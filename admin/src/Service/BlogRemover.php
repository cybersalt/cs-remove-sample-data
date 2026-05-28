<?php
/**
 * @package    Cybersalt.Component.Removesampledata
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

namespace Cybersalt\Component\Removesampledata\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;

class BlogRemover
{
    private DatabaseInterface $db;
    private ActionLogger $logger;

    public function __construct(DatabaseInterface $db, ActionLogger $logger)
    {
        $this->db     = $db;
        $this->logger = $logger;
    }

    /**
     * Remove the items the caller selected (filtered version of the detection result).
     * Order respects FK dependencies: modules -> menu items -> menu types ->
     * field values -> articles -> categories -> workflows (stages/transitions first) ->
     * tags -> fields -> field groups.
     */
    public function remove(array $selected): array
    {
        $counts = ['removed' => 0, 'failed' => 0];

        $this->logger->info('start', 'blog', '', 'Blog sample data removal started');

        // 1. Modules
        foreach ($selected['modules'] ?? [] as $item) {
            $this->deleteRow('#__modules_menu', 'moduleid', (int) $item['id']);
            $this->deleteRow('#__modules', 'id', (int) $item['id'], $item['title'], 'module', $counts);
        }

        // 2. Menu items
        foreach ($selected['menu_items'] ?? [] as $item) {
            $this->deleteRow('#__menu', 'id', (int) $item['id'], $item['title'], 'menu_item', $counts);
        }

        // 3. Menu types
        foreach ($selected['menu_types'] ?? [] as $item) {
            $this->deleteRow('#__menu_types', 'id', (int) $item['id'], $item['title'], 'menu_type', $counts);
        }

        // 4. Article -> field values, frontpage assignments, then article rows
        if (!empty($selected['articles'])) {
            $articleIds = array_map(fn ($a) => (int) $a['id'], $selected['articles']);
            $this->bulkDelete('#__fields_values', [
                'item_id'     => $articleIds,
            ], 'field_value', $counts, false);
            $this->bulkDelete('#__content_frontpage', [
                'content_id'  => $articleIds,
            ], 'content_frontpage', $counts, false);
            foreach ($selected['articles'] as $item) {
                $this->deleteRow('#__content', 'id', (int) $item['id'], $item['title'], 'article', $counts);
            }
        }

        // 5. Content categories
        foreach ($selected['categories'] ?? [] as $item) {
            $this->deleteRow('#__categories', 'id', (int) $item['id'], $item['title'], 'category', $counts);
        }

        // 6. Workflow transitions -> stages -> workflow
        foreach ($selected['workflow_transitions'] ?? [] as $item) {
            $this->deleteRow('#__workflow_transitions', 'id', (int) $item['id'], $item['title'], 'workflow_transition', $counts);
        }
        foreach ($selected['workflow_stages'] ?? [] as $item) {
            $this->deleteRow('#__workflow_stages', 'id', (int) $item['id'], $item['title'], 'workflow_stage', $counts);
        }
        foreach ($selected['workflows'] ?? [] as $item) {
            $this->deleteRow('#__workflows', 'id', (int) $item['id'], $item['title'], 'workflow', $counts);
        }

        // 7. Tags
        foreach ($selected['tags'] ?? [] as $item) {
            $this->deleteRow('#__contentitem_tag_map', 'tag_id', (int) $item['id'], '', '', $counts, false);
            $this->deleteRow('#__tags', 'id', (int) $item['id'], $item['title'], 'tag', $counts);
        }

        // 8. Fields then field groups
        foreach ($selected['fields'] ?? [] as $item) {
            $this->deleteRow('#__fields_values', 'field_id', (int) $item['id'], '', '', $counts, false);
            $this->deleteRow('#__fields', 'id', (int) $item['id'], $item['title'], 'field', $counts);
        }
        foreach ($selected['field_groups'] ?? [] as $item) {
            $this->deleteRow('#__fields_groups', 'id', (int) $item['id'], $item['title'], 'field_group', $counts);
        }

        $this->logger->success('finish', 'blog', '', 'Blog sample data removal complete', $counts);
        return $counts;
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

    private function bulkDelete(string $table, array $whereInMap, string $logType, array &$counts, bool $countIt = true): void
    {
        foreach ($whereInMap as $col => $ids) {
            if (!$ids) {
                continue;
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
    }
}
