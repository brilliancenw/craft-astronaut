<?php

namespace brilliance\launcherassistant\migrations;

use Craft;
use craft\db\Migration;

/**
 * Install migration
 *
 * This migration is run when the plugin is installed and uninstalled.
 * The safeDown() method is called during uninstall to clean up all tables.
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createAIConversationTables();
        $this->createAISettingsTable();
        $this->addClaudeModelColumn();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        // Drop tables in reverse order (messages first due to foreign key)
        $this->dropTableIfExists('{{%launcher_ai_messages}}');
        $this->dropTableIfExists('{{%launcher_ai_conversations}}');
        $this->dropTableIfExists('{{%launcher_ai_settings}}');

        return true;
    }

    /**
     * Create AI conversation tables
     */
    private function createAIConversationTables(): void
    {
        // Create conversations table
        if ($this->db->schema->getTableSchema('{{%launcher_ai_conversations}}') === null) {
            $this->createTable('{{%launcher_ai_conversations}}', [
                'id' => $this->primaryKey(),
                'userId' => $this->integer()->notNull()->comment('User who owns this conversation'),
                'threadId' => $this->string(255)->notNull()->comment('Unique thread identifier'),
                'provider' => $this->string(50)->notNull()->comment('AI provider (claude, openai, gemini)'),
                'title' => $this->string(255)->null()->comment('Conversation title'),
                'lastMessageAt' => $this->dateTime()->notNull()->comment('Timestamp of last message'),
                'messageCount' => $this->integer()->notNull()->defaultValue(0)->comment('Total messages in conversation'),
                'metadata' => $this->json()->null()->comment('Additional conversation metadata'),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
            ]);

            // Create indexes for conversations
            $this->createIndex(null, '{{%launcher_ai_conversations}}', ['userId'], false);
            $this->createIndex(null, '{{%launcher_ai_conversations}}', ['threadId'], true);
            $this->createIndex(null, '{{%launcher_ai_conversations}}', ['userId', 'lastMessageAt'], false);

            // Add foreign key for userId
            $this->addForeignKey(
                null,
                '{{%launcher_ai_conversations}}',
                ['userId'],
                '{{%users}}',
                ['id'],
                'CASCADE',
                null
            );
        }

        // Create messages table
        if ($this->db->schema->getTableSchema('{{%launcher_ai_messages}}') === null) {
            $this->createTable('{{%launcher_ai_messages}}', [
                'id' => $this->primaryKey(),
                'conversationId' => $this->integer()->notNull()->comment('Reference to conversation'),
                'role' => $this->string(50)->notNull()->comment('Message role (user, assistant, system, tool)'),
                'content' => $this->mediumText()->null()->comment('Message content'),
                'toolCalls' => $this->json()->null()->comment('Tool/function calls made by assistant'),
                'toolResults' => $this->json()->null()->comment('Results from tool executions'),
                'metadata' => $this->json()->null()->comment('Additional message metadata'),
                'dateCreated' => $this->dateTime()->notNull(),
            ]);

            // Create indexes for messages
            $this->createIndex(null, '{{%launcher_ai_messages}}', ['conversationId'], false);
            $this->createIndex(null, '{{%launcher_ai_messages}}', ['conversationId', 'dateCreated'], false);

            // Add foreign key for conversationId
            $this->addForeignKey(
                null,
                '{{%launcher_ai_messages}}',
                ['conversationId'],
                '{{%launcher_ai_conversations}}',
                ['id'],
                'CASCADE',
                null
            );
        }
    }

    /**
     * Create AI settings table
     */
    private function createAISettingsTable(): void
    {
        if ($this->db->schema->getTableSchema('{{%launcher_ai_settings}}') === null) {
            $this->createTable('{{%launcher_ai_settings}}', [
                'id' => $this->primaryKey(),

                // AI Provider Configuration
                'aiProvider' => $this->string(50)->defaultValue('claude')->comment('Active AI provider (claude, openai, gemini)'),
                'claudeApiKey' => $this->text()->null()->comment('Claude API key (encrypted)'),
                'openaiApiKey' => $this->text()->null()->comment('OpenAI API key (encrypted)'),
                'geminiApiKey' => $this->text()->null()->comment('Gemini API key (encrypted)'),
                'claudeModel' => $this->string(100)->null()->comment('Claude model to use'),

                // Brand Information
                'websiteName' => $this->string(255)->null()->comment('Website/brand name'),
                'brandOwner' => $this->string(255)->null()->comment('Brand owner/company name'),
                'brandTagline' => $this->string(255)->null()->comment('Brand tagline'),
                'brandDescription' => $this->text()->null()->comment('Brand description'),
                'brandVoice' => $this->text()->null()->comment('Brand voice guidelines'),
                'targetAudience' => $this->text()->null()->comment('Target audience description'),
                'brandColors' => $this->json()->null()->comment('Brand colors array'),
                'brandLogoUrl' => $this->string(500)->null()->comment('Brand logo URL'),

                // Content Guidelines
                'contentGuidelines' => $this->text()->null()->comment('General content guidelines'),
                'contentTone' => $this->text()->null()->comment('Content tone guidelines'),
                'writingStyle' => $this->text()->null()->comment('Writing style guidelines'),
                'seoGuidelines' => $this->text()->null()->comment('SEO guidelines'),
                'customGuidelines' => $this->json()->null()->comment('Custom guidelines array'),

                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            // Insert default row
            $this->insert('{{%launcher_ai_settings}}', [
                'aiProvider' => 'claude',
                'claudeModel' => 'claude-sonnet-4-20250514',
                'dateCreated' => date('Y-m-d H:i:s'),
                'dateUpdated' => date('Y-m-d H:i:s'),
                'uid' => \craft\helpers\StringHelper::UUID(),
            ]);
        }
    }

    /**
     * Add Claude model column if it doesn't exist
     * (for cases where settings table exists but column is missing)
     */
    private function addClaudeModelColumn(): void
    {
        if ($this->db->schema->getTableSchema('{{%launcher_ai_settings}}') !== null) {
            if (!$this->db->columnExists('{{%launcher_ai_settings}}', 'claudeModel')) {
                $this->addColumn(
                    '{{%launcher_ai_settings}}',
                    'claudeModel',
                    $this->string(100)->null()->after('geminiApiKey')
                );

                // Update existing row with default value
                $this->update(
                    '{{%launcher_ai_settings}}',
                    ['claudeModel' => 'claude-sonnet-4-20250514'],
                    ['claudeModel' => null]
                );
            }
        }
    }
}
