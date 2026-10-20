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

        $categories = $this->findCategories($aliasMap['categories'], $titleMap['categories']);
        $articles   = $this->findArticles($aliasMap['articles'], $titleMap['articles'], array_column($categories, 'id'));
        $menuTypes  = $this->findMenuTypes($titleMap['menu_types']);

        $result = [
            'articles'             => $articles,
            'categories'           => $this->flagCategoriesWithOtherContent($categories, array_column($articles, 'id')),
            'tags'                 => $this->flagTagsUsedElsewhere($this->findTags($aliasMap['tags'], $titleMap['tags']), array_column($articles, 'id')),
            'menu_items'           => $this->findMenuItems($aliasMap['menu_items'], $menuItemValidTitles),
            'menu_types'           => $menuTypes,
            'modules'              => $this->findModules(array_column($menuTypes, 'menutype')),
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

    private function findArticles(array $aliases, array $titles, array $sampleCatIds = []): array
    {
        if (!$aliases && !$titles) {
            return [];
        }
        $sampleCatSet = array_flip(array_map('intval', $sampleCatIds));
        $query = $this->db->createQuery()
            ->select($this->db->quoteName(['id', 'title', 'alias', 'catid', 'created', 'modified']))
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

            // The blog plugin files every sample article in one of its own
            // four categories. An article with a sample-looking title or
            // alias (e.g. "About") that lives anywhere else is far more
            // likely to be the site's own content.
            if (!isset($sampleCatSet[(int) $row->catid])) {
                $editReasons[] = 'outside-sample-category';
            }

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
            ->select($this->db->quoteName(['id', 'title', 'alias', 'extension', 'level', 'created_time', 'modified_time']))
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
            $reasons = [];
            if (!isset($titleSet[$row->title])) {
                $reasons[] = 'title-modified';
            }
            // The blog plugin creates its categories at the top level.
            if ((int) $row->level !== 1) {
                $reasons[] = 'not-top-level';
            }
            $items[] = [
                'id'           => (int) $row->id,
                'title'        => $row->title,
                'alias'        => $row->alias,
                'edited'       => !empty($reasons),
                'edit_reasons' => $reasons,
            ];
        }
        return $items;
    }

    /**
     * A sample category that now also holds the site's own articles or
     * sub-categories must not be deleted silently: removing the category row
     * would leave those articles pointing at a category that no longer
     * exists. Flag it so it starts unticked in the scan view.
     */
    private function flagCategoriesWithOtherContent(array $categories, array $sampleArticleIds): array
    {
        if (!$categories) {
            return [];
        }
        $catIds = array_map('intval', array_column($categories, 'id'));
        $in     = implode(',', $catIds);

        $query = $this->db->createQuery()
            ->select([$this->db->quoteName('catid'), 'COUNT(*) AS ' . $this->db->quoteName('n')])
            ->from($this->db->quoteName('#__content'))
            ->where($this->db->quoteName('catid') . ' IN (' . $in . ')')
            ->group($this->db->quoteName('catid'));
        if ($sampleArticleIds) {
            $query->where($this->db->quoteName('id') . ' NOT IN (' . implode(',', array_map('intval', $sampleArticleIds)) . ')');
        }
        $otherArticles = $this->db->setQuery($query)->loadAssocList('catid', 'n') ?: [];

        $query = $this->db->createQuery()
            ->select([$this->db->quoteName('parent_id'), 'COUNT(*) AS ' . $this->db->quoteName('n')])
            ->from($this->db->quoteName('#__categories'))
            ->where($this->db->quoteName('parent_id') . ' IN (' . $in . ')')
            ->where($this->db->quoteName('id') . ' NOT IN (' . $in . ')')
            ->group($this->db->quoteName('parent_id'));
        $otherChildren = $this->db->setQuery($query)->loadAssocList('parent_id', 'n') ?: [];

        foreach ($categories as &$cat) {
            if (!empty($otherArticles[$cat['id']]) || !empty($otherChildren[$cat['id']])) {
                $cat['edit_reasons'][] = 'contains-other-content';
                $cat['edited']         = true;
            }
        }
        unset($cat);
        return $categories;
    }

    /**
     * A sample tag (e.g. "Joomla 6", "Love") that the site has since used on
     * its own content is flagged rather than removed silently.
     */
    private function flagTagsUsedElsewhere(array $tags, array $sampleArticleIds): array
    {
        if (!$tags) {
            return [];
        }
        $query = $this->db->createQuery()
            ->select([$this->db->quoteName('tag_id'), 'COUNT(*) AS ' . $this->db->quoteName('n')])
            ->from($this->db->quoteName('#__contentitem_tag_map'))
            ->where($this->db->quoteName('tag_id') . ' IN (' . implode(',', array_map('intval', array_column($tags, 'id'))) . ')')
            ->group($this->db->quoteName('tag_id'));
        $sampleSet = array_map('intval', $sampleArticleIds);
        $query->where('NOT (' . $this->db->quoteName('type_alias') . ' = ' . $this->db->quote('com_content.article')
            . ($sampleSet ? ' AND ' . $this->db->quoteName('content_item_id') . ' IN (' . implode(',', $sampleSet) . ')' : ' AND 1 = 0')
            . ')');
        $usedElsewhere = $this->db->setQuery($query)->loadAssocList('tag_id', 'n') ?: [];

        foreach ($tags as &$tag) {
            if (!empty($usedElsewhere[$tag['id']])) {
                $tag['edit_reasons'][] = 'used-by-other-content';
                $tag['edited']         = true;
            }
        }
        unset($tag);
        return $tags;
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
            // The blog plugin builds each menutype as "<0|1|2><title>", e.g.
            // "0main-menu-blog". A menu with a matching title but any other
            // menutype (a site's own "Bottom Menu") is not sample data.
            $isSampleMenutype = (bool) preg_match('/^[0-2]\D/', (string) $row->menutype);
            $items[] = [
                'id'           => (int) $row->id,
                'title'        => $row->title,
                'menutype'     => $row->menutype,
                'edited'       => !$isSampleMenutype,
                'edit_reasons' => $isSampleMenutype ? [] : ['not-sample-menutype'],
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
        $menuTypes     = array_filter($this->findMenuTypes($this->expandMenuTypeTitles()), fn ($m) => empty($m['edited']));
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

    /**
     * Module type (and client) each sample module is created with, by its
     * position in SampleDataInventory::BLOG_MODULE_KEYS. Titles such as
     * "Search", "Image" or "Site Information" are common on real sites, so a
     * module only counts as sample data when its title AND type both match.
     * The article modules accept the older per-type modules too (Joomla 5.0/5.1
     * sample data used mod_articles_archive / _latest / _category).
     */
    private const BLOG_MODULE_SPECS = [
        0  => [['mod_menu'], 0],
        1  => [['mod_menu'], 0],
        2  => [['mod_syndicate'], 0],
        3  => [['mod_articles', 'mod_articles_archive'], 0],
        4  => [['mod_articles', 'mod_articles_latest', 'mod_articles_category'], 0],
        5  => [['mod_articles', 'mod_articles_category', 'mod_articles_latest'], 0],
        6  => [['mod_menu'], 0],
        7  => [['mod_finder', 'mod_search'], 0],
        8  => [['mod_custom'], 0],
        9  => [['mod_tags_popular'], 0],
        10 => [['mod_tags_similar'], 0],
        11 => [['mod_stats_admin'], 1],
    ];

    private function findModules(array $sampleMenutypes): array
    {
        // title => list of [allowed module types, client id]
        $specs = [];
        foreach ($this->candidateLanguageCodes() as $code) {
            $lang = $this->loadBlogLanguage($code);
            if (!$lang) {
                continue;
            }
            foreach (SampleDataInventory::BLOG_MODULE_KEYS as $i => $key) {
                $title = $lang->_($key);
                if ($title === '' || strpos($title, 'PLG_SAMPLEDATA_BLOG_') === 0 || !isset(self::BLOG_MODULE_SPECS[$i])) {
                    continue;
                }
                foreach ($this->expandWithLanguageSuffixes([$title], 'title') as $t) {
                    $specs[$t][] = self::BLOG_MODULE_SPECS[$i];
                }
            }
        }
        if (!$specs) {
            return [];
        }
        $titles = array_keys($specs);
        $query = $this->db->createQuery()
            ->select($this->db->quoteName(['id', 'title', 'module', 'position', 'client_id', 'params']))
            ->from($this->db->quoteName('#__modules'))
            ->where($this->db->quoteName('title') . ' IN (' . implode(',', array_map([$this->db, 'quote'], $titles)) . ')');

        $rows = $this->db->setQuery($query)->loadObjectList() ?: [];
        $sampleMenutypeSet = array_flip($sampleMenutypes);
        $items = [];
        foreach ($rows as $row) {
            $match = false;
            foreach ($specs[$row->title] ?? [] as [$types, $client]) {
                if (in_array($row->module, $types, true) && (int) $row->client_id === $client) {
                    $match = true;
                    break;
                }
            }
            if (!$match) {
                continue;
            }
            $reasons = [];
            // A sample menu module shows one of the sample menus. If it now
            // shows a different menu, the site has repurposed it.
            if ($row->module === 'mod_menu') {
                $params   = json_decode((string) $row->params, true) ?: [];
                $menutype = (string) ($params['menutype'] ?? '');
                if (!isset($sampleMenutypeSet[$menutype])) {
                    $reasons[] = 'shows-other-menu';
                }
            }
            $items[] = [
                'id'           => (int) $row->id,
                'title'        => $row->title,
                'module'       => $row->module,
                'position'     => $row->position,
                'client_id'    => (int) $row->client_id,
                'edited'       => !empty($reasons),
                'edit_reasons' => $reasons,
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
            ->where($this->db->quoteName('context') . ' = ' . $this->db->quote('com_content.article'))
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
            ->where($this->db->quoteName('context') . ' = ' . $this->db->quote('com_content.article'))
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
            ->where($this->db->quoteName('extension') . ' = ' . $this->db->quote('com_content.article'))
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
