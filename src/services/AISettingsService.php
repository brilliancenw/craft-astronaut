<?php

namespace brilliance\launcherassistant\services;

use brilliance\launcherassistant\records\AISettingsRecord;
use Craft;
use craft\base\Component;
use craft\helpers\App;

/**
 * AI Settings Service
 *
 * Manages environment-specific AI settings (API keys, brand info, guidelines)
 * These settings are stored in the database and are NOT part of project config
 */
class AISettingsService extends Component
{
    private ?AISettingsRecord $_settings = null;

    /**
     * Get AI settings
     */
    public function getSettings(): ?AISettingsRecord
    {
        if ($this->_settings === null) {
            $this->_settings = AISettingsRecord::getInstance();
        }

        return $this->_settings;
    }

    /**
     * Save AI settings
     */
    public function saveSettings(array $attributes): bool
    {
        $settings = $this->getSettings();

        // If settings table doesn't exist yet (during installation), skip save
        if (!$settings) {
            Craft::warning('Cannot save AI settings - database table not yet created. Run migrations first.', __METHOD__);
            return false;
        }

        // Set attributes
        $settings->setAttributes($attributes, false);

        // Handle API keys - encrypt literal values, store env var references as-is
        if (isset($attributes['claudeApiKey'])) {
            $settings->claudeApiKey = $this->prepareApiKeyForStorage($attributes['claudeApiKey']);
        }
        if (isset($attributes['openaiApiKey'])) {
            $settings->openaiApiKey = $this->prepareApiKeyForStorage($attributes['openaiApiKey']);
        }
        if (isset($attributes['geminiApiKey'])) {
            $settings->geminiApiKey = $this->prepareApiKeyForStorage($attributes['geminiApiKey']);
        }

        return $settings->save();
    }

    /**
     * Get decrypted API key for a provider
     *
     * Checks environment variables first, then falls back to database.
     * This allows sensitive API keys to be stored securely in .env files
     * rather than in the database.
     *
     * Environment variables:
     * - LAUNCHER_CLAUDE_API_KEY
     * - LAUNCHER_OPENAI_API_KEY
     * - LAUNCHER_GEMINI_API_KEY
     */
    public function getApiKey(string $provider): ?string
    {
        // Check environment variables first (more secure)
        $envKey = match ($provider) {
            'claude' => App::env('LAUNCHER_CLAUDE_API_KEY'),
            'openai' => App::env('LAUNCHER_OPENAI_API_KEY'),
            'gemini' => App::env('LAUNCHER_GEMINI_API_KEY'),
            default => null,
        };

        if ($envKey) {
            return $envKey;
        }

        // Fall back to database (encrypted storage)
        $settings = $this->getSettings();

        // If settings table doesn't exist yet (during installation), return null
        if (!$settings) {
            return null;
        }

        $encrypted = match ($provider) {
            'claude' => $settings->claudeApiKey,
            'openai' => $settings->openaiApiKey,
            'gemini' => $settings->geminiApiKey,
            default => null,
        };

        if (!$encrypted) {
            return null;
        }

        return $this->decryptApiKey($encrypted);
    }

