# Launcher Assistant

AI-powered assistant for Craft CMS that seamlessly integrates with the Launcher plugin, providing intelligent content management and site administration capabilities.

## Requirements

- Craft CMS 5.0.0 or later
- PHP 8.2 or later
- **Launcher plugin 1.2.0 or later** (required dependency)

## Features

- AI-powered conversation interface with Craft CMS
- 20+ tools for content management and site administration
- Support for multiple AI providers (Claude, OpenAI, Gemini)
- Seamless integration with Launcher plugin UI
- Separate hotkey (Cmd+J) for quick assistant access
- Persistent conversation history
- Tool calling for Craft CMS operations

## Installation

### Via Composer

```bash
composer require brilliance/craft-launcher-assistant
```

### Enable the Plugin

```bash
php craft plugin/install launcher-assistant
```

## Configuration

Navigate to **Launcher → API Configuration** in the Craft admin panel to configure your AI provider:

1. Select your AI provider (Claude, OpenAI, or Gemini)
2. Enter your API key
3. Configure brand information and content guidelines
4. Start using the assistant with Cmd+J (Mac) or Ctrl+J (Windows/Linux)

## Usage

Press **Cmd+J** (Mac) or **Ctrl+J** (Windows/Linux) from anywhere in the Craft admin panel to open the Assistant.

The assistant can help you with:
- Creating and managing content
- Searching for entries, categories, and assets
- Navigating admin settings
- Running system utilities
- And much more!

## License

Proprietary - Copyright (c) Brilliance

## Support

- Issues: https://github.com/brilliancenw/craft-launcher-assistant/issues
- Documentation: https://github.com/brilliancenw/craft-launcher-assistant
