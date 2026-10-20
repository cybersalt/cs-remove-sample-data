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
        $counts = ['removed' => 0, 'failed' => 0, 'skipped' => 0];

        $this->logger->info('start', 'blog', '', 'Blog sample data removal started');
        $cleaner = new RelatedRowsCleaner($this->db, $this->logger);
        $ids     = fn (string $group) => array_map(fn ($i) => (int) $i['id'], $selected[$group] ?? []);

        // 1. Modules (with their page assignments and ACL assets)
        $cleaner->beforeModules($ids('modules'));
        foreach ($selected['modules'] ?? [] as $item) {
            $this->deleteRow('#__modules', 'id', (int) $item['id'], $item['title'], 'module', $counts);
        }

        // 2. Menu items
        $cleaner->beforeMenuItems($ids('menu_items'));
        foreach ($selected['menu_items'] ?? [] as $item) {
            $this->deleteRow('#__menu', 'id', (int) $item['id'], $item['title'], 'menu_item', $counts);
        }

        // 3. Menu types — only once they are empty, so items the user kept
        //    never end up in a menu that no longer exists.
        foreach ($selected['menu_types'] ?? [] as $item) {
            if (!empty($item['menutype']) && $cleaner->menuTypeStillInUse((string) $item['menutype'])) {
                $this->logger->warning('skip', 'menu_type', (string) $item['id'], $item['title'], 'Menu still has items you chose to keep');
                $counts['skipped']++;
                continue;
            }
            $this->deleteRow('#__menu_types', 'id', (int) $item['id'], $item['title'], 'menu_type', $counts);
        }

        // 4. Articles, after their field values, featured rows, workflow
        //    associations, tag maps and assets
        $cleaner->beforeArticles($ids('articles'));
        foreach ($selected['articles'] ?? [] as $item) {
            $this->deleteRow('#__content', 'id', (int) $item['id'], $item['title'], 'article', $counts);
        }

        // 5. Content categories — skipped if they still hold articles or
        //    sub-categories the user is keeping.
        foreach ($selected['categories'] ?? [] as $item) {
            if ($cleaner->categoryStillInUse((int) $item['id'])) {
                $this->logger->warning('skip', 'category', (string) $item['id'], $item['title'], 'Category still holds content you chose to keep');
                $counts['skipped']++;
                continue;
            }
            $cleaner->beforeCategories([(int) $item['id']]);
            $this->deleteRow('#__categories', 'id', (int) $item['id'], $item['title'], 'category', $counts);
        }

        // 6. Workflow transitions -> stages -> workflow
        $cleaner->beforeWorkflows($ids('workflows'), $ids('workflow_stages'), $ids('workflow_transitions'));
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
        $cleaner->beforeTags($ids('tags'));
        foreach ($selected['tags'] ?? [] as $item) {
            $this->deleteRow('#__tags', 'id', (int) $item['id'], $item['title'], 'tag', $counts);
        }

        // 8. Fields then field groups
        $cleaner->beforeFields($ids('fields'));
        foreach ($selected['fields'] ?? [] as $item) {
            $this->deleteRow('#__fields', 'id', (int) $item['id'], $item['title'], 'field', $counts);
        }
        $cleaner->beforeFieldGroups($ids('field_groups'));
        foreach ($selected['field_groups'] ?? [] as $item) {
            $this->deleteRow('#__fields_groups', 'id', (int) $item['id'], $item['title'], 'field_group', $counts);
        }

        // 9. Re-number the nested-set trees rows were taken out of.
        $cleaner->rebuildTrees();

        $this->logger->success('finish', 'blog', '', 'Blog sample data removal complete', $counts);
        return $counts;
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