    /**
     * Get masked API key for display (show last 4 characters)
     */
    public function getMaskedApiKey(string $provider): ?string
    {
        // Check if stored value is an env var reference
        $settings = $this->getSettings();
        if ($settings) {
            $storedValue = match ($provider) {
                'claude' => $settings->claudeApiKey,
                'openai' => $settings->openaiApiKey,
                'gemini' => $settings->geminiApiKey,
                default => null,
            };

            // If it's an env var reference, show that instead of masking
            if ($storedValue && str_starts_with($storedValue, '$')) {
                return $storedValue; // Show the env var reference as-is (e.g., "$LAUNCHER_CLAUDE_API_KEY")
            }
        }

        // Otherwise get the actual key and mask it
        $apiKey = $this->getApiKey($provider);

        if (!$apiKey) {
            return null;
        }

        $length = strlen($apiKey);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4) . substr($apiKey, -4);
    }

    /**
     * Check if an API key is configured for a provider
     */
    public function hasApiKey(string $provider): bool
    {
        return !empty($this->getApiKey($provider));
    }

    /**
     * Check if an API key is set via environment variable
     */
    public function hasEnvApiKey(string $provider): bool
    {
        $envKey = match ($provider) {
            'claude' => App::env('LAUNCHER_CLAUDE_API_KEY'),
            'openai' => App::env('LAUNCHER_OPENAI_API_KEY'),
            'gemini' => App::env('LAUNCHER_GEMINI_API_KEY'),
            default => null,
        };

        return !empty($envKey);
    }

    /**
     * Prepare API key for storage
     * Environment variable references (starting with $) are stored as-is
     * Literal values are encrypted before storage
     */
    private function prepareApiKeyForStorage(?string $key): ?string
    {
        if (empty($key)) {
            return null;
        }

        // If it's an environment variable reference, store as-is
        if (str_starts_with($key, '$')) {
            return $key;
        }

        // Otherwise, encrypt the literal value
        return $this->encryptApiKey($key);
    }

    /**
     * Encrypt an API key
     */
    private function encryptApiKey(?string $key): ?string
    {
        if (empty($key)) {
            return null;
        }

        // Use Craft's security helper to encrypt, then base64 encode for storage
        $encrypted = Craft::$app->getSecurity()->encryptByPassword($key, $this->getEncryptionKey());
        return base64_encode($encrypted);
    }

    /**
     * Decrypt an API key or parse environment variable reference
     */
    private function decryptApiKey(?string $encrypted): ?string
    {
        if (empty($encrypted)) {
            return null;
        }

        // If it's an environment variable reference, parse it
        if (str_starts_with($encrypted, '$')) {
            return App::parseEnv($encrypted);
        }

        // Otherwise, decrypt the encrypted value
        try {
            // Base64 decode first, then decrypt
            $decoded = base64_decode($encrypted);
            return Craft::$app->getSecurity()->decryptByPassword($decoded, $this->getEncryptionKey());
        } catch (\Exception $e) {
            Craft::error('Failed to decrypt API key: ' . $e->getMessage(), __METHOD__);
            return null;
        }
    }

    /**
     * Get encryption key from environment or generate one
     */
    private function getEncryptionKey(): string
    {
        // Use Craft's security key
        return App::env('CRAFT_SECURITY_KEY') ?? Craft::$app->getConfig()->getGeneral()->securityKey;
    }

    /**
     * Get provider name
     */
    public function getProviderName(): string
    {
        $settings = $this->getSettings();
        return $settings?->aiProvider ?? 'claude';
    }

    /**
     * Get brand information
     */
    public function getBrandInfo(): array
    {
        $settings = $this->getSettings();

        // If settings table doesn't exist yet, return empty defaults
        if (!$settings) {
            return [
                'websiteName' => null,
                'brandOwner' => null,
                'brandTagline' => null,
                'brandDescription' => null,
                'brandVoice' => null,
                'targetAudience' => null,
                'brandColors' => [],
                'brandLogoUrl' => null,
            ];
        }

        return [
            'websiteName' => $settings->websiteName,
            'brandOwner' => $settings->brandOwner,
            'brandTagline' => $settings->brandTagline,
            'brandDescription' => $settings->brandDescription,
            'brandVoice' => $settings->brandVoice,
            'targetAudience' => $settings->targetAudience,
            'brandColors' => $settings->brandColors ?? [],
            'brandLogoUrl' => $settings->brandLogoUrl,
        ];
    }

    /**
     * Get content guidelines
     */
    public function getContentGuidelines(): array
    {
        $settings = $this->getSettings();

        // If settings table doesn't exist yet, return empty defaults
        if (!$settings) {
            return [
                'contentGuidelines' => null,
                'contentTone' => null,
                'writingStyle' => null,
                'seoGuidelines' => null,
                'customGuidelines' => [],
            ];
        }

        return [
            'contentGuidelines' => $settings->contentGuidelines,
            'contentTone' => $settings->contentTone,
            'writingStyle' => $settings->writingStyle,
            'seoGuidelines' => $settings->seoGuidelines,
            'customGuidelines' => $settings->customGuidelines ?? [],
        ];
    }
}
