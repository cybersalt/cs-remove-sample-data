<?php
/**
 * @package    Cybersalt.Component.Removesampledata
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

namespace Cybersalt\Component\Removesampledata\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

class BlogDetector
{
    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Run a full detection pass and return a typed inventory:
     *  [
     *    'articles'    => [ ['id'=>123,'title'=>'x','alias'=>'x','edited'=>false], ... ],
     *    'categories'  => [...], 'tags'=>[...], 'menu_items'=>[...], 'menu_types'=>[...],
     *    'modules'     => [...], 'field_groups'=>[...], 'fields'=>[...],
     *    'workflows'   => [...], 'workflow_stages'=>[...], 'workflow_transitions'=>[...],
     *    'newsfeed_category' => [...]
     *  ]
     */
    public function detect(): array
    {
        $langCodes = $this->candidateLanguageCodes();
        $aliasMap  = $this->buildExpectedAliases($langCodes);
        $titleMap  = $this->buildExpectedTitles($langCodes);

        // Menu items inherit titles from the article or category they link to,
        // so the "valid title" set for menu items is the union of menu-item +
        // article + category titles. Without this, every menu item whose title
        // is borrowed from its target gets flagged as Edited.
        $menuItemValidTitles = array_values(array_unique(array_merge(
            $titleMap['menu_items'],
            $titleMap['articles'],
            $titleMap['categories']
        )));

        $result = [
            'articles'             => $this->findArticles($aliasMap['articles'], $titleMap['articles']),
            'categories'           => $this->findCategories($aliasMap['categories'], $titleMap['categories']),
            'tags'                 => $this->findTags($aliasMap['tags'], $titleMap['tags']),
            'menu_items'           => $this->findMenuItems($aliasMap['menu_items'], $menuItemValidTitles),
            'menu_types'           => $this->findMenuTypes($titleMap['menu_types']),
            'modules'              => $this->findModules($titleMap['modules']),
            'field_groups'         => $this->findFieldGroups($titleMap['field_group']),
            'fields'               => $this->findFields($titleMap['field']),
            'workflows'            => $this->findWorkflows($titleMap['workflow']),
            'workflow_stages'      => $this->findWorkflowStages($titleMap['workflow']),
            'workflow_transitions' => $this->findWorkflowTransitions($titleMap['workflow']),
        ];

        return $result;
    }

    public function summary(array $detected): array
    {
        $count = 0;
        $edited = 0;
        foreach ($detected as $items) {
            foreach ($items as $item) {
                $count++;
                if (!empty($item['edited'])) {
                    $edited++;
                }
            }
        }
        return ['total' => $count, 'edited' => $edited];
    }

    private function candidateLanguageCodes(): array
    {
        $app = Factory::getApplication();
        $codes = [];

        // Current admin language first.
        $codes[] = $app->getLanguage()->getTag();

        // Then any other installed admin language packs that ship the sample-data .ini.
        $base = JPATH_ADMINISTRATOR . '/language';
        if (is_dir($base)) {
            foreach (scandir($base) as $entry) {
                if ($entry === '.' || $entry === '..' || $entry === 'overrides') {
                    continue;
                }
                $iniPath = $base . '/' . $entry . '/plg_sampledata_blog.ini';
                if (is_file($iniPath)) {
                    $codes[] = $entry;
                }
            }
        }
        // en-GB fallback always last.
        $codes[] = 'en-GB';
        return array_values(array_unique(array_filter($codes)));
    }

    private function buildExpectedTitles(array $langCodes): array
    {
        $titles = [
            'categories'        => [],
            'articles'          => [],
            'article_introtext' => [],
            'tags'              => [],
            'menu_types'        => [],
            'menu_items'        => [],
            'modules'           => [],
            'field_group'       => [],
            'field'             => [],
            'workflow'          => [],
        ];

        foreach ($langCodes as $code) {
            $lang = $this->loadBlogLanguage($code);
            if ($lang === null) {
                continue;
            }
            foreach (SampleDataInventory::BLOG_CATEGORY_KEYS as $k) {
                $titles['categories'][$lang->_($k)] = true;
            }
            foreach (SampleDataInventory::BLOG_ARTICLE_KEYS as $k) {
                $titles['articles'][$lang->_($k)] = true;
            }
            foreach (SampleDataInventory::BLOG_ARTICLE_INTROTEXT_KEYS as $k) {
                $value = $lang->_($k);
                if ($value !== '' && $value !== $k) {
                    $titles['article_introtext'][$value] = true;
                }
            }
            foreach (SampleDataInventory::BLOG_TAG_KEYS as $k) {
                $titles['tags'][$lang->_($k)] = true;
            }
            foreach (SampleDataInventory::BLOG_MENU_TYPE_KEYS as $k) {
                $titles['menu_types'][$lang->_($k)] = true;
            }
            foreach (SampleDataInventory::BLOG_MENU_ITEM_KEYS as $k) {
                $titles['menu_items'][$lang->_($k)] = true;
            }
            foreach (SampleDataInventory::BLOG_MODULE_KEYS as $k) {
                $titles['modules'][$lang->_($k)] = true;
            }
            $titles['field_group'][$lang->_(SampleDataInventory::BLOG_FIELD_GROUP_KEY)] = true;
            $titles['field'][$lang->_(SampleDataInventory::BLOG_FIELD_KEY)]             = true;
            $titles['workflow'][$lang->_(SampleDataInventory::BLOG_WORKFLOW_KEY)]       = true;
        }

        // Convert to plain arrays of strings, stripping anything that's still the constant itself.
        foreach ($titles as $k => $v) {
            $titles[$k] = array_values(array_filter(array_keys($v), fn ($t) => $t !== '' && strpos($t, 'PLG_SAMPLEDATA_BLOG_') !== 0));
        }
        // Expand every title set with the language-suffix variants so we
        // also catch blog-on-multilingual installs (e.g. "Blog (en-GB)" as
        // well as "Blog").
        foreach (['categories', 'articles', 'tags', 'menu_types', 'menu_items', 'modules', 'field_group', 'field', 'workflow'] as $k) {
            if (!empty($titles[$k])) {
                $titles[$k] = $this->expandWithLanguageSuffixes($titles[$k], 'title');
            }
        }
        return $titles;
    }

    private function buildExpectedAliases(array $langCodes): array
    {
        $aliases = [
            'categories' => [],
            'articles'   => [],
            'tags'       => [],
            'menu_items' => [],
        ];

        foreach ($langCodes as $code) {
            $lang = $this->loadBlogLanguage($code);
            if ($lang === null) {
                continue;
            }
            foreach (SampleDataInventory::BLOG_CATEGORY_KEYS as $k) {
                $aliases['categories'][] = $this->aliasOf($lang->_($k), $code);
            }
            foreach (SampleDataInventory::BLOG_ARTICLE_KEYS as $k) {
                $aliases['articles'][] = $this->aliasOf($lang->_($k), $code);
            }
            foreach (SampleDataInventory::BLOG_TAG_KEYS as $k) {
                $aliases['tags'][] = $this->aliasOf($lang->_($k), $code);
            }
            foreach (SampleDataInventory::BLOG_MENU_ITEM_KEYS as $k) {
                $aliases['menu_items'][] = $this->aliasOf($lang->_($k), $code);
            }
        }
        foreach ($aliases as $k => $v) {
            $aliases[$k] = array_values(array_unique(array_filter($v)));
        }
        // Expand each alias set with language-suffix variants for blog-on-
        // multilingual installs (e.g. "blog-en-gb" as well as "blog").
        foreach (['categories', 'articles', 'tags', 'menu_items'] as $k) {
            if (!empty($aliases[$k])) {
                $aliases[$k] = $this->expandWithLanguageSuffixes($aliases[$k], 'alias');
            }
        }
        return $aliases;
    }

    private function aliasOf(string $title, string $langTag): string
    {
        return ApplicationHelper::stringURLSafe($title, $langTag);
    }

    /**
     * Joomla's blog sample-data plugin, when installed on a site that ALSO
     * has multilingual content languages configured, appends '-{lang}' to
     * every alias and " ({LANG})" to every title. We need to recognise both
     * the base form and the language-suffixed variants so users can remove
     * a multilingual reinstall the same way they remove a plain one.
     *
     * Returns lang codes formatted both as they appear in #__languages
     * (e.g. 'en-GB') AND in their lowercase / URL-safe form (e.g. 'en-gb'),
     * because Joomla mixes the two depending on where the suffix is used.
     */
    private function contentLanguageSuffixes(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $cached = ['title' => [], 'alias' => []];

        try {
            $query = $this->db->createQuery()
                ->select($this->db->quoteName('lang_code'))
                ->from($this->db->quoteName('#__languages'));
            $codes = $this->db->setQuery($query)->loadColumn() ?: [];
        } catch (\Throwable $e) {
            $codes = [];
        }
        foreach ($codes as $code) {
            $code = (string) $code;
            if ($code === '' || $code === '*') {
                continue;
            }
            // Title suffix is the language code in its canonical form,
            // wrapped in parentheses with a leading space (Joomla's blog
            // plugin actually inserts a double space — we'll match either).
            $cached['title'][] = ' (' . $code . ')';
            // Alias suffix is lowercase, hyphenated, prefixed with '-'.
            $cached['alias'][] = '-' . strtolower($code);
            // Some Joomla code paths use '_{lang}' instead of '-{lang}'.
            $cached['alias'][] = '_' . strtolower($code);
        }
        return $cached;
    }

    /**
     * Given a flat list of "base" titles or aliases, expand it to also
     * include the language-suffixed variants for every content language
     * installed on the site.
     */
    private function expandWithLanguageSuffixes(array $values, string $kind): array
    {
        $suffixes = $this->contentLanguageSuffixes()[$kind] ?? [];
        if (!$suffixes) {
            return $values;
        }
        $expanded = $values;
        foreach ($values as $v) {
            if ($v === '') {
                continue;
            }
            foreach ($suffixes as $suffix) {
                $expanded[] = $v . $suffix;
                // For titles, also handle the double-space variant the blog
                // plugin sometimes inserts before the parenthesised lang.
                if ($kind === 'title' && str_starts_with($suffix, ' (')) {
                    $expanded[] = $v . ' ' . $suffix;
                }
            }
        }
        return array_values(array_unique($expanded));
    }

    private function loadBlogLanguage(string $code): ?Language
    {
        $iniPath = JPATH_ADMINISTRATOR . '/language/' . $code . '/plg_sampledata_blog.ini';
        if (!is_file($iniPath)) {
            return null;
        }
        $lang = Language::getInstance($code);
        $lang->load('plg_sampledata_blog', JPATH_ADMINISTRATOR, $code, true, false);
        return $lang;
    }

    private function findArticles(array $aliases, array $titles): array
    {
        if (!$aliases && !$titles) {
            return [];
        }
        $query = $this->db->createQuery()
            ->select($this->db->quoteName(['id', 'title', 'alias', 'created', 'modified']))
            ->from($this->db->quoteName('#__content'));

        $where = [];
        if ($aliases) {
            $where[] = $this->db->quoteName('alias') . ' IN (' . implode(',', array_map([$this->db, 'quote'], $aliases)) . ')';
        }
        if ($titles) {
            $where[] = $this->db->quoteName('title') . ' IN (' . implode(',', array_map([$this->db, 'quote'], $titles)) . ')';
        }
        $query->where('(' . implode(' OR ', $where) . ')');

        $rows = $this->db->setQuery($query)->loadObjectList() ?: [];

        $titleSet = array_flip($titles);

        $items = [];
        foreach ($rows as $row) {
            $expectedTitle = isset($titleSet[$row->title]);
            $editReasons   = $this->editReasons($expectedTitle, $row);

            $items[] = [
                'id'           => (int) $row->id,
                'title'        => $row->title,
                'alias'        => $row->alias,
                'edited'       => !empty($editReasons),
                'edit_reasons' => $editReasons,
            ];
        }
        return $items;
    }

    /**
     * An item is "edited" if its title no longer matches one of the expected
     * sample-data titles, or if it was clearly modified by hand at least a minute
     * after creation. Same-second-as-created `modified` values are framework
     * artefacts from JTable::store() and don't count.
     */
    private function editReasons(bool $expectedTitle, $row): array
    {
        $reasons = [];
        if (!$expectedTitle) {
            $reasons[] = 'title-modified';
        }
        if (!empty($row->modified) && $row->modified !== '0000-00-00 00:00:00' && !empty($row->created)) {
            $delta = strtotime((string) $row->modified) - strtotime((string) $row->created);
            // 120s tolerates the menu-table tree-rebuild re-saves and any sample-data
            // step-to-step delay; anything beyond that is a real later edit.
            if ($delta > 120) {
                $reasons[] = 'modified-after-create';
            }
        }
        return $reasons;
    }

    private function findCategories(array $aliases, array $titles): array
    {
        if (!$aliases && !$titles) {
            return [];
        }
        $query = $this->db->createQuery()
            ->select($this->db->quoteName(['id', 'title', 'alias', 'extension', 'created_time', 'modified_time']))
            ->from($this->db->quoteName('#__categories'))
            ->where($this->db->quoteName('extension') . ' = ' . $this->db->quote('com_content'));

        $where = [];
        if ($aliases) {
            $where[] = $this->db->quoteName('alias') . ' IN (' . implode(',', array_map([$this->db, 'quote'], $aliases)) . ')';
        }
        if ($titles) {
            $where[] = $this->db->quoteName('title') . ' IN (' . implode(',', array_map([$this->db, 'quote'], $titles)) . ')';
        }
        $query->where('(' . implode(' OR ', $where) . ')');

        $rows = $this->db->setQuery($query)->loadObjectList() ?: [];
        $titleSet = array_flip($titles);

        $items = [];
        foreach ($rows as $row) {
            $expectedTitle = isset($titleSet[$row->title]);
            $edited = !$expectedTitle;
            $items[] = [
                'id'           => (int) $row->id,
                'title'        => $row->title,
                'alias'        => $row->alias,
                'edited'       => $edited,
                'edit_reasons' => $edited ? ['title-modified'] : [],
            ];
        }
        return $items;
    }

    private function findTags(array $aliases, array $titles): array
    {
        if (!$aliases && !$titles) {
            return [];
        }
        $query = $this->db->createQuery()
            ->select($this->db->quoteName(['id', 'title', 'alias']))
            ->from($this->db->quoteName('#__tags'))
            ->where($this->db->quoteName('parent_id') . ' > 0');

        $where = [];
        if ($aliases) {
            $where[] = $this->db->quoteName('alias') . ' IN (' . implode(',', array_map([$this->db, 'quote'], $aliases)) . ')';
        }
        if ($titles) {
            $where[] = $this->db->quoteName('title') . ' IN (' . implode(',', array_map([$this->db, 'quote'], $titles)) . ')';
        }
        $query->where('(' . implode(' OR ', $where) . ')');

        $rows = $this->db->setQuery($query)->loadObjectList() ?: [];
        $titleSet = array_flip($titles);

        $items = [];
        foreach ($rows as $row) {
            $expectedTitle = isset($titleSet[$row->title]);
            $items[] = [
                'id'           => (int) $row->id,
                'title'        => $row->title,
                'alias'        => $row->alias,
                'edited'       => !$expectedTitle,
                'edit_reasons' => !$expectedTitle ? ['title-modified'] : [],
            ];
        }
        return $items;
    }

    private function findMenuTypes(array $titles): array
    {
        if (!$titles) {
            return [];
        }
        $query = $this->db->createQuery()
            ->select($this->db->quoteName(['id', 'menutype', 'title']))
            ->from($this->db->quoteName('#__menu_types'))
            ->where($this->db->quoteName('title') . ' IN (' . implode(',', array_map([$this->db, 'quote'], $titles)) . ')');

        $rows = $this->db->setQuery($query)->loadObjectList() ?: [];
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id'           => (int) $row->id,
                'title'        => $row->title,
                'menutype'     => $row->menutype,
                'edited'       => false,
                'edit_reasons' => [],
            ];
        }
        return $items;
    }

    private function findMenuItems(array $aliases, array $titles): array
    {
        // Membership is decided by menu-type: every item inside a menu type
        // the sample-data plugin created counts as sample data. The title set
        // is used only to decide whether to flag the item as Edited — menu
        // items inherit titles from their target article/category, so the
        // valid-title set must include those too (the caller has already
        // unioned them in).
        $menuTypes     = $this->findMenuTypes($this->expandMenuTypeTitles());
        $menutypeNames = array_column($menuTypes, 'menutype');
        if (!$menutypeNames) {
            return [];
        }

        $query = $this->db->createQuery()
            ->select($this->db->quoteName(['id', 'menutype', 'title', 'alias']))
            ->from($this->db->quoteName('#__menu'))
            ->where($this->db->quoteName('client_id') . ' = 0')
            ->where($this->db->quoteName('menutype') . ' IN (' . implode(',', array_map([$this->db, 'quote'], $menutypeNames)) . ')');

        $rows     = $this->db->setQuery($query)->loadObjectList() ?: [];
        $titleSet = array_flip($titles);

        $items = [];
        foreach ($rows as $row) {
            // Skip the synthetic root row (#__menu has one row per menutype
            // with title = the menutype name; it's not a user-facing item).
            if ($row->title === $row->menutype) {
                continue;
            }
            $expectedTitle = isset($titleSet[$row->title]);
            $items[] = [
                'id'           => (int) $row->id,
                'title'        => $row->title,
                'alias'        => $row->alias,
                'menutype'     => $row->menutype,
                'edited'       => !$expectedTitle,
                'edit_reasons' => !$expectedTitle ? ['title-modified'] : [],
            ];
        }
        return $items;
    }

    private function expandMenuTypeTitles(): array
    {
        $titles = [];
        foreach ($this->candidateLanguageCodes() as $code) {
            $lang = $this->loadBlogLanguage($code);
            if (!$lang) {
                continue;
            }
            foreach (SampleDataInventory::BLOG_MENU_TYPE_KEYS as $k) {
                $titles[] = $lang->_($k);
            }
        }
        $titles = array_values(array_unique(array_filter($titles, fn ($t) => strpos($t, 'PLG_SAMPLEDATA_BLOG_') !== 0)));
        // Include the " (lang-code)" suffixed variants so the menu-type
        // lookup matches when the blog plugin appended the language to the
        // title on a multilingual install.
        return $this->expandWithLanguageSuffixes($titles, 'title');
    }

    private function findModules(array $titles): array
    {
        if (!$titles) {
            return [];
        }
        $query = $this->db->createQuery()
            ->select($this->db->quoteName(['id', 'title', 'module', 'position', 'client_id']))
            ->from($this->db->quoteName('#__modules'))
            ->where($this->db->quoteName('title') . ' IN (' . implode(',', array_map([$this->db, 'quote'], $titles)) . ')');

        $rows = $this->db->setQuery($query)->loadObjectList() ?: [];
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id'           => (int) $row->id,
                'title'        => $row->title,
                'module'       => $row->module,
                'position'     => $row->position,
                'client_id'    => (int) $row->client_id,
                'edited'       => false,
                'edit_reasons' => [],
            ];
        }
        return $items;
    }

    private function findFieldGroups(array $titles): array
    {
        if (!$titles) {
            return [];
        }
        $query = $this->db->createQuery()
            ->select($this->db->quoteName(['id', 'title']))
            ->from($this->db->quoteName('#__fields_groups'))
            ->where($this->db->quoteName('title') . ' IN (' . implode(',', array_map([$this->db, 'quote'], $titles)) . ')');

        $rows = $this->db->setQuery($query)->loadObjectList() ?: [];
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id'    => (int) $row->id,
                'title' => $row->title,
            ];
        }
        return $items;
    }

    private function findFields(array $titles): array
    {
        if (!$titles) {
            return [];
        }
        $query = $this->db->createQuery()
            ->select($this->db->quoteName(['id', 'title', 'name']))
            ->from($this->db->quoteName('#__fields'))
            ->where($this->db->quoteName('title') . ' IN (' . implode(',', array_map([$this->db, 'quote'], $titles)) . ')');

        $rows = $this->db->setQuery($query)->loadObjectList() ?: [];
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id'    => (int) $row->id,
                'title' => $row->title,
                'name'  => $row->name,
            ];
        }
        return $items;
    }

    private function findWorkflows(array $titles): array
    {
        if (!$titles) {
            return [];
        }
        $query = $this->db->createQuery()
            ->select($this->db->quoteName(['id', 'title']))
            ->from($this->db->quoteName('#__workflows'))
            ->where($this->db->quoteName('title') . ' IN (' . implode(',', array_map([$this->db, 'quote'], $titles)) . ')');

        $rows = $this->db->setQuery($query)->loadObjectList() ?: [];
        $items = [];
        foreach ($rows as $row) {
            $items[] = ['id' => (int) $row->id, 'title' => $row->title];
        }
        return $items;
    }

    private function findWorkflowStages(array $workflowTitles): array
    {
        $workflows = $this->findWorkflows($workflowTitles);
        if (!$workflows) {
            return [];
        }
        $ids = array_column($workflows, 'id');
        $query = $this->db->createQuery()
            ->select($this->db->quoteName(['id', 'workflow_id', 'title']))
            ->from($this->db->quoteName('#__workflow_stages'))
            ->where($this->db->quoteName('workflow_id') . ' IN (' . implode(',', array_map('intval', $ids)) . ')');

        $rows = $this->db->setQuery($query)->loadObjectList() ?: [];
        $items = [];
        foreach ($rows as $row) {
            $items[] = ['id' => (int) $row->id, 'workflow_id' => (int) $row->workflow_id, 'title' => $row->title];
        }
        return $items;
    }

    private function findWorkflowTransitions(array $workflowTitles): array
    {
        $workflows = $this->findWorkflows($workflowTitles);
        if (!$workflows) {
            return [];
        }
        $ids = array_column($workflows, 'id');
        $query = $this->db->createQuery()
            ->select($this->db->quoteName(['id', 'workflow_id', 'title']))
            ->from($this->db->quoteName('#__workflow_transitions'))
            ->where($this->db->quoteName('workflow_id') . ' IN (' . implode(',', array_map('intval', $ids)) . ')');

        $rows = $this->db->setQuery($query)->loadObjectList() ?: [];
        $items = [];
        foreach ($rows as $row) {
            $items[] = ['id' => (int) $row->id, 'workflow_id' => (int) $row->workflow_id, 'title' => $row->title];
        }
        return $items;
    }
}
