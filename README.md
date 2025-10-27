# Launcher Assistant

**Professional AI-powered assistant for Craft CMS** that seamlessly integrates with the Launcher plugin, providing intelligent content management and site administration capabilities.

> **Note:** This is a **commercial plugin** that requires a paid license. However, it depends on the **Craft Launcher** plugin, which is and always will be **free and open source** (GPL-3.0).

## Requirements

- Craft CMS 5.0.0 or later
- PHP 8.2 or later
- **[Craft Launcher](https://github.com/brilliance/craft-launcher) plugin** (required dependency - **FREE forever**)
- Claude API key from [Anthropic](https://console.anthropic.com/) (currently the only supported provider)

## Features

### AI-Powered Content Management
- Intelligent conversation interface integrated into Craft CMS admin
- Create, edit, and manage content through natural language
- Get instant answers about your site structure and content
- Generate drafts that you can review before publishing

### 20+ Built-in Tools
- **Content Tools**: Search entries, get section details, create drafts
- **Navigation**: Find and access admin settings, utilities
- **System Tools**: Clear caches, rebuild search indexes, run queue jobs
- **Multi-site Support**: Works seamlessly with Craft's multi-site features

### AI Provider Support
- **Claude (Anthropic)** - Currently supported with full function calling
- **OpenAI** - In development
- **Gemini (Google)** - In development

### Seamless Integration
- Integrates directly into the Launcher plugin interface
- Dedicated hotkey (Cmd+J / Ctrl+J) for instant access
- Persistent conversation history per user
- Beautiful chat interface with rich text formatting
- Brand-aware responses based on your configured guidelines

## Installation

### Step 1: Install Craft Launcher (Free)

First, install the free Craft Launcher plugin if you haven't already:

```bash
composer require brilliance/craft-launcher
php craft plugin/install launcher
```

The Launcher plugin is **completely free** and provides the foundation for the Assistant.

### Step 2: Install Launcher Assistant (Commercial)

```bash
composer require brilliance/craft-launcher-assistant
php craft plugin/install launcher-assistant
```

## Configuration

After installation, configure the Assistant through the Craft admin panel:

### 1. API Configuration

Navigate to **Launcher → API Configuration**:

1. **Select AI Provider**: Choose Claude (currently the only supported provider)
2. **Enter Claude API Key**: Get your API key from [console.anthropic.com](https://console.anthropic.com/)
3. **Choose Model**: Click "Fetch Available Models" to see and select from available Claude models

### 2. Brand Information (Optional)

Navigate to **Launcher → Brand Information** to customize responses:

- Website name and owner
- Brand voice and tagline
- Target audience
- Brand colors and logo

### 3. Content Guidelines (Optional)

Navigate to **Launcher → Content Guidelines** to set standards:

- General content guidelines
- Tone and writing style
- SEO guidelines
- Custom guidelines

These settings help the AI understand your brand and provide more relevant responses.

## Usage

### Opening the Assistant

Press **Cmd+J** (Mac) or **Ctrl+J** (Windows/Linux) from anywhere in the Craft admin panel to open the Assistant tab in Launcher.

### Example Commands

The assistant can help you with a wide variety of tasks:

**Content Management:**
- "Create a blog post about our new product launch"
- "Show me all entries in the News section"
- "Find entries with the tag 'featured'"

**Site Navigation:**
- "Take me to the user settings"
- "Where can I manage email settings?"
- "Show me the site utilities"

**System Operations:**
- "Clear all caches"
- "Rebuild the search indexes"
- "Run pending queue jobs"

**Information Queries:**
- "What sections do I have?"
- "Show me the fields in the Blog section"
- "How many entries are in the News section?"

The assistant understands natural language and will guide you through complex tasks step by step.

## License & Pricing

### Commercial License

Launcher Assistant is **commercial software** that requires a paid license for production use. It follows a licensing model similar to Craft CMS itself.

**Purchase a license at:** [brilliancenw.com](https://www.brilliancenw.com/)

### Free Dependency

This plugin requires **Craft Launcher**, which is completely **FREE and open source** (GPL-3.0) and will always remain free. You can use Launcher without the Assistant at no cost.

### License Terms

- One license per Craft CMS installation
- License includes updates and support
- Development/staging environments covered under production license
- See [LICENSE.md](LICENSE.md) for full terms

### Cost Considerations

In addition to the plugin license, you'll need an API key from Claude (Anthropic):
- Claude API uses pay-as-you-go pricing
- Typical cost: $3-15/month depending on usage
- Visit [anthropic.com/pricing](https://www.anthropic.com/pricing) for current rates

## Support

### Documentation
- Plugin Documentation: https://github.com/brilliancenw/craft-launcher-assistant
- Craft Launcher Docs: https://github.com/brilliance/craft-launcher

### Issues & Questions
- Report bugs: https://github.com/brilliancenw/craft-launcher-assistant/issues
- Email support: info@brilliancenw.com
- Website: https://www.brilliancenw.com/

### Getting Help

For the best support experience:
1. Check existing documentation first
2. Search closed issues on GitHub
3. Provide detailed information when reporting issues (Craft version, PHP version, error messages)
4. Include steps to reproduce any bugs

## Credits

Developed by [Brilliance](https://www.brilliancenw.com/) - Craft CMS experts.

---

**Remember:** While Launcher Assistant is commercial software, the **Craft Launcher** plugin it depends on is and always will be **completely free**!
