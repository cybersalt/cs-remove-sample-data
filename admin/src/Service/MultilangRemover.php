<?php
/**
 * @package    Cybersalt.Component.Removesampledata
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

namespace Cybersalt\Component\Removesampledata\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;

class MultilangRemover
{
    private DatabaseInterface $db;
    private ActionLogger $logger;

    public function __construct(DatabaseInterface $db, ActionLogger $logger)
    {
        $this->db     = $db;
        $this->logger = $logger;
    }

    /**
     * @param array $selected      Filtered detection result
     * @param bool  $disableFilter Whether to disable plg_system_languagefilter
     * @param bool  $restoreMenu   Whether to republish the main menu module
     */
    public function remove(array $selected, bool $disableFilter, bool $restoreMenu): array
    {
        $counts = ['removed' => 0, 'failed' => 0, 'skipped' => 0];
        $this->logger->info('start', 'multilang', '', 'Multilingual sample data removal started');

        // Disable language filter plugin (if asked).
        if ($disableFilter && !empty($selected['languagefilter']['enabled'])) {
            $this->setExtensionEnabled((int) $selected['languagefilter']['extension_id'], 0, 'plg_system_languagefilter');
        }

        $cleaner = new RelatedRowsCleaner($this->db, $this->logger);
        $ids     = fn (string $group) => array_map(fn ($i) => (int) $i['id'], $selected[$group] ?? []);

        // Modules: language switcher + per-language menu modules.
        $cleaner->beforeModules($ids('modules'));
        foreach ($selected['modules'] ?? [] as $item) {
            $this->deleteRow('#__modules', 'id', (int) $item['id'], $item['title'], 'module', $counts);
        }

        // Menu items (homes + per-lang items) — must precede menu-type deletion.
        $cleaner->beforeMenuItems($ids('menu_items'));
        foreach ($selected['menu_items'] ?? [] as $item) {
            $this->deleteRow('#__menu', 'id', (int) $item['id'], $item['title'], 'menu_item', $counts);
        }

        // Per-language menu types, once empty.
        foreach ($selected['menu_types'] ?? [] as $item) {
            if (!empty($item['menutype']) && $cleaner->menuTypeStillInUse((string) $item['menutype'])) {
                $this->logger->warning('skip', 'menu_type', (string) $item['id'], $item['title'], 'Menu still has items you chose to keep');
                $counts['skipped']++;
                continue;
            }
            $this->deleteRow('#__menu_types', 'id', (int) $item['id'], $item['title'], 'menu_type', $counts);
        }

        // Per-language articles (associations scoped to com_content.item).
        $cleaner->beforeArticles($ids('articles'));
        foreach ($selected['articles'] ?? [] as $item) {
            $this->deleteRow('#__content', 'id', (int) $item['id'], $item['title'], 'article', $counts);
        }

        // Per-language categories, unless they still hold content being kept.
        foreach ($selected['categories'] ?? [] as $item) {
            if ($cleaner->categoryStillInUse((int) $item['id'])) {
                $this->logger->warning('skip', 'category', (string) $item['id'], $item['title'], 'Category still holds content you chose to keep');
                $counts['skipped']++;
                continue;
            }
            $cleaner->beforeCategories([(int) $item['id']]);
            $this->deleteRow('#__categories', 'id', (int) $item['id'], $item['title'], 'category', $counts);
        }

        $cleaner->rebuildTrees();

        // Restore main menu module if asked.
        if ($restoreMenu && !empty($selected['main_menu_module']['present']) && (int) $selected['main_menu_module']['published'] === 0) {
            $this->setModulePublished((int) $selected['main_menu_module']['id'], 1, (string) $selected['main_menu_module']['title']);
        }

        $this->logger->success('finish', 'multilang', '', 'Multilingual sample data removal complete', $counts);
        return $counts;
    }

    private function setExtensionEnabled(int $extensionId, int $enabled, string $label): void
    {
        try {
            $query = $this->db->createQuery()
                ->update($this->db->quoteName('#__extensions'))
                ->set($this->db->quoteName('enabled') . ' = ' . (int) $enabled)
                ->where($this->db->quoteName('extension_id') . ' = ' . (int) $extensionId);
            $this->db->setQuery($query)->execute();
            $this->logger->success(
                $enabled ? 'enable-plugin' : 'disable-plugin',
                'plugin',
                (string) $extensionId,
                $label
            );
        } catch (\Throwable $e) {
            $this->logger->error('disable-plugin', 'plugin', (string) $extensionId, $label, $e->getMessage());
        }
    }

    private function setModulePublished(int $moduleId, int $published, string $label): void
    {
        try {
            $query = $this->db->createQuery()
                ->update($this->db->quoteName('#__modules'))
                ->set($this->db->quoteName('published') . ' = ' . (int) $published)
                ->where($this->db->quoteName('id') . ' = ' . (int) $moduleId);
            $this->db->setQuery($query)->execute();
            $this->logger->success('republish-module', 'module', (string) $moduleId, $label);
        } catch (\Throwable $e) {
            $this->logger->error('republish-module', 'module', (string) $moduleId, $label, $e->getMessage());
        }
    }

    private function deleteRow(string $table, string $idCol, int $id, string $label = '', string $logType = '', ?array &$counts = null, bool $countIt = true): void
    {
        try {
            $query = $this->db->createQuery()
                ->delete($this->db->quoteName($table))
                ->where($this->db->quoteName($idCol) . ' = ' . (int) $id);
            $this->db->setQuery($query)->execute();

            if ($countIt && $counts !== null) {
                $counts['removed']++;
            }
            if ($logType !== '') {
                $this->logger->success('delete', $logType, (string) $id, $label);
            }
        } catch (\Throwable $e) {
            if ($countIt && $counts !== null) {
                $counts['failed']++;
            }
            $this->logger->error('delete', $logType ?: $table, (string) $id, $label, $e->getMessage());
        }
    }
}
