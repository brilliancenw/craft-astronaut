<?php

namespace brilliance\launcherassistant\assetbundles\assistant;

use brilliance\launcher\assetbundles\launcher\LauncherAsset;
use craft\web\AssetBundle;

/**
 * Assistant Asset Bundle
 *
 * Extends the Launcher asset bundle to add AI assistant-specific assets
 */
class AssistantAsset extends AssetBundle
{
    public function init()
    {
        $this->sourcePath = '@brilliance/launcherassistant/assetbundles/assistant/dist';

        // Depend on the Launcher asset bundle for shared UI components
        $this->depends = [
            LauncherAsset::class,
        ];

        $this->js = [
            'js/ai-assistant.js',
        ];

        $this->css = [
            'css/ai-assistant.css',
        ];

        parent::init();
    }
}
