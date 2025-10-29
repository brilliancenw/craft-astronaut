<?php

namespace brilliance\launcherassistant\controllers;

use brilliance\launcherassistant\LauncherAssistant;
use Craft;
use craft\web\Controller;
use yii\web\Response;

/**
 * Admin Controller
 *
 * Handles the Launcher admin panel CP section
 */
class AdminController extends Controller
{
    /**
     * Dashboard/Index
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('accessLauncher');

        $settings = LauncherAssistant::$plugin->getSettings();
        $aiSettings = LauncherAssistant::$plugin->aiSettingsService->getSettings();

        // Get conversation stats
        $conversationCount = \brilliance\launcherassistant\records\AIConversationRecord::find()->count();
        $userConversationCount = \brilliance\launcherassistant\records\AIConversationRecord::find()
            ->where(['userId' => Craft::$app->user->id])
            ->count();

        // Check provider configuration
        $hasApiKey = LauncherAssistant::$plugin->aiSettingsService->hasApiKey($aiSettings->aiProvider);

        return $this->renderTemplate('astronaut/admin/index', [
            'settings' => $settings,
            'aiSettings' => $aiSettings,
            'conversationCount' => $conversationCount,
            'userConversationCount' => $userConversationCount,
            'hasApiKey' => $hasApiKey,
            'selectedTab' => 'dashboard',
        ]);
    }

    /**
     * API Configuration tab
     */
    public function actionApiConfig(): Response
    {
        $this->requirePermission('accessLauncher');

        $settings = LauncherAssistant::$plugin->getSettings();
        $aiSettings = LauncherAssistant::$plugin->aiSettingsService->getSettings();

        return $this->renderTemplate('astronaut/admin/api-config', [
            'settings' => $settings,
            'aiSettings' => $aiSettings,
            'selectedTab' => 'api-config',
        ]);
    }

    /**
     * Brand Information tab
     */
    public function actionBrandInfo(): Response
    {
        $this->requirePermission('accessLauncher');

        $settings = LauncherAssistant::$plugin->getSettings();
        $aiSettings = LauncherAssistant::$plugin->aiSettingsService->getSettings();

        return $this->renderTemplate('astronaut/admin/brand-info', [
            'settings' => $settings,
            'aiSettings' => $aiSettings,
            'selectedTab' => 'brand-info',
        ]);
    }

    /**
     * Content Guidelines tab
     */
    public function actionGuidelines(): Response
    {
        $this->requirePermission('accessLauncher');

        $settings = LauncherAssistant::$plugin->getSettings();
        $aiSettings = LauncherAssistant::$plugin->aiSettingsService->getSettings();

        return $this->renderTemplate('astronaut/admin/guidelines', [
            'settings' => $settings,
            'aiSettings' => $aiSettings,
            'selectedTab' => 'guidelines',
        ]);
    }

    /**
     * Save API configuration
     */
    public function actionSaveApiConfig(): Response
    {
        $this->requirePermission('accessLauncher');
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $attributes = [
            'aiProvider' => $request->getBodyParam('aiProvider'),
        ];

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

        // Save selected Claude model
        if ($claudeModel = $request->getBodyParam('claudeModel')) {
            $attributes['claudeModel'] = $claudeModel;
        }

        if (LauncherAssistant::$plugin->aiSettingsService->saveSettings($attributes)) {
            Craft::$app->getSession()->setNotice('API configuration saved.');
            return $this->redirectToPostedUrl();
        }

        Craft::$app->getSession()->setError('Could not save API configuration.');
        return $this->redirectToPostedUrl();
    }

    /**
     * Save brand information
     */
    public function actionSaveBrandInfo(): Response
    {
        $this->requirePermission('accessLauncher');
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $attributes = [
            'websiteName' => $request->getBodyParam('websiteName'),
            'brandOwner' => $request->getBodyParam('brandOwner'),
            'brandTagline' => $request->getBodyParam('brandTagline'),
            'brandDescription' => $request->getBodyParam('brandDescription'),
            'brandVoice' => $request->getBodyParam('brandVoice'),
            'targetAudience' => $request->getBodyParam('targetAudience'),
            'brandLogoUrl' => $request->getBodyParam('brandLogoUrl'),
            'brandColors' => $request->getBodyParam('brandColors', []),
        ];

        if (LauncherAssistant::$plugin->aiSettingsService->saveSettings($attributes)) {
            Craft::$app->getSession()->setNotice('Brand information saved.');
            return $this->redirectToPostedUrl();
        }

        Craft::$app->getSession()->setError('Could not save brand information.');
        return $this->redirectToPostedUrl();
    }

    /**
     * Save content guidelines
     */
    public function actionSaveGuidelines(): Response
    {
        $this->requirePermission('accessLauncher');
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $attributes = [
            'contentGuidelines' => $request->getBodyParam('contentGuidelines'),
            'contentTone' => $request->getBodyParam('contentTone'),
            'writingStyle' => $request->getBodyParam('writingStyle'),
            'seoGuidelines' => $request->getBodyParam('seoGuidelines'),
            'customGuidelines' => $request->getBodyParam('customGuidelines', []),
        ];

        if (LauncherAssistant::$plugin->aiSettingsService->saveSettings($attributes)) {
            Craft::$app->getSession()->setNotice('Content guidelines saved.');
            return $this->redirectToPostedUrl();
        }

        Craft::$app->getSession()->setError('Could not save content guidelines.');
        return $this->redirectToPostedUrl();
    }

    /**
     * Validate API key
     */
    public function actionValidateKey(): Response
    {
        $this->requirePermission('accessLauncher');
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $provider = Craft::$app->getRequest()->getBodyParam('provider');
        $apiKey = Craft::$app->getRequest()->getBodyParam('apiKey');

        if (empty($provider) || empty($apiKey)) {
            return $this->asJson([
                'success' => false,
                'message' => 'Provider and API key are required',
            ]);
        }

        // Temporarily create provider with this key to validate
        $providerInstance = match ($provider) {
            'claude' => new \brilliance\launcherassistant\ai\providers\ClaudeProvider($apiKey),
            default => null,
        };

        if (!$providerInstance) {
            return $this->asJson([
                'success' => false,
                'message' => 'Provider not yet implemented',
            ]);
        }

        $validation = $providerInstance->validate();

        return $this->asJson([
            'success' => $validation['valid'],
            'message' => $validation['message'],
        ]);
    }
}
