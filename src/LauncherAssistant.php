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
    public bool $hasCpSettings = true;  // Settings page for API keys only
    public bool $hasCpSection = false;  // Uses Launcher's CP section for other settings

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
                $event->rules['POST astronaut/ai/start'] = 'astronaut/ai/start';
                $event->rules['POST astronaut/ai/send'] = 'astronaut/ai/send';
                $event->rules['GET astronaut/ai/history'] = 'astronaut/ai/history';
                $event->rules['GET astronaut/ai/list'] = 'astronaut/ai/list';
                $event->rules['POST astronaut/ai/new'] = 'astronaut/ai/new';
                $event->rules['POST astronaut/ai/delete'] = 'astronaut/ai/delete';
                $event->rules['GET astronaut/ai/validate'] = 'astronaut/ai/validate';
                $event->rules['GET astronaut/ai/models'] = 'astronaut/ai/models';

                // Admin panel routes - integrate with Launcher's CP section
                $event->rules['launcher/api-config'] = 'astronaut/admin/api-config';
                $event->rules['launcher/brand-info'] = 'astronaut/admin/brand-info';
                $event->rules['launcher/guidelines'] = 'astronaut/admin/guidelines';

                // Admin panel save actions
                $event->rules['POST launcher/save-api-config'] = 'astronaut/admin/save-api-config';
                $event->rules['POST launcher/save-brand-info'] = 'astronaut/admin/save-brand-info';
                $event->rules['POST launcher/save-guidelines'] = 'astronaut/admin/save-guidelines';
                $event->rules['POST launcher/validate-key'] = 'astronaut/admin/validate-key';
            }
        );

        // Show dependency warning in CP if Rocket Launcher is missing
        if (Craft::$app->getRequest()->getIsCpRequest()) {
            $this->showDependencyWarning();
        }

        // Register assistant assets in CP (only if Rocket Launcher is installed)
        if (Craft::$app->getRequest()->getIsCpRequest() && $this->isRocketLauncherInstalled()) {
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
                                            sendMessageUrl: Craft.getActionUrl('astronaut/ai/send'),
                                            startConversationUrl: Craft.getActionUrl('astronaut/ai/start'),
                                            validateUrl: Craft.getActionUrl('astronaut/ai/validate')
                                        });
                                    }
                                });
                            } else {
                                document.addEventListener('DOMContentLoaded', function() {
                                    if (window.LauncherAI) {
                                        window.LauncherAI.init({
                                            hotkey: '$hotkey',
                                            sendMessageUrl: Craft.getActionUrl('astronaut/ai/send'),
                                            startConversationUrl: Craft.getActionUrl('astronaut/ai/start'),
                                            validateUrl: Craft.getActionUrl('astronaut/ai/validate')
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

        // Register with Launcher's addon system (only if Launcher is installed)
        if ($this->isRocketLauncherInstalled()) {
            $this->registerWithLauncher();
        }

        Craft::info(
            Craft::t(
                'astronaut',
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
                    'handle' => 'astronaut',
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

        // Register modal tab
        Event::on(
            \brilliance\launcher\services\AddonService::class,
            \brilliance\launcher\services\AddonService::EVENT_REGISTER_MODAL_TABS,
            function (\brilliance\launcher\events\RegisterModalTabsEvent $event) {
                $settings = $this->getSettings();

                // Generate assistant tab HTML
                $tabHtml = $this->getAssistantTabHtml();

                $event->registerTab('assistant', [
                    'label' => 'Assistant',
                    'hotkey' => $settings->aiHotkey,
                    'html' => $tabHtml,
                    'priority' => 10, // Left side (lower priority = left)
                ]);
            }
        );
    }

    /**
     * Generate the HTML for the assistant tab
     */
    protected function getAssistantTabHtml(): string
    {
        return <<<HTML
<div class="launcher-ai-assistant-content">
    <div id="launcher-ai-messages" class="launcher-ai-messages">
        <div class="launcher-ai-welcome">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="M9 9h.01M15 9h.01M9 15a3 3 0 0 0 6 0"></path>
            </svg>
            <h3>Welcome to Craft AI Assistant</h3>
            <p>I can help you create content, manage your site, and answer questions about Craft CMS.</p>
            <div class="launcher-ai-suggestions">
                <button class="launcher-ai-suggestion" data-prompt="Create a blog post about our latest product">
                    Create a blog post
                </button>
                <button class="launcher-ai-suggestion" data-prompt="Clear all caches">
                    Clear caches
                </button>
                <button class="launcher-ai-suggestion" data-prompt="What sections do I have in my site?">
                    List my sections
                </button>
            </div>
        </div>
    </div>
    <div class="launcher-ai-input-wrapper">
        <textarea
            id="launcher-ai-input"
            class="launcher-ai-input"
            placeholder="Ask me anything or type a command..."
            rows="1"
        ></textarea>
        <button type="button" id="launcher-ai-send" class="launcher-ai-send" title="Send (Enter)">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="22" y1="2" x2="11" y2="13"></line>
                <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
            </svg>
        </button>
    </div>
</div>
HTML;
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml(): ?string
    {
        // If Rocket Launcher is not installed, show only the dependency warning
        if (!$this->isRocketLauncherInstalled()) {
            return Craft::$app->getView()->renderTemplate('astronaut/settings-missing-dependency');
        }

        // Check which keys are set via environment variables (this doesn't require database)
        $envKeys = [
            'claude' => $this->aiSettingsService->hasEnvApiKey('claude'),
            'openai' => $this->aiSettingsService->hasEnvApiKey('openai'),
            'gemini' => $this->aiSettingsService->hasEnvApiKey('gemini'),
        ];

        try {
            // Get masked API keys for display (this may require database)
            $maskedKeys = [
                'claude' => $this->aiSettingsService->getMaskedApiKey('claude'),
                'openai' => $this->aiSettingsService->getMaskedApiKey('openai'),
                'gemini' => $this->aiSettingsService->getMaskedApiKey('gemini'),
            ];

            return Craft::$app->getView()->renderTemplate('astronaut/settings', [
                'settings' => $this->getSettings(),
                'maskedKeys' => $maskedKeys,
                'envKeys' => $envKeys,
            ]);
        } catch (\Exception $e) {
            // During initial installation, database tables may not exist yet
            // If env vars are configured, show settings page anyway with just env var info
            if ($envKeys['claude'] || $envKeys['openai'] || $envKeys['gemini']) {
                return Craft::$app->getView()->renderTemplate('astronaut/settings', [
                    'settings' => $this->getSettings(),
                    'maskedKeys' => [
                        'claude' => $envKeys['claude'] ? '(Set via environment variable)' : null,
                        'openai' => $envKeys['openai'] ? '(Set via environment variable)' : null,
                        'gemini' => $envKeys['gemini'] ? '(Set via environment variable)' : null,
                    ],
                    'envKeys' => $envKeys,
                    'tablesNotReady' => true, // Flag to show a notice about pending migrations
                ]);
            }

            // No env vars and no database - show migration reminder
            return Craft::$app->getView()->renderTemplate('astronaut/settings-pending-migration', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    public function beforeSaveSettings(): bool
    {
        // Save API keys to AISettingsService instead of plugin settings
        $request = Craft::$app->getRequest();

        $attributes = [];

        // Only update API keys if they're provided (not empty)
        if ($claudeKey = $request->getBodyParam('claudeApiKey')) {
            $attributes['claudeApiKey'] = $claudeKey;
        }
        if ($openaiKey = $request->getBodyParam('openaiApiKey')) {
            $attributes['openaiApiKey'] = $openaiKey;
        }
        if ($geminiKey = $request->getBodyParam('geminiApiKey')) {
            $attributes['geminiApiKey'] = $geminiKey;
        }

        // Save to AISettingsService if any keys were provided
        if (!empty($attributes)) {
            $this->aiSettingsService->saveSettings($attributes);
        }

        return parent::beforeSaveSettings();
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
            $conversationTableExists = $db->schema->getTableSchema('{{%launcher_ai_conversations}}') !== null;
            $messagesTableExists = $db->schema->getTableSchema('{{%launcher_ai_messages}}') !== null;
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
     * Check if Rocket Launcher plugin is installed
     */
    public function isRocketLauncherInstalled(): bool
    {
        return Craft::$app->plugins->isPluginInstalled('launcher') &&
               Craft::$app->plugins->isPluginEnabled('launcher');
    }

    /**
     * Show dependency warning banner in CP if Rocket Launcher is missing
     */
    protected function showDependencyWarning(): void
    {
        if (!$this->isRocketLauncherInstalled()) {
            Event::on(
                View::class,
                View::EVENT_BEFORE_RENDER_TEMPLATE,
                function ($event) {
                    // Skip settings page - it has its own dedicated warning
                    $request = Craft::$app->getRequest();
                    if ($request->getSegment(1) === 'settings' &&
                        $request->getSegment(2) === 'plugins' &&
                        $request->getSegment(3) === 'astronaut') {
                        return;
                    }

                    $pluginStoreUrl = 'https://plugins.craftcms.com/rocket-launcher';

                    $warning = '<div style="background: #fef3c7; border: 2px solid #f59e0b; border-radius: 8px; padding: 16px; margin: 16px 0;">
                        <strong style="color: #92400e; font-size: 16px;">🚀 Houston, We Have a Problem!</strong>
                        <p style="color: #78350f; margin: 8px 0 0 0;">
                            You\'re trying to launch an Astronaut without a Rocket Launcher!
                            This plugin requires the free <strong>Rocket Launcher</strong> plugin to function.
                        </p>
                        <p style="margin: 12px 0 0 0;">
                            <a href="' . $pluginStoreUrl . '" target="_blank" style="display: inline-block; background: #1976d2; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-weight: 500;">
                                Install Rocket Launcher from Plugin Store →
                            </a>
                        </p>
                    </div>';

                    Craft::$app->getView()->registerHtml($warning);
                }
            );
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
