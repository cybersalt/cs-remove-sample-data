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
        $counts = ['removed' => 0, 'failed' => 0];
        $this->logger->info('start', 'multilang', '', 'Multilingual sample data removal started');

        // Disable language filter plugin (if asked).
        if ($disableFilter && !empty($selected['languagefilter']['enabled'])) {
            $this->setExtensionEnabled((int) $selected['languagefilter']['extension_id'], 0, 'plg_system_languagefilter');
        }

        // Modules: language switcher + per-language menu modules.
        foreach ($selected['modules'] ?? [] as $item) {
            $this->deleteRow('#__modules_menu', 'moduleid', (int) $item['id']);
            $this->deleteRow('#__modules', 'id', (int) $item['id'], $item['title'], 'module', $counts);
        }

        // Menu items (homes + per-lang items) — must precede menu-type deletion.
        if (!empty($selected['menu_items'])) {
            $menuItemIds = array_map(fn ($i) => (int) $i['id'], $selected['menu_items']);
            $this->bulkDelete('#__associations', ['id' => $menuItemIds, 'context' => ['com_menus.item']], 'association', $counts, false);
            foreach ($selected['menu_items'] as $item) {
                $this->deleteRow('#__menu', 'id', (int) $item['id'], $item['title'], 'menu_item', $counts);
            }
        }

        // Per-language menu types.
        foreach ($selected['menu_types'] ?? [] as $item) {
            $this->deleteRow('#__menu_types', 'id', (int) $item['id'], $item['title'], 'menu_type', $counts);
        }

        // Per-language articles.
        if (!empty($selected['articles'])) {
            $articleIds = array_map(fn ($a) => (int) $a['id'], $selected['articles']);
            $this->bulkDeleteSimple('#__associations', 'id', $articleIds, 'association', $counts, false);
            $this->bulkDeleteSimple('#__content_frontpage', 'content_id', $articleIds, 'content_frontpage', $counts, false);
            foreach ($selected['articles'] as $item) {
                $this->deleteRow('#__content', 'id', (int) $item['id'], $item['title'], 'article', $counts);
            }
        }

        // Per-language categories.
        if (!empty($selected['categories'])) {
            $catIds = array_map(fn ($c) => (int) $c['id'], $selected['categories']);
            $this->bulkDeleteSimple('#__associations', 'id', $catIds, 'association', $counts, false);
            foreach ($selected['categories'] as $item) {
                $this->deleteRow('#__categories', 'id', (int) $item['id'], $item['title'], 'category', $counts);
            }
        }

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

    private function deleteRow(string $table, string $idCol, int $id, string $label = '', string $logType = '', array &$counts = null, bool $countIt = true): void
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

    private function bulkDeleteSimple(string $table, string $col, array $ids, string $logType, array &$counts, bool $countIt = true): void
    {
        if (!$ids) {
            return;
        }
        try {
            $query = $this->db->createQuery()
                ->delete($this->db->quoteName($table))
                ->where($this->db->quoteName($col) . ' IN (' . implode(',', array_map('intval', $ids)) . ')');
            $this->db->setQuery($query)->execute();
            $affected = (int) $this->db->getAffectedRows();
            $this->logger->info('bulk-delete', $logType, '', $table . ' rows', ['affected' => $affected]);
            if ($countIt) {
                $counts['removed'] += $affected;
            }
        } catch (\Throwable $e) {
            $this->logger->error('bulk-delete', $logType, '', $table, $e->getMessage());
        }
    }

    private function bulkDelete(string $table, array $where, string $logType, array &$counts, bool $countIt = true): void
    {
        try {
            $query = $this->db->createQuery()->delete($this->db->quoteName($table));
            foreach ($where as $col => $values) {
                $values = (array) $values;
                if (!$values) {
                    return;
                }
                if (is_int($values[0] ?? null)) {
                    $query->where($this->db->quoteName($col) . ' IN (' . implode(',', array_map('intval', $values)) . ')');
                } else {
                    $query->where($this->db->quoteName($col) . ' IN (' . implode(',', array_map([$this->db, 'quote'], $values)) . ')');
                }
            }
            $this->db->setQuery($query)->execute();
            $affected = (int) $this->db->getAffectedRows();
            $this->logger->info('bulk-delete', $logType, '', $table . ' rows', ['affected' => $affected]);
            if ($countIt) {
                $counts['removed'] += $affected;
            }
        } catch (\Throwable $e) {
            $this->logger->error('bulk-delete', $logType, '', $table, $e->getMessage());
        }
    }
}
