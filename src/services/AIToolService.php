<?php

namespace brilliance\launcherassistant\services;

use brilliance\launcher\Launcher;
use Craft;
use craft\base\Component;
use craft\elements\Entry;
use craft\helpers\Json;

/**
 * AI Tool Service
 *
 * Provides tools/functions that AI agents can call to interact with Craft CMS.
 * Leverages existing SearchService and Craft APIs.
 */
class AIToolService extends Component
{
    /**
     * Get tool definitions for AI providers
     * Returns array of tool definitions in a provider-agnostic format
     */
    public function getToolDefinitions(): array
    {
        return [
            [
                'name' => 'listSections',
                'description' => 'Get a list of all available content sections/channels in Craft CMS. Returns basic info (name, handle, type) without field details.',
                'parameters' => [],
            ],
            [
                'name' => 'getSectionDetails',
                'description' => 'Get detailed information about a specific section including all fields, requirements, and configuration. Use this when you need to know what fields are available for creating content.',
                'parameters' => [
                    'handle' => [
                        'type' => 'string',
                        'description' => 'The section handle (e.g., "blog", "news", "products")',
                        'required' => true,
                    ],
                ],
            ],
            [
                'name' => 'searchEntries',
                'description' => 'Search for existing entries across all sections or within a specific section. Useful for finding similar content or checking if something already exists.',
                'parameters' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Search query',
                        'required' => true,
                    ],
                    'section' => [
                        'type' => 'string',
                        'description' => 'Optional section handle to limit search',
                        'required' => false,
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of results (default: 10)',
                        'required' => false,
                    ],
                ],
            ],
            [
                'name' => 'getFieldDetails',
                'description' => 'Get detailed information about a specific field including type, settings, and validation rules.',
                'parameters' => [
                    'handle' => [
                        'type' => 'string',
                        'description' => 'The field handle',
                        'required' => true,
                    ],
                ],
            ],
            [
                'name' => 'createDraftEntry',
                'description' => 'Create a new draft entry with the provided content. The draft will need to be reviewed and published by the user.',
                'parameters' => [
                    'sectionHandle' => [
                        'type' => 'string',
                        'description' => 'Section handle where entry should be created',
                        'required' => true,
                    ],
                    'title' => [
                        'type' => 'string',
                        'description' => 'Entry title',
                        'required' => true,
                    ],
                    'fields' => [
                        'type' => 'object',
                        'description' => 'Field values as key-value pairs (fieldHandle => value)',
                        'required' => false,
                    ],
                ],
            ],
            [
                'name' => 'listFields',
                'description' => 'Get a list of all available fields in Craft CMS with basic information.',
                'parameters' => [],
            ],
            [
                'name' => 'listCategoryGroups',
                'description' => 'Get a list of all category groups (used for organizing content with tags, topics, etc.).',
                'parameters' => [],
            ],
            [
                'name' => 'getCategoryGroupDetails',
                'description' => 'Get detailed information about a specific category group including fields and available categories.',
                'parameters' => [
                    'handle' => [
                        'type' => 'string',
                        'description' => 'The category group handle (e.g., "topics", "tags", "blogCategories")',
                        'required' => true,
                    ],
                ],
            ],
            [
                'name' => 'searchCategories',
                'description' => 'Search for categories/tags to use when creating content.',
                'parameters' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Search query',
                        'required' => true,
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of results (default: 10)',
                        'required' => false,
                    ],
                ],
            ],
            [
                'name' => 'listAssetVolumes',
                'description' => 'Get a list of all asset volumes (media libraries for images, documents, videos, etc.).',
                'parameters' => [],
            ],
            [
                'name' => 'searchAssets',
                'description' => 'Search for assets/media files (images, documents, videos, etc.).',
                'parameters' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Search query',
                        'required' => true,
                    ],
                    'volume' => [
                        'type' => 'string',
                        'description' => 'Optional volume handle to limit search',
                        'required' => false,
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of results (default: 10)',
                        'required' => false,
                    ],
                ],
            ],
            [
                'name' => 'listGlobals',
                'description' => 'Get a list of all global sets (site-wide content like contact info, social media, footer content, etc.).',
                'parameters' => [],
            ],
            [
                'name' => 'getGlobalDetails',
                'description' => 'Get detailed information about a specific global set including all fields and current values.',
                'parameters' => [
                    'handle' => [
                        'type' => 'string',
                        'description' => 'The global set handle (e.g., "siteInfo", "footer", "contactInfo")',
                        'required' => true,
                    ],
                ],
            ],
            [
                'name' => 'searchGlobals',
                'description' => 'Search within global set content.',
                'parameters' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Search query',
                        'required' => true,
                    ],
                ],
            ],
            [
                'name' => 'listUtilities',
                'description' => 'Get a list of all available Craft CMS utilities (admin tools like cache clearing, backups, etc.).',
                'parameters' => [],
            ],
            [
                'name' => 'clearCaches',
                'description' => 'Clear all Craft CMS caches. Use this when the user asks to clear/flush/refresh caches.',
                'parameters' => [],
            ],
            [
                'name' => 'rebuildAssetIndexes',
                'description' => 'Rebuild asset indexes to sync the database with files on disk. Use when assets are missing or out of sync.',
                'parameters' => [],
            ],
            [
                'name' => 'getQueueStatus',
                'description' => 'Get the status of background queue jobs (pending, running, failed jobs).',
                'parameters' => [],
            ],
            [
                'name' => 'runQueueJobs',
                'description' => 'Process pending queue jobs in the background. Use when jobs are stuck or need to be processed.',
                'parameters' => [
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of jobs to process (default: 10)',
                        'required' => false,
                    ],
                ],
            ],
            [
                'name' => 'getSystemInfo',
                'description' => 'Get system information and diagnostics (PHP version, database, Craft version, environment, etc.).',
                'parameters' => [],
            ],
            [
                'name' => 'getCommerceStatus',
                'description' => 'Check if Craft Commerce is installed and get available product types.',
                'parameters' => [],
            ],
            [
                'name' => 'listFieldTypes',
                'description' => 'Get a list of all available field types in Craft CMS with descriptions of when to use each type.',
                'parameters' => [],
            ],
            [
                'name' => 'getSectionTypeInfo',
                'description' => 'Get detailed information about Craft section types (Single, Channel, Structure) and when to use each one.',
                'parameters' => [],
            ],
            [
                'name' => 'createSection',
                'description' => 'Create a new content section in Craft CMS. A section is a container for entries. Choose type based on use case: "single" for one-off pages (About, Homepage), "channel" for streams of similar content (Blog, News), "structure" for hierarchical content (Documentation, Navigation).',
                'parameters' => [
                    'name' => [
                        'type' => 'string',
                        'description' => 'Human-readable section name (e.g., "Blog Posts")',
                        'required' => true,
                    ],
                    'handle' => [
                        'type' => 'string',
                        'description' => 'Unique identifier in camelCase or under_score (e.g., "blogPosts")',
                        'required' => true,
                    ],
                    'type' => [
                        'type' => 'string',
                        'description' => 'Section type: "single", "channel", or "structure"',
                        'required' => true,
                    ],
                    'enableVersioning' => [
                        'type' => 'boolean',
                        'description' => 'Enable entry versioning (default: true)',
                        'required' => false,
                    ],
                ],
            ],
            [
                'name' => 'createEntryType',
                'description' => 'Create a new entry type for a section. Entry types define different templates and field layouts within a section. Most sections have one entry type, but you can have multiple (e.g., "Article" and "Video" entry types in a "News" section).',
                'parameters' => [
                    'sectionHandle' => [
                        'type' => 'string',
                        'description' => 'Handle of the section to add this entry type to',
                        'required' => true,
                    ],
                    'name' => [
                        'type' => 'string',
                        'description' => 'Human-readable entry type name (e.g., "Article")',
                        'required' => true,
                    ],
                    'handle' => [
                        'type' => 'string',
                        'description' => 'Unique identifier in camelCase (e.g., "article")',
                        'required' => true,
                    ],
                ],
            ],
            [
                'name' => 'createField',
                'description' => 'Create a new custom field that can be added to entry types, categories, users, etc. Start with "Plain Text" for simple text fields. The field will be created globally and can then be added to any field layout.',
                'parameters' => [
                    'name' => [
                        'type' => 'string',
                        'description' => 'Human-readable field name (e.g., "Blog Content")',
                        'required' => true,
                    ],
                    'handle' => [
                        'type' => 'string',
                        'description' => 'Unique identifier in camelCase (e.g., "blogContent")',
                        'required' => true,
                    ],
                    'type' => [
                        'type' => 'string',
                        'description' => 'Field type class name (use listFieldTypes to see options). Common: "craft\\fields\\PlainText" for text',
                        'required' => true,
                    ],
                    'instructions' => [
                        'type' => 'string',
                        'description' => 'Help text shown to content editors',
                        'required' => false,
                    ],
                    'required' => [
                        'type' => 'boolean',
                        'description' => 'Whether this field is required (default: false)',
                        'required' => false,
                    ],
                ],
            ],
            [
                'name' => 'addFieldToEntryType',
                'description' => 'Add an existing field to an entry type\'s field layout. This makes the field available when creating entries of this type.',
                'parameters' => [
                    'entryTypeHandle' => [
                        'type' => 'string',
                        'description' => 'Handle of the entry type',
                        'required' => true,
                    ],
                    'fieldHandle' => [
                        'type' => 'string',
                        'description' => 'Handle of the field to add',
                        'required' => true,
                    ],
                    'tabName' => [
                        'type' => 'string',
                        'description' => 'Name of the tab to add field to (default: "Content")',
                        'required' => false,
                    ],
                    'required' => [
                        'type' => 'boolean',
                        'description' => 'Make this field required in this layout (default: false)',
                        'required' => false,
                    ],
                ],
            ],
        ];
    }

    /**
     * Execute a tool/function call
     */
    public function executeTool(string $toolName, array $parameters): array
    {
        try {
            $result = match ($toolName) {
                'listSections' => $this->listSections(),
                'getSectionDetails' => $this->getSectionDetails($parameters['handle'] ?? ''),
                'searchEntries' => $this->searchEntries(
                    $parameters['query'] ?? '',
                    $parameters['section'] ?? null,
                    $parameters['limit'] ?? 10
                ),
                'getFieldDetails' => $this->getFieldDetails($parameters['handle'] ?? ''),
                'createDraftEntry' => $this->createDraftEntry(
                    $parameters['sectionHandle'] ?? '',
                    $parameters['title'] ?? '',
                    $parameters['fields'] ?? []
                ),
                'listFields' => $this->listFields(),
                'listCategoryGroups' => $this->listCategoryGroups(),
                'getCategoryGroupDetails' => $this->getCategoryGroupDetails($parameters['handle'] ?? ''),
                'searchCategories' => $this->searchCategories(
                    $parameters['query'] ?? '',
                    $parameters['limit'] ?? 10
                ),
                'listAssetVolumes' => $this->listAssetVolumes(),
                'searchAssets' => $this->searchAssets(
                    $parameters['query'] ?? '',
                    $parameters['volume'] ?? null,
                    $parameters['limit'] ?? 10
                ),
                'listGlobals' => $this->listGlobals(),
                'getGlobalDetails' => $this->getGlobalDetails($parameters['handle'] ?? ''),
                'searchGlobals' => $this->searchGlobals($parameters['query'] ?? ''),
                'listUtilities' => $this->listUtilities(),
                'clearCaches' => $this->clearCaches(),
                'rebuildAssetIndexes' => $this->rebuildAssetIndexes(),
                'getQueueStatus' => $this->getQueueStatus(),
                'runQueueJobs' => $this->runQueueJobs($parameters['limit'] ?? 10),
                'getSystemInfo' => $this->getSystemInfo(),
                'getCommerceStatus' => $this->getCommerceStatus(),
                'listFieldTypes' => $this->listFieldTypes(),
                'getSectionTypeInfo' => $this->getSectionTypeInfo(),
                'createSection' => $this->createSection(
                    $parameters['name'] ?? '',
                    $parameters['handle'] ?? '',
                    $parameters['type'] ?? '',
                    $parameters['enableVersioning'] ?? true
                ),
                'createEntryType' => $this->createEntryType(
                    $parameters['sectionHandle'] ?? '',
                    $parameters['name'] ?? '',
                    $parameters['handle'] ?? ''
                ),
                'createField' => $this->createField(
                    $parameters['name'] ?? '',
                    $parameters['handle'] ?? '',
                    $parameters['type'] ?? '',
                    $parameters['instructions'] ?? '',
                    $parameters['required'] ?? false
                ),
                'addFieldToEntryType' => $this->addFieldToEntryType(
                    $parameters['entryTypeHandle'] ?? '',
                    $parameters['fieldHandle'] ?? '',
                    $parameters['tabName'] ?? 'Content',
                    $parameters['required'] ?? false
                ),
                default => ['error' => "Unknown tool: {$toolName}"],
            };

            // Ensure result is JSON-serializable
            // Convert any objects to arrays recursively
            return $this->ensureJsonSerializable($result);

        } catch (\Exception $e) {
            Craft::error("AI Tool execution error [{$toolName}]: " . $e->getMessage(), __METHOD__);
            return [
                'error' => $e->getMessage(),
                'tool' => $toolName,
            ];
        }
    }

    /**
     * Ensure data is JSON-serializable by converting objects to arrays
     */
    private function ensureJsonSerializable($data)
    {
        if (is_object($data)) {
            // Convert object to array
            $data = get_object_vars($data);
        }

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->ensureJsonSerializable($value);
            }
        }

        return $data;
    }

    /**
     * List all sections
     * Leverages existing SearchService browse functionality
     */
    private function listSections(): array
    {
        $searchService = Launcher::$plugin->search;
        $browseResults = $searchService->browseContentType('sections');

        $sections = $browseResults['sections'] ?? [];
        $result = [];

        foreach ($sections as $section) {
            $result[] = [
                'name' => $section['title'],
                'handle' => $section['handle'],
                'type' => $section['type'] ?? 'section',
                'url' => $section['url'],
            ];
        }

        return [
            'sections' => $result,
            'totalCount' => count($result),
        ];
    }

    /**
     * Get detailed information about a section
     */
    private function getSectionDetails(string $handle): array
    {
        if (empty($handle)) {
            return ['error' => 'Section handle is required'];
        }

        $section = Craft::$app->getEntries()->getSectionByHandle($handle);
        if (!$section) {
            return ['error' => "Section not found: {$handle}"];
        }

        $entryTypes = $section->getEntryTypes();
        $entryTypeDetails = [];

        foreach ($entryTypes as $entryType) {
            $fieldLayout = $entryType->getFieldLayout();
            $fields = [];

            if ($fieldLayout) {
                foreach ($fieldLayout->getCustomFields() as $field) {
                    $fields[] = [
                        'name' => $field->name,
                        'handle' => $field->handle,
                        'type' => get_class($field),
                        'required' => $field->required ?? false,
                        'instructions' => $field->instructions ?? '',
                    ];
                }
            }

            $entryTypeDetails[] = [
                'name' => $entryType->name,
                'handle' => $entryType->handle,
                'hasTitleField' => $entryType->hasTitleField,
                'titleFormat' => $entryType->titleFormat ?? null,
                'fields' => $fields,
            ];
        }

        return [
            'section' => [
                'name' => $section->name,
                'handle' => $section->handle,
                'type' => $section->type,
                'enableVersioning' => $section->enableVersioning,
            ],
            'entryTypes' => $entryTypeDetails,
        ];
    }

    /**
     * Search entries using existing SearchService
     */
    private function searchEntries(string $query, ?string $section, int $limit = 10): array
    {
        if (empty($query)) {
            return ['error' => 'Search query is required'];
        }

        // Leverage existing SearchService
        $searchService = Launcher::$plugin->search;
        $results = $searchService->search($query);

        // Filter and format results
        $entries = [];
        $count = 0;

        if (isset($results['entries'])) {
            foreach ($results['entries'] as $entry) {
                if ($count >= $limit) {
                    break;
                }

                // Filter by section if specified
                if ($section && isset($entry['sectionHandle']) && $entry['sectionHandle'] !== $section) {
                    continue;
                }

                $entries[] = [
                    'id' => $entry['id'] ?? null,
                    'title' => $entry['title'] ?? '',
                    'url' => $entry['url'] ?? '',
                    'section' => $entry['section'] ?? '',
                    'sectionHandle' => $entry['sectionHandle'] ?? '',
                    'status' => $entry['status'] ?? '',
                ];

                $count++;
            }
        }

        return [
            'entries' => $entries,
            'count' => count($entries),
            'query' => $query,
        ];
    }

    /**
     * Get field details
     */
    private function getFieldDetails(string $handle): array
    {
        if (empty($handle)) {
            return ['error' => 'Field handle is required'];
        }

        $field = Craft::$app->fields->getFieldByHandle($handle);
        if (!$field) {
            return ['error' => "Field not found: {$handle}"];
        }

        return [
            'name' => $field->name,
            'handle' => $field->handle,
            'type' => get_class($field),
            'instructions' => $field->instructions ?? '',
            'required' => $field->required ?? false,
            'settings' => $field->getSettings(),
        ];
    }

    /**
     * List all fields
     */
    private function listFields(): array
    {
        $fields = Craft::$app->fields->getAllFields();
        $result = [];

        foreach ($fields as $field) {
            $result[] = [
                'name' => $field->name,
                'handle' => $field->handle,
                'type' => get_class($field),
            ];
        }

        return [
            'fields' => $result,
            'totalCount' => count($result),
        ];
    }

    /**
     * Create a draft entry
     */
    private function createDraftEntry(string $sectionHandle, string $title, array $fields = []): array
    {
        if (empty($sectionHandle)) {
            return ['error' => 'Section handle is required'];
        }

        if (empty($title)) {
            return ['error' => 'Title is required'];
        }

        $section = Craft::$app->getEntries()->getSectionByHandle($sectionHandle);
        if (!$section) {
            return ['error' => "Section not found: {$sectionHandle}"];
        }

        $entryTypes = $section->getEntryTypes();
        if (empty($entryTypes)) {
            return ['error' => "No entry types found for section: {$sectionHandle}"];
        }

        // Use the first entry type
        $entryType = $entryTypes[0];

        // Create the entry
        $entry = new Entry();
        $entry->sectionId = $section->id;
        $entry->typeId = $entryType->id;
        $entry->title = $title;
        $entry->setFieldValues($fields);

        // Save as draft
        $entry->setScenario(Entry::SCENARIO_ESSENTIALS);

        if (!Craft::$app->drafts->saveElementAsDraft($entry, Craft::$app->user->id, null, null, false)) {
            return [
                'error' => 'Failed to create draft',
                'errors' => $entry->getErrors(),
            ];
        }

        return [
            'success' => true,
            'draftId' => $entry->draftId,
            'entryId' => $entry->id,
            'title' => $entry->title,
            'cpEditUrl' => $entry->getCpEditUrl(),
            'message' => 'Draft created successfully. You can review and publish it in the Craft control panel.',
        ];
    }

    /**
     * Get Commerce status
     */
    private function getCommerceStatus(): array
    {
        $isInstalled = Craft::$app->plugins->isPluginInstalled('commerce');

        if (!$isInstalled) {
            return [
                'installed' => false,
                'message' => 'Craft Commerce is not installed',
            ];
        }

        $productTypes = [];

        if (class_exists('craft\commerce\Plugin')) {
            $commerce = \craft\commerce\Plugin::getInstance();
            foreach ($commerce->getProductTypes()->getAllProductTypes() as $productType) {
                $productTypes[] = [
                    'name' => $productType->name,
                    'handle' => $productType->handle,
                ];
            }
        }

        return [
            'installed' => true,
            'productTypes' => $productTypes,
        ];
    }

    /**
     * List all category groups
     */
    private function listCategoryGroups(): array
    {
        $groups = Craft::$app->categories->getAllGroups();
        $result = [];

        foreach ($groups as $group) {
            $result[] = [
                'name' => $group->name,
                'handle' => $group->handle,
                'maxLevels' => $group->maxLevels,
            ];
        }

        return [
            'categoryGroups' => $result,
            'totalCount' => count($result),
        ];
    }

    /**
     * Get category group details
     */
    private function getCategoryGroupDetails(string $handle): array
    {
        if (empty($handle)) {
            return ['error' => 'Category group handle is required'];
        }

        $group = Craft::$app->categories->getGroupByHandle($handle);
        if (!$group) {
            return ['error' => "Category group not found: {$handle}"];
        }

        $fieldLayout = $group->getFieldLayout();
        $fields = [];

        if ($fieldLayout) {
            foreach ($fieldLayout->getCustomFields() as $field) {
                $fields[] = [
                    'name' => $field->name,
                    'handle' => $field->handle,
                    'type' => get_class($field),
                    'required' => $field->required ?? false,
                ];
            }
        }

        // Get some example categories
        $categories = \craft\elements\Category::find()
            ->groupId($group->id)
            ->limit(10)
            ->all();

        $exampleCategories = [];
        foreach ($categories as $category) {
            $exampleCategories[] = [
                'id' => $category->id,
                'title' => $category->title,
                'level' => $category->level,
            ];
        }

        return [
            'group' => [
                'name' => $group->name,
                'handle' => $group->handle,
                'maxLevels' => $group->maxLevels,
            ],
            'fields' => $fields,
            'exampleCategories' => $exampleCategories,
        ];
    }

    /**
     * Search categories
     */
    private function searchCategories(string $query, int $limit = 10): array
    {
        if (empty($query)) {
            return ['error' => 'Search query is required'];
        }

        $searchService = Launcher::$plugin->search;
        $results = $searchService->search($query);

        $categories = [];
        $count = 0;

        if (isset($results['categories'])) {
            foreach ($results['categories'] as $category) {
                if ($count >= $limit) {
                    break;
                }

                $categories[] = [
                    'id' => $category['id'] ?? null,
                    'title' => $category['title'] ?? '',
                    'group' => $category['group'] ?? '',
                    'groupHandle' => $category['groupHandle'] ?? '',
                ];

                $count++;
            }
        }

        return [
            'categories' => $categories,
            'count' => count($categories),
            'query' => $query,
        ];
    }

    /**
     * List all asset volumes
     */
    private function listAssetVolumes(): array
    {
        $volumes = Craft::$app->volumes->getAllVolumes();
        $result = [];

        foreach ($volumes as $volume) {
            $result[] = [
                'name' => $volume->name,
                'handle' => $volume->handle,
                'hasUrls' => $volume->hasUrls,
            ];
        }

        return [
            'volumes' => $result,
            'totalCount' => count($result),
        ];
    }

    /**
     * Search assets
     */
    private function searchAssets(string $query, ?string $volume, int $limit = 10): array
    {
        if (empty($query)) {
            return ['error' => 'Search query is required'];
        }

        $searchService = Launcher::$plugin->search;
        $results = $searchService->search($query);

        $assets = [];
        $count = 0;

        if (isset($results['assets'])) {
            foreach ($results['assets'] as $asset) {
                if ($count >= $limit) {
                    break;
                }

                // Filter by volume if specified
                if ($volume && isset($asset['volumeHandle']) && $asset['volumeHandle'] !== $volume) {
                    continue;
                }

                $assets[] = [
                    'id' => $asset['id'] ?? null,
                    'title' => $asset['title'] ?? '',
                    'filename' => $asset['filename'] ?? '',
                    'url' => $asset['url'] ?? '',
                    'volume' => $asset['volume'] ?? '',
                    'volumeHandle' => $asset['volumeHandle'] ?? '',
                    'kind' => $asset['kind'] ?? '',
                ];

                $count++;
            }
        }

        return [
            'assets' => $assets,
            'count' => count($assets),
            'query' => $query,
        ];
    }

    /**
     * List all global sets
     */
    private function listGlobals(): array
    {
        $globals = Craft::$app->globals->getAllSets();
        $result = [];

        foreach ($globals as $global) {
            $result[] = [
                'name' => $global->name,
                'handle' => $global->handle,
            ];
        }

        return [
            'globals' => $result,
            'totalCount' => count($result),
        ];
    }

    /**
     * Get global set details
     */
    private function getGlobalDetails(string $handle): array
    {
        if (empty($handle)) {
            return ['error' => 'Global set handle is required'];
        }

        $globalSet = Craft::$app->globals->getSetByHandle($handle);
        if (!$globalSet) {
            return ['error' => "Global set not found: {$handle}"];
        }

        $fieldLayout = $globalSet->getFieldLayout();
        $fields = [];
        $currentValues = [];

        if ($fieldLayout) {
            foreach ($fieldLayout->getCustomFields() as $field) {
                $fields[] = [
                    'name' => $field->name,
                    'handle' => $field->handle,
                    'type' => get_class($field),
                    'instructions' => $field->instructions ?? '',
                ];

                // Get current field value
                $value = $globalSet->getFieldValue($field->handle);
                $currentValues[$field->handle] = $this->formatFieldValue($value);
            }
        }

        return [
            'global' => [
                'name' => $globalSet->name,
                'handle' => $globalSet->handle,
            ],
            'fields' => $fields,
            'currentValues' => $currentValues,
        ];
    }

    /**
     * Search globals
     */
    private function searchGlobals(string $query): array
    {
        if (empty($query)) {
            return ['error' => 'Search query is required'];
        }

        $searchService = Launcher::$plugin->search;
        $results = $searchService->search($query);

        $globals = [];

        if (isset($results['globals'])) {
            foreach ($results['globals'] as $global) {
                $globals[] = [
                    'name' => $global['name'] ?? '',
                    'handle' => $global['handle'] ?? '',
                    'url' => $global['url'] ?? '',
                ];
            }
        }

        return [
            'globals' => $globals,
            'count' => count($globals),
            'query' => $query,
        ];
    }

    /**
     * Format field value for display
     */
    private function formatFieldValue(mixed $value): mixed
    {
        // Handle different field types
        if (is_object($value)) {
            if ($value instanceof \craft\elements\db\ElementQuery) {
                return 'Related elements (query)';
            }
            if (method_exists($value, '__toString')) {
                return (string)$value;
            }
            return get_class($value);
        }

        return $value;
    }

    /**
     * List all available utilities
     */
    private function listUtilities(): array
    {
        $searchService = Launcher::$plugin->search;

        // Use the existing getAllUtilities method
        $utilities = $searchService->browseContentType('utilities');

        if (isset($utilities['utilities'])) {
            return [
                'utilities' => $utilities['utilities'],
                'totalCount' => count($utilities['utilities']),
            ];
        }

        return [
            'utilities' => [],
            'totalCount' => 0,
        ];
    }

    /**
     * Clear all Craft caches
     */
    private function clearCaches(): array
    {
        try {
            // Clear Craft's internal caches
            Craft::$app->cache->flush();

            // Clear template caches
            Craft::$app->templateCaches->deleteAllCaches();

            // Clear data caches
            Craft::$app->getCache()->flush();

            // Clear asset transform indexes
            Craft::$app->assetTransforms->deleteAllTransformIndexes();

            // Clear compiled templates using Craft's FileHelper
            $compiledTemplatesPath = Craft::$app->path->getCompiledTemplatesPath();
            if (is_dir($compiledTemplatesPath)) {
                \craft\helpers\FileHelper::clearDirectory($compiledTemplatesPath);
            }

            Craft::info('Caches cleared via Astronaut', __METHOD__);

            return [
                'success' => true,
                'message' => 'All caches have been cleared successfully.',
                'cachesCleared' => [
                    'data',
                    'compiled templates',
                    'template caches',
                    'asset transforms',
                ],
            ];
        } catch (\Exception $e) {
            Craft::error('Failed to clear caches: ' . $e->getMessage(), __METHOD__);
            return [
                'success' => false,
                'error' => 'Failed to clear caches: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Rebuild asset indexes
     */
    private function rebuildAssetIndexes(): array
    {
        try {
            // Get all volumes
            $volumes = Craft::$app->volumes->getAllVolumes();

            if (empty($volumes)) {
                return [
                    'success' => true,
                    'message' => 'No asset volumes to index.',
                ];
            }

            $volumeIds = [];
            foreach ($volumes as $volume) {
                $volumeIds[] = $volume->id;
            }

            // Queue the asset indexing job
            Craft::$app->queue->push(new \craft\queue\jobs\UpdateAssetIndexes([
                'volumeIds' => $volumeIds,
            ]));

            return [
                'success' => true,
                'message' => 'Asset index rebuild has been queued. This will run in the background.',
                'volumesQueued' => count($volumeIds),
            ];
        } catch (\Exception $e) {
            Craft::error('Failed to queue asset index rebuild: ' . $e->getMessage(), __METHOD__);
            return [
                'success' => false,
                'error' => 'Failed to queue asset index rebuild: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get queue status
     */
    private function getQueueStatus(): array
    {
        try {
            $queue = Craft::$app->queue;

            // Get queue info using reflection if needed, or just count jobs
            $db = Craft::$app->getDb();

            $totalJobs = (int)$db->createCommand()
                ->from('{{%queue}}')
                ->count()
                ->queryScalar();

            $waitingJobs = (int)$db->createCommand()
                ->from('{{%queue}}')
                ->where(['timePushed' => null])
                ->orWhere(['and', ['not', ['timePushed' => null]], ['timeStarted' => null]])
                ->count()
                ->queryScalar();

            $failedJobs = (int)$db->createCommand()
                ->from('{{%queue}}')
                ->where(['not', ['fail' => null]])
                ->count()
                ->queryScalar();

            $runningJobs = (int)$db->createCommand()
                ->from('{{%queue}}')
                ->where(['not', ['timeStarted' => null]])
                ->andWhere(['timeDone' => null])
                ->count()
                ->queryScalar();

            return [
                'success' => true,
                'queue' => [
                    'totalJobs' => $totalJobs,
                    'waiting' => $waitingJobs,
                    'running' => $runningJobs,
                    'failed' => $failedJobs,
                ],
            ];
        } catch (\Exception $e) {
            Craft::error('Failed to get queue status: ' . $e->getMessage(), __METHOD__);
            return [
                'success' => false,
                'error' => 'Failed to get queue status: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Run queue jobs
     */
    private function runQueueJobs(int $limit = 10): array
    {
        try {
            $queue = Craft::$app->queue;
            $processed = 0;

            // Run queue jobs
            for ($i = 0; $i < $limit; $i++) {
                if ($queue->run()) {
                    $processed++;
                } else {
                    break;
                }
            }

            return [
                'success' => true,
                'message' => "Processed {$processed} queue job(s).",
                'jobsProcessed' => $processed,
            ];
        } catch (\Exception $e) {
            Craft::error('Failed to run queue jobs: ' . $e->getMessage(), __METHOD__);
            return [
                'success' => false,
                'error' => 'Failed to run queue jobs: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get system information
     */
    private function getSystemInfo(): array
    {
        try {
            $info = Craft::$app->getInfo();

            return [
                'success' => true,
                'system' => [
                    'craftVersion' => Craft::$app->version,
                    'craftEdition' => Craft::$app->getEditionName(),
                    'phpVersion' => PHP_VERSION,
                    'databaseDriver' => Craft::$app->db->driverName,
                    'databaseVersion' => Craft::$app->db->getServerVersion(),
                    'environment' => Craft::$app->env,
                    'devMode' => Craft::$app->config->general->devMode,
                    'timezone' => Craft::$app->getTimeZone(),
                    'language' => Craft::$app->language,
                    'isMultiSite' => Craft::$app->getIsMultiSite(),
                ],
            ];
        } catch (\Exception $e) {
            Craft::error('Failed to get system info: ' . $e->getMessage(), __METHOD__);
            return [
                'success' => false,
                'error' => 'Failed to get system info: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * List all available field types with descriptions
     */
    private function listFieldTypes(): array
    {
        $fieldTypes = [
            [
                'class' => 'craft\\fields\\PlainText',
                'name' => 'Plain Text',
                'description' => 'Simple text field for short text content (headlines, names, single lines)',
                'useCase' => 'Short text, names, titles, simple content',
            ],
            [
                'class' => 'craft\\fields\\Url',
                'name' => 'URL',
                'description' => 'Validated URL field',
                'useCase' => 'Website links, external URLs',
            ],
            [
                'class' => 'craft\\fields\\Email',
                'name' => 'Email',
                'description' => 'Validated email address field',
                'useCase' => 'Email addresses',
            ],
            [
                'class' => 'craft\\fields\\Number',
                'name' => 'Number',
                'description' => 'Numeric field with optional min/max validation',
                'useCase' => 'Prices, quantities, ratings',
            ],
            [
                'class' => 'craft\\fields\\Dropdown',
                'name' => 'Dropdown',
                'description' => 'Select one option from a predefined list',
                'useCase' => 'Status, category, single choice from options',
            ],
            [
                'class' => 'craft\\fields\\Checkboxes',
                'name' => 'Checkboxes',
                'description' => 'Select multiple options from a predefined list',
                'useCase' => 'Tags, features, multiple choices',
            ],
            [
                'class' => 'craft\\fields\\Lightswitch',
                'name' => 'Lightswitch',
                'description' => 'Simple on/off toggle',
                'useCase' => 'Boolean values, feature flags, yes/no',
            ],
            [
                'class' => 'craft\\fields\\Date',
                'name' => 'Date/Time',
                'description' => 'Date and time picker',
                'useCase' => 'Event dates, publication dates, deadlines',
            ],
            [
                'class' => 'craft\\fields\\Assets',
                'name' => 'Assets',
                'description' => 'Relate to uploaded files (images, videos, PDFs)',
                'useCase' => 'Images, downloads, media files',
            ],
            [
                'class' => 'craft\\fields\\Entries',
                'name' => 'Entries',
                'description' => 'Relate to other entries',
                'useCase' => 'Related articles, linked content',
            ],
            [
                'class' => 'craft\\fields\\Categories',
                'name' => 'Categories',
                'description' => 'Relate to categories',
                'useCase' => 'Organizing content by category',
            ],
            [
                'class' => 'craft\\fields\\Table',
                'name' => 'Table',
                'description' => 'Editable table with custom columns',
                'useCase' => 'Pricing tables, specifications, data grids',
            ],
            [
                'class' => 'craft\\fields\\Matrix',
                'name' => 'Matrix',
                'description' => 'Create repeating blocks of content with different layouts',
                'useCase' => 'Flexible content blocks, page builders',
            ],
            [
                'class' => 'craft\\ckeditor\\Field',
                'name' => 'CKEditor',
                'description' => 'Rich text editor for formatted content (if CKEditor plugin installed)',
                'useCase' => 'Long-form content, blog posts, formatted text',
            ],
        ];

        return [
            'fieldTypes' => $fieldTypes,
            'totalCount' => count($fieldTypes),
        ];
    }

    /**
     * Get information about section types
     */
    private function getSectionTypeInfo(): array
    {
        return [
            'sectionTypes' => [
                [
                    'type' => 'single',
                    'name' => 'Single',
                    'description' => 'For one-off pages that don\'t need multiple entries',
                    'examples' => ['Homepage', 'About Page', 'Contact Page'],
                    'characteristics' => [
                        'Only one entry per site',
                        'Good for unique pages',
                        'Cannot have multiple entries',
                    ],
                ],
                [
                    'type' => 'channel',
                    'name' => 'Channel',
                    'description' => 'For streams of similar content entries',
                    'examples' => ['Blog Posts', 'News Articles', 'Team Members'],
                    'characteristics' => [
                        'Unlimited entries',
                        'Entries are independent',
                        'Most common section type',
                        'Default sort by date',
                    ],
                ],
                [
                    'type' => 'structure',
                    'name' => 'Structure',
                    'description' => 'For hierarchical, organized content',
                    'examples' => ['Documentation', 'Navigation Menu', 'Page Tree'],
                    'characteristics' => [
                        'Entries can be nested/hierarchical',
                        'Parent-child relationships',
                        'Custom ordering',
                        'Great for navigation',
                    ],
                ],
            ],
        ];
    }

    /**
     * Create a new section
     */
    private function createSection(string $name, string $handle, string $type, bool $enableVersioning = true): array
    {
        if (empty($name) || empty($handle) || empty($type)) {
            return ['error' => 'Name, handle, and type are required'];
        }

        // Validate type
        if (!in_array($type, ['single', 'channel', 'structure'])) {
            return ['error' => 'Type must be "single", "channel", or "structure"'];
        }

        try {
            // Check if section already exists
            $existingSection = Craft::$app->sections->getSectionByHandle($handle);
            if ($existingSection) {
                return ['error' => "Section with handle '{$handle}' already exists"];
            }

            $section = new \craft\models\Section();
            $section->name = $name;
            $section->handle = $handle;
            $section->type = $type;
            $section->enableVersioning = $enableVersioning;

            // Set up site settings (required)
            $allSites = Craft::$app->sites->getAllSites();
            $siteSettings = [];

            foreach ($allSites as $site) {
                $siteSettings[$site->id] = new \craft\models\Section_SiteSettings();
                $siteSettings[$site->id]->siteId = $site->id;
                $siteSettings[$site->id]->enabledByDefault = true;
                $siteSettings[$site->id]->hasUrls = true;

                // Set URI format based on type
                if ($type === 'single') {
                    $siteSettings[$site->id]->uriFormat = $handle;
                } else {
                    $siteSettings[$site->id]->uriFormat = $handle . '/{slug}';
                }

                $siteSettings[$site->id]->template = '_' . $handle . '/entry';
            }

            $section->setSiteSettings($siteSettings);

            // Save the section
            if (!Craft::$app->sections->saveSection($section)) {
                return [
                    'error' => 'Failed to create section',
                    'errors' => $section->getErrors(),
                ];
            }

            Craft::info("Section created: {$handle}", __METHOD__);

            return [
                'success' => true,
                'message' => "Section '{$name}' created successfully",
                'section' => [
                    'id' => $section->id,
                    'name' => $section->name,
                    'handle' => $section->handle,
                    'type' => $section->type,
                ],
                'nextSteps' => [
                    'An entry type will be automatically created',
                    'You can now create fields and add them to this section\'s entry type',
                    'Use createField to create custom fields',
                    'Use addFieldToEntryType to add fields to the entry type',
                ],
            ];
        } catch (\Exception $e) {
            Craft::error('Failed to create section: ' . $e->getMessage(), __METHOD__);
            return [
                'error' => 'Failed to create section: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Create a new entry type
     */
    private function createEntryType(string $sectionHandle, string $name, string $handle): array
    {
        if (empty($sectionHandle) || empty($name) || empty($handle)) {
            return ['error' => 'Section handle, name, and handle are required'];
        }

        try {
            // Get the section
            $section = Craft::$app->sections->getSectionByHandle($sectionHandle);
            if (!$section) {
                return ['error' => "Section not found: {$sectionHandle}"];
            }

            // Check if entry type already exists
            $existingTypes = $section->getEntryTypes();
            foreach ($existingTypes as $existingType) {
                if ($existingType->handle === $handle) {
                    return ['error' => "Entry type with handle '{$handle}' already exists in this section"];
                }
            }

            $entryType = new \craft\models\EntryType();
            $entryType->sectionId = $section->id;
            $entryType->name = $name;
            $entryType->handle = $handle;
            $entryType->hasTitleField = true;

            // Create empty field layout
            $fieldLayout = new \craft\models\FieldLayout();
            $fieldLayout->type = Entry::class;
            $entryType->setFieldLayout($fieldLayout);

            // Save the entry type
            if (!Craft::$app->entries->saveEntryType($entryType)) {
                return [
                    'error' => 'Failed to create entry type',
                    'errors' => $entryType->getErrors(),
                ];
            }

            Craft::info("Entry type created: {$handle} in section {$sectionHandle}", __METHOD__);

            return [
                'success' => true,
                'message' => "Entry type '{$name}' created successfully",
                'entryType' => [
                    'id' => $entryType->id,
                    'name' => $entryType->name,
                    'handle' => $entryType->handle,
                    'sectionHandle' => $sectionHandle,
                ],
                'nextSteps' => [
                    'Create fields using createField',
                    'Add fields to this entry type using addFieldToEntryType',
                ],
            ];
        } catch (\Exception $e) {
            Craft::error('Failed to create entry type: ' . $e->getMessage(), __METHOD__);
            return [
                'error' => 'Failed to create entry type: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Create a new field
     */
    private function createField(string $name, string $handle, string $type, string $instructions = '', bool $required = false): array
    {
        if (empty($name) || empty($handle) || empty($type)) {
            return ['error' => 'Name, handle, and type are required'];
        }

        try {
            // Check if field already exists
            $existingField = Craft::$app->fields->getFieldByHandle($handle);
            if ($existingField) {
                return ['error' => "Field with handle '{$handle}' already exists"];
            }

            // Validate field type exists
            if (!class_exists($type)) {
                return ['error' => "Field type class not found: {$type}. Use listFieldTypes to see available types."];
            }

            $field = new $type();
            $field->name = $name;
            $field->handle = $handle;
            $field->instructions = $instructions;

            // Save the field
            if (!Craft::$app->fields->saveField($field)) {
                return [
                    'error' => 'Failed to create field',
                    'errors' => $field->getErrors(),
                ];
            }

            Craft::info("Field created: {$handle}", __METHOD__);

            return [
                'success' => true,
                'message' => "Field '{$name}' created successfully",
                'field' => [
                    'id' => $field->id,
                    'name' => $field->name,
                    'handle' => $field->handle,
                    'type' => get_class($field),
                ],
                'nextSteps' => [
                    'Field is now available globally',
                    'Add it to an entry type using addFieldToEntryType',
                    'Specify which entry types should have this field',
                ],
            ];
        } catch (\Exception $e) {
            Craft::error('Failed to create field: ' . $e->getMessage(), __METHOD__);
            return [
                'error' => 'Failed to create field: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Add a field to an entry type's field layout
     */
    private function addFieldToEntryType(string $entryTypeHandle, string $fieldHandle, string $tabName = 'Content', bool $required = false): array
    {
        if (empty($entryTypeHandle) || empty($fieldHandle)) {
            return ['error' => 'Entry type handle and field handle are required'];
        }

        try {
            // Find the entry type
            $entryType = null;
            $sections = Craft::$app->sections->getAllSections();

            foreach ($sections as $section) {
                foreach ($section->getEntryTypes() as $type) {
                    if ($type->handle === $entryTypeHandle) {
                        $entryType = $type;
                        break 2;
                    }
                }
            }

            if (!$entryType) {
                return ['error' => "Entry type not found: {$entryTypeHandle}"];
            }

            // Get the field
            $field = Craft::$app->fields->getFieldByHandle($fieldHandle);
            if (!$field) {
                return ['error' => "Field not found: {$fieldHandle}"];
            }

            // Get or create field layout
            $fieldLayout = $entryType->getFieldLayout() ?? new \craft\models\FieldLayout();
            $fieldLayout->type = Entry::class;

            // Get existing tabs or create new one
            $tabs = $fieldLayout->getTabs();
            $targetTab = null;

            foreach ($tabs as $tab) {
                if ($tab->name === $tabName) {
                    $targetTab = $tab;
                    break;
                }
            }

            // Create tab if it doesn't exist
            if (!$targetTab) {
                $targetTab = new \craft\models\FieldLayoutTab();
                $targetTab->name = $tabName;
                $targetTab->setLayout($fieldLayout);
            }

            // Check if field is already in the layout
            $elements = $targetTab->getElements();
            foreach ($elements as $element) {
                if ($element instanceof \craft\fieldlayoutelements\CustomField) {
                    if ($element->getField()->handle === $fieldHandle) {
                        return ['error' => "Field '{$fieldHandle}' is already in this entry type"];
                    }
                }
            }

            // Create field layout element
            $fieldLayoutElement = new \craft\fieldlayoutelements\CustomField($field);
            $fieldLayoutElement->required = $required;

            // Add field to tab
            $elements[] = $fieldLayoutElement;
            $targetTab->setElements($elements);

            // Set tabs on layout
            $allTabs = [];
            $tabFound = false;
            foreach ($tabs as $tab) {
                if ($tab->name === $tabName) {
                    $allTabs[] = $targetTab;
                    $tabFound = true;
                } else {
                    $allTabs[] = $tab;
                }
            }
            if (!$tabFound) {
                $allTabs[] = $targetTab;
            }

            $fieldLayout->setTabs($allTabs);
            $entryType->setFieldLayout($fieldLayout);

            // Save the entry type
            if (!Craft::$app->entries->saveEntryType($entryType)) {
                return [
                    'error' => 'Failed to update entry type',
                    'errors' => $entryType->getErrors(),
                ];
            }

            Craft::info("Field '{$fieldHandle}' added to entry type '{$entryTypeHandle}'", __METHOD__);

            return [
                'success' => true,
                'message' => "Field '{$fieldHandle}' added to entry type '{$entryTypeHandle}' successfully",
                'layout' => [
                    'entryType' => $entryTypeHandle,
                    'tab' => $tabName,
                    'field' => $fieldHandle,
                    'required' => $required,
                ],
            ];
        } catch (\Exception $e) {
            Craft::error('Failed to add field to entry type: ' . $e->getMessage(), __METHOD__);
            return [
                'error' => 'Failed to add field to entry type: ' . $e->getMessage(),
            ];
        }
    }

}
