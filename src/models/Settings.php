<?php

namespace brilliance\launcherassistant\models;

use craft\base\Model;

/**
 * Launcher Assistant Settings Model
 */
class Settings extends Model
{
    // AI Assistant settings
    public bool $enableAIAssistant = true;
    public string $aiHotkey = 'cmd+j';
    public string $aiProvider = 'claude';
    public string $claudeApiKey = '';
    public string $claudeModel = 'claude-sonnet-4-20250514';
    public string $openaiApiKey = '';
    public string $geminiApiKey = '';
    public int $maxAIConversationHistory = 50;
    public bool $enableAIStreaming = true;

    // Website/Brand information
    public string $websiteName = '';
    public string $brandOwner = '';
    public string $brandTagline = '';
    public string $brandDescription = '';
    public string $brandVoice = '';
    public string $targetAudience = '';
    public array $brandColors = [];
    public string $brandLogoUrl = '';

    // Content Guidelines
    public string $contentGuidelines = '';
    public string $contentTone = '';
    public string $writingStyle = '';
    public string $seoGuidelines = '';
    public array $customGuidelines = [];

    public function rules(): array
    {
        return [
            [['aiHotkey', 'aiProvider', 'claudeModel'], 'string'],
            [['claudeApiKey', 'openaiApiKey', 'geminiApiKey'], 'string'],
            [['websiteName', 'brandOwner', 'brandTagline', 'brandDescription', 'brandVoice', 'targetAudience'], 'string'],
            [['contentGuidelines', 'contentTone', 'writingStyle', 'seoGuidelines'], 'string'],
            [['maxAIConversationHistory'], 'number', 'integerOnly' => true],
            [['maxAIConversationHistory'], 'default', 'value' => 50],
            [['enableAIStreaming'], 'boolean'],
            [['brandColors', 'customGuidelines'], 'safe'],
        ];
    }
}
