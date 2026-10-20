<?php
/**
 * @package    Cybersalt.Component.Removesampledata
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

namespace Cybersalt\Component\Removesampledata\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;

class MultilangDetector
{
    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    public function detect(): array
    {
        // The multilang sample-data plugin's signature artefact is the set of
        // per-language menutypes named exactly 'mainmenu-{lang}'. If none of
        // those exist, the site doesn't have multilang sample data — even if
        // there ARE other per-language items present (e.g. the blog sample
        // data, when reinstalled on a site with multilingual content
        // languages still configured, creates blog categories/articles with
        // '-{lang}' suffixes in their aliases, which is NOT multilang sample
        // data — those belong to the Blog scan).
        $menuTypes = $this->findMenuTypes();
        if (!$menuTypes) {
            return [
                'menu_types'       => [],
                'menu_items'       => [],
                'modules'          => [],
                'categories'       => [],
                'articles'         => [],
                'associations'     => [],
                'languagefilter'   => ['present' => false, 'enabled' => false, 'extension_id' => 0],
                'main_menu_module' => ['present' => false, 'published' => 1, 'id' => 0],
            ];
        }

        return [
            'menu_types'           => $menuTypes,
            'menu_items'           => $this->findMenuItems(),
            'modules'              => $this->findModules(array_column($menuTypes, 'menutype')),
            'categories'           => $categories = $this->findCategories(),
            'articles'             => $this->findArticles(array_column($categories, 'id')),
            'associations'         => $this->findAssociationGroups(),
            'languagefilter'       => $this->getLanguageFilterStatus(),
            'main_menu_module'     => $this->getMainMenuModuleState(),
        ];
    }

    public function summary(array $detected): array
    {
        $count = 0;
        foreach (['menu_types', 'menu_items', 'modules', 'categories', 'articles'] as $k) {
            $count += count($detected[$k] ?? []);
        }
        if (!empty($detected['languagefilter']['enabled'])) {
            $count++;
        }
        return ['total' => $count, 'edited' => 0];
    }

    private function findMenuTypes(): array
    {
        $query = $this->db->createQuery()
            ->select($this->db->quoteName(['id', 'menutype', 'title']))
            ->from($this->db->quoteName('#__menu_types'))
            ->where($this->db->quoteName('menutype') . ' LIKE ' . $this->db->quote(SampleDataInventory::MULTILANG_MENUTYPE_PREFIX . '%'));

        $rows = $this->db->setQuery($query)->loadObjectList() ?: [];
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id'           => (int) $row->id,
                'menutype'     => $row->menutype,
                'title'        => $row->title,
                'edited'       => false,
                'edit_reasons' => [],
            ];
        }
        return $items;
    }

    private function findMenuItems(): array
    {
        // Only consider items inside a menutype the multilang sample-data created.
        // Plus: alias has to look like home-... / home_... or allcategories-... / allcategories_...
        // — both belt and braces, to avoid sweeping up legitimate user menu items that
        // happen to share a name.
        $menuTypes = $this->findMenuTypes();
        if (!$menuTypes) {
            return [];
        }
        $menutypeNames = array_column($menuTypes, 'menutype');

        $query = $this->db->createQuery()
            ->select($this->db->quoteName(['id', 'menutype', 'title', 'alias', 'home', 'language']))
            ->from($this->db->quoteName('#__menu'))
            ->where($this->db->quoteName('client_id') . ' = 0')
            ->where($this->db->quoteName('menutype') . ' IN (' . implode(',', array_map([$this->db, 'quote'], $menutypeNames)) . ')');

        $rows = $this->db->setQuery($query)->loadObjectList() ?: [];
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id'           => (int) $row->id,
                'title'        => $row->title,
                'alias'        => $row->alias,
                'menutype'     => $row->menutype,
                'home'         => (int) $row->home,
                'language'     => $row->language,
                'edited'       => false,
                'edit_reasons' => [],
            ];
        }
        return $items;
    }

    private function findModules(array $sampleMenutypes): array
    {
        // The multilingual plugin creates one "Language Switcher" module
        // (mod_languages, language "*") and one "Main menu <lang>" mod_menu
        // module per language that shows the matching mainmenu-<lang> menu.
        $query = $this->db->createQuery()
            ->select($this->db->quoteName(['id', 'title', 'module', 'position', 'language', 'client_id', 'params']))
            ->from($this->db->quoteName('#__modules'))
            ->where($this->db->quoteName('module') . ' IN (' . $this->db->quote(SampleDataInventory::MULTILANG_LANGSWITCH_ELEMENT) . ',' . $this->db->quote('mod_menu') . ')')
            ->where($this->db->quoteName('client_id') . ' = 0');

        $rows          = $this->db->setQuery($query)->loadObjectList() ?: [];
        $sampleTypeSet = array_flip($sampleMenutypes);
        $items         = [];
        foreach ($rows as $row) {
            $reasons = [];
            if ($row->module === SampleDataInventory::MULTILANG_LANGSWITCH_ELEMENT) {
                if ($row->language !== '*') {
                    continue;
                }
                $kind = 'language-switcher';
                // A site's own switcher normally has its own title; only the
                // plugin's default title counts as pristine.
                if ($row->title !== 'Language Switcher') {
                    $reasons[] = 'title-modified';
                }
            } else {
                $params   = json_decode((string) $row->params, true) ?: [];
                $menutype = (string) ($params['menutype'] ?? '');
                if ($row->language === '*' || !isset($sampleTypeSet[$menutype])) {
                    continue;
                }
                $kind = 'language-menu';
                if ($row->title !== 'Main menu ' . $row->language) {
                    $reasons[] = 'title-modified';
                }
            }
            $items[] = [
                'id'           => (int) $row->id,
                'title'        => $row->title,
                'module'       => $row->module,
                'position'     => $row->position,
                'language'     => $row->language,
                'kind'         => $kind,
                'edited'       => !empty($reasons),
                'edit_reasons' => $reasons,
            ];
        }
        return $items;
    }

    private function findCategories(): array
    {
        // Multilang creates a per-language category whose alias ends with the
        // lowercase language code, preceded by either '-' or '_' (depending on
        // how stringURLSafe normalised the title).
        $query = $this->db->createQuery()
            ->select($this->db->quoteName(['id', 'title', 'alias', 'language']))
            ->from($this->db->quoteName('#__categories'))
            ->where($this->db->quoteName('extension') . ' = ' . $this->db->quote('com_content'))
            ->where($this->db->quoteName('language') . ' <> ' . $this->db->quote('*'));

        $rows = $this->db->setQuery($query)->loadObjectList() ?: [];
        $items = [];
        foreach ($rows as $row) {
            if (!$this->aliasEndsWithLang((string) $row->alias, (string) $row->language)) {
                continue;
            }
            // The plugin titles its category "<Category> (<lang>)".
            if (!$this->titleEndsWithLang((string) $row->title, (string) $row->language)) {
                continue;
            }
            $items[] = [
                'id'           => (int) $row->id,
                'title'        => $row->title,
                'alias'        => $row->alias,
                'language'     => $row->language,
                'edited'       => false,
                'edit_reasons' => [],
            ];
        }
        return $items;
    }

    private function findArticles(array $sampleCatIds = []): array
    {
        $query = $this->db->createQuery()
            ->select($this->db->quoteName(['id', 'title', 'alias', 'language', 'catid']))
            ->from($this->db->quoteName('#__content'))
            ->where($this->db->quoteName('language') . ' <> ' . $this->db->quote('*'));

        $rows = $this->db->setQuery($query)->loadObjectList() ?: [];
        $items = [];
        foreach ($rows as $row) {
            if (!$this->aliasEndsWithLang((string) $row->alias, (string) $row->language)
                || !$this->titleEndsWithLang((string) $row->title, (string) $row->language)) {
                continue;
            }
            // The plugin files each sample article in its own per-language
            // sample category.
            $reasons = in_array((int) $row->catid, array_map('intval', $sampleCatIds), true) ? [] : ['outside-sample-category'];
            $items[] = [
                'id'           => (int) $row->id,
                'title'        => $row->title,
                'alias'        => $row->alias,
                'language'     => $row->language,
                'edited'       => !empty($reasons),
                'edit_reasons' => $reasons,
            ];
        }
        return $items;
    }

    private function titleEndsWithLang(string $title, string $language): bool
    {
        $lc = strtolower($language);
        return $lc !== '' && $lc !== '*' && str_ends_with(strtolower($title), ' (' . $lc . ')');
    }

    private function aliasEndsWithLang(string $alias, string $language): bool
    {
        $lc = strtolower($language);
        if ($lc === '' || $lc === '*') {
            return false;
        }
        return str_ends_with($alias, '-' . $lc) || str_ends_with($alias, '_' . $lc);
    }

    private function findAssociationGroups(): array
    {
        // Count associations linking the items we'd be removing — informational only;
        // associations row deletion is cascaded by Joomla on item delete in most paths.
        $query = $this->db->createQuery()
            ->select('COUNT(*) AS total')
            ->from($this->db->quoteName('#__associations'));
        $row = $this->db->setQuery($query)->loadObject();
        return [
            ['type' => 'all-associations', 'count' => (int) ($row->total ?? 0)],
        ];
    }

    private function getLanguageFilterStatus(): array
    {
        $query = $this->db->createQuery()
            ->select($this->db->quoteName(['extension_id', 'enabled']))
            ->from($this->db->quoteName('#__extensions'))
            ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('plugin'))
            ->where($this->db->quoteName('element') . ' = ' . $this->db->quote(SampleDataInventory::MULTILANG_LANGUAGEFILTER_PLUGIN))
            ->where($this->db->quoteName('folder') . ' = ' . $this->db->quote('system'));
        $row = $this->db->setQuery($query)->loadObject();
        if (!$row) {
            return ['present' => false, 'enabled' => false, 'extension_id' => 0];
        }
        return [
            'present'      => true,
            'enabled'      => (int) $row->enabled === 1,
            'extension_id' => (int) $row->extension_id,
        ];
    }

    private function getMainMenuModuleState(): array
    {
        $query = $this->db->createQuery()
            ->select($this->db->quoteName(['id', 'title', 'published']))
            ->from($this->db->quoteName('#__modules'))
            ->where($this->db->quoteName('module') . ' = ' . $this->db->quote('mod_menu'))
            ->where($this->db->quoteName('client_id') . ' = 0')
            ->where($this->db->quoteName('language') . ' = ' . $this->db->quote('*'))
            ->where($this->db->quoteName('position') . ' = ' . $this->db->quote(SampleDataInventory::MULTILANG_LANGSWITCH_POSITION));
        $row = $this->db->setQuery($query)->loadObject();
        if (!$row) {
            return ['present' => false, 'published' => 0, 'id' => 0];
        }
        return [
            'present'   => true,
            'published' => (int) $row->published,
            'id'        => (int) $row->id,
            'title'     => $row->title,
        ];
    }
}
