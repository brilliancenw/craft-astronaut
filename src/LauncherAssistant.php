<?php

namespace brilliance\launcherassistant;

use brilliance\launcher\Launcher;
use brilliance\launcherassistant\assetbundles\assistant\AssistantAsset;
use brilliance\launcherassistant\models\Settings;
use brilliance\launcherassistant\services\AIConversationService;
use brilliance\launcherassistant\services\AIToolService;
use brilliance\launcherassistant\services\CraftContextService;
use brilliance\launcherassistant\services\AISettingsService;
use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use craft\web\View;
use yii\base\Event;

/**
 * Launcher Assistant Plugin
 *
 * AI-powered assistant that integrates with the Launcher plugin
 */
class LauncherAssistant extends Plugin
{
    public static ?LauncherAssistant $plugin = null;
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = false; // Settings managed through Launcher CP section
    public bool $hasCpSection = false;  // Uses Launcher's CP section

    public static function config(): array
    {
        return [
            'components' => [
                'aiConversationService' => AIConversationService::class,
                'aiToolService' => AIToolService::class,
                'craftContextService' => CraftContextService::class,
                'aiSettingsService' => AISettingsService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        // Check if Launcher is installed
        if (!Craft::$app->plugins->isPluginInstalled('launcher')) {
            Craft::error('Launcher Assistant requires the Launcher plugin to be installed', __METHOD__);
            return;
        }

        // Set controller namespace
        $this->controllerNamespace = 'brilliance\\launcherassistant\\controllers';

        $this->setComponents([
            'aiConversationService' => AIConversationService::class,
            'aiToolService' => AIToolService::class,
            'craftContextService' => CraftContextService::class,
            'aiSettingsService' => AISettingsService::class,
        ]);

        // Register URL rules
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                // API endpoints for AI interaction
                $event->rules['POST launcher-assistant/ai/start'] = 'launcher-assistant/ai/start';
                $event->rules['POST launcher-assistant/ai/send'] = 'launcher-assistant/ai/send';
                $event->rules['GET launcher-assistant/ai/history'] = 'launcher-assistant/ai/history';
                $event->rules['GET launcher-assistant/ai/list'] = 'launcher-assistant/ai/list';
                $event->rules['POST launcher-assistant/ai/new'] = 'launcher-assistant/ai/new';
                $event->rules['POST launcher-assistant/ai/delete'] = 'launcher-assistant/ai/delete';
                $event->rules['GET launcher-assistant/ai/validate'] = 'launcher-assistant/ai/validate';
                $event->rules['GET launcher-assistant/ai/models'] = 'launcher-assistant/ai/models';

                // Admin panel routes - integrate with Launcher's CP section
                $event->rules['launcher/api-config'] = 'launcher-assistant/admin/api-config';
                $event->rules['launcher/brand-info'] = 'launcher-assistant/admin/brand-info';
                $event->rules['launcher/guidelines'] = 'launcher-assistant/admin/guidelines';

                // Admin panel save actions
                $event->rules['POST launcher/save-api-config'] = 'launcher-assistant/admin/save-api-config';
                $event->rules['POST launcher/save-brand-info'] = 'launcher-assistant/admin/save-brand-info';
                $event->rules['POST launcher/save-guidelines'] = 'launcher-assistant/admin/save-guidelines';
                $event->rules['POST launcher/validate-key'] = 'launcher-assistant/admin/validate-key';
            }
        );

        // Register assistant assets in CP
        if (Craft::$app->getRequest()->getIsCpRequest()) {
            Event::on(
                View::class,
                View::EVENT_BEFORE_RENDER_TEMPLATE,
                function () {
                    if (Craft::$app->getUser()->checkPermission('accessLauncher')) {
                        // Register assistant assets
                        Craft::$app->getView()->registerAssetBundle(AssistantAsset::class);

                        $settings = $this->getSettings();
                        $hotkey = $settings->aiHotkey;

                        // Initialize AI assistant
                        $js = <<<JS
                        // Ensure LauncherPlugin is ready
                        if (typeof Craft !== 'undefined' && window.LauncherPlugin) {
                            if (Craft.cp && Craft.cp.ready) {
                                Craft.cp.ready(function() {
                                    if (window.LauncherAI) {
                                        window.LauncherAI.init({
                                            hotkey: '$hotkey',
                                            sendMessageUrl: Craft.getActionUrl('launcher-assistant/ai/send'),
                                            startConversationUrl: Craft.getActionUrl('launcher-assistant/ai/start'),
                                            validateUrl: Craft.getActionUrl('launcher-assistant/ai/validate')
                                        });
                                    }
                                });
                            } else {
                                document.addEventListener('DOMContentLoaded', function() {
                                    if (window.LauncherAI) {
                                        window.LauncherAI.init({
                                            hotkey: '$hotkey',
                                            sendMessageUrl: Craft.getActionUrl('launcher-assistant/ai/send'),
                                            startConversationUrl: Craft.getActionUrl('launcher-assistant/ai/start'),
                                            validateUrl: Craft.getActionUrl('launcher-assistant/ai/validate')
                                        });
                                    }
                                });
                            }
                        }
                        JS;

                        Craft::$app->getView()->registerJs($js, View::POS_END);
                    }
                }
            );
        }

        // Register with Launcher's addon system
        $this->registerWithLauncher();

        Craft::info(
            Craft::t(
                'launcher-assistant',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    /**
     * Register this plugin with Launcher's addon system
     */
    protected function registerWithLauncher(): void
    {
        // Register as a Launcher addon
        Event::on(
            \brilliance\launcher\services\AddonService::class,
            \brilliance\launcher\services\AddonService::EVENT_REGISTER_ADDONS,
            function (\brilliance\launcher\events\RegisterAddonPluginsEvent $event) {
                $event->registerAddon([
                    'handle' => 'launcher-assistant',
                    'name' => 'Assistant',
                    'priority' => 10,
                ]);
            }
        );

        // Register CP navigation items
        Event::on(
            \brilliance\launcher\services\AddonService::class,
            \brilliance\launcher\services\AddonService::EVENT_REGISTER_CP_NAV_ITEMS,
            function (\brilliance\launcher\events\RegisterCpNavItemsEvent $event) {
                $event->registerNavItem('api-config', [
                    'label' => 'API Configuration',
                    'url' => 'launcher/api-config',
                ]);
                $event->registerNavItem('brand-info', [
                    'label' => 'Brand Information',
                    'url' => 'launcher/brand-info',
                ]);
                $event->registerNavItem('guidelines', [
                    'label' => 'Content Guidelines',
                    'url' => 'launcher/guidelines',
                ]);
            }
        );

        // Register custom hotkey
        Event::on(
            \brilliance\launcher\services\AddonService::class,
            \brilliance\launcher\services\AddonService::EVENT_REGISTER_HOTKEYS,
            function (\brilliance\launcher\events\RegisterHotkeysEvent $event) {
                $settings = $this->getSettings();
                $event->registerHotkey(
                    $settings->aiHotkey,
                    'LauncherAI.open',
                    'Open Launcher Assistant'
                );
            }
        );
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    public function install(): void
    {
        parent::install();
    }

    /**
     * @inheritdoc
     */
    public function afterInstall(): void
    {
        parent::afterInstall();

        // Ensure database tables are created
        $this->createAITables();
    }

    /**
     * Create AI conversation tables
     */
    public function createAITables(): bool
    {
        $db = Craft::$app->getDb();

        try {
            // Check if tables already exist
            $conversationTableExists = $db->schema->getTableSchema('{{%ai_conversations}}') !== null;
            $messagesTableExists = $db->schema->getTableSchema('{{%ai_messages}}') !== null;
            $settingsTableExists = $db->schema->getTableSchema('{{%launcher_ai_settings}}') !== null;

            if ($conversationTableExists && $messagesTableExists && $settingsTableExists) {
                return true; // Tables already exist
            }

            Craft::info('AI tables will be created by migrations', __METHOD__);
            return true;

        } catch (\Exception $e) {
            Craft::error('Failed to check AI table status: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function beforeUninstall(): void
    {
        parent::beforeUninstall();

        // Clean up AI data
        $db = Craft::$app->getDb();

        try {
            // Note: Tables will be dropped automatically by migrations on uninstall
            Craft::info('Launcher Assistant data will be removed', __METHOD__);
        } catch (\Exception $e) {
            Craft::warning('Failed to clean up Launcher Assistant data: ' . $e->getMessage(), __METHOD__);
        }
    }
}
