<?php
/**
 * @package    Cybersalt.Component.Removesampledata
 * @copyright  Copyright (C) 2026 Cybersalt Consulting Ltd. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 *
 * Removes the rows that hang off a deleted item in other tables (ACL assets,
 * workflow associations, tag maps, module/menu assignments) and rebuilds the
 * nested-set trees afterwards, so a removal leaves no orphans behind.
 */

namespace Cybersalt\Component\Removesampledata\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;

class RelatedRowsCleaner
{
    private DatabaseInterface $db;
    private ActionLogger $logger;

    /** @var string[] nested-set tables touched in this run */
    private array $dirtyTrees = [];

    public function __construct(DatabaseInterface $db, ActionLogger $logger)
    {
        $this->db     = $db;
        $this->logger = $logger;
    }

    /** Rows tied to articles that are about to be deleted. */
    public function beforeArticles(array $ids): void
    {
        $ids = $this->ints($ids);
        if (!$ids) {
            return;
        }
        $in = implode(',', $ids);
        // Custom-field values belong to a field with a context. Only touch
        // values of article fields: a user or contact with the same id has
        // its own values in the same table.
        $this->run(
            'DELETE FROM ' . $this->db->quoteName('#__fields_values')
            . ' WHERE ' . $this->db->quoteName('item_id') . ' IN (' . $this->quotedStrings($ids) . ')'
            . ' AND ' . $this->db->quoteName('field_id') . ' IN (SELECT ' . $this->db->quoteName('id') . ' FROM ' . $this->db->quoteName('#__fields')
            . ' WHERE ' . $this->db->quoteName('context') . ' = ' . $this->db->quote('com_content.article') . ')',
            'field_value',
            '#__fields_values'
        );
        $this->deleteWhere('#__content_frontpage', $this->db->quoteName('content_id') . ' IN (' . $in . ')', 'content_frontpage');
        $this->deleteWhere(
            '#__workflow_associations',
            $this->db->quoteName('item_id') . ' IN (' . $in . ') AND ' . $this->db->quoteName('extension') . ' = ' . $this->db->quote('com_content.article'),
            'workflow_association'
        );
        $this->deleteWhere(
            '#__contentitem_tag_map',
            $this->db->quoteName('content_item_id') . ' IN (' . $in . ') AND ' . $this->db->quoteName('type_alias') . ' = ' . $this->db->quote('com_content.article'),
            'tag_map'
        );
        $this->deleteWhere(
            '#__ucm_content',
            $this->db->quoteName('core_content_item_id') . ' IN (' . $in . ') AND ' . $this->db->quoteName('core_type_alias') . ' = ' . $this->db->quote('com_content.article'),
            'ucm_content'
        );
        $this->deleteWhere(
            '#__associations',
            $this->db->quoteName('id') . ' IN (' . $in . ') AND ' . $this->db->quoteName('context') . ' = ' . $this->db->quote('com_content.item'),
            'association'
        );
        $this->deleteAssets(array_map(fn ($id) => 'com_content.article.' . $id, $ids));
    }

    public function beforeCategories(array $ids): void
    {
        $ids = $this->ints($ids);
        if (!$ids) {
            return;
        }
        $in = implode(',', $ids);
        $this->deleteWhere(
            '#__associations',
            $this->db->quoteName('id') . ' IN (' . $in . ') AND ' . $this->db->quoteName('context') . ' = ' . $this->db->quote('com_categories.item'),
            'association'
        );
        $this->deleteWhere(
            '#__contentitem_tag_map',
            $this->db->quoteName('content_item_id') . ' IN (' . $in . ') AND ' . $this->db->quoteName('type_alias') . ' = ' . $this->db->quote('com_content.category'),
            'tag_map'
        );
        $this->deleteAssets(array_map(fn ($id) => 'com_content.category.' . $id, $ids));
        $this->dirtyTrees['#__categories'] = true;
    }

    public function beforeModules(array $ids): void
    {
        $ids = $this->ints($ids);
        if (!$ids) {
            return;
        }
        $this->deleteWhere('#__modules_menu', $this->db->quoteName('moduleid') . ' IN (' . implode(',', $ids) . ')', 'module_assignment');
        $this->deleteAssets(array_map(fn ($id) => 'com_modules.module.' . $id, $ids));
    }

    public function beforeMenuItems(array $ids): void
    {
        $ids = $this->ints($ids);
        if (!$ids) {
            return;
        }
        $in = implode(',', $ids);
        // Module assignments to these pages (positive = "only on", negative = "all except").
        $this->deleteWhere(
            '#__modules_menu',
            $this->db->quoteName('menuid') . ' IN (' . $in . ',' . implode(',', array_map(fn ($i) => -$i, $ids)) . ')',
            'module_assignment'
        );
        $this->deleteWhere(
            '#__associations',
            $this->db->quoteName('id') . ' IN (' . $in . ') AND ' . $this->db->quoteName('context') . ' = ' . $this->db->quote('com_menus.item'),
            'association'
        );
        $this->dirtyTrees['#__menu'] = true;
    }

    public function beforeTags(array $ids): void
    {
        $ids = $this->ints($ids);
        if (!$ids) {
            return;
        }
        $this->deleteWhere('#__contentitem_tag_map', $this->db->quoteName('tag_id') . ' IN (' . implode(',', $ids) . ')', 'tag_map');
        $this->dirtyTrees['#__tags'] = true;
    }

    public function beforeFields(array $ids): void
    {
        $ids = $this->ints($ids);
        if (!$ids) {
            return;
        }
        $this->deleteWhere('#__fields_values', $this->db->quoteName('field_id') . ' IN (' . implode(',', $ids) . ')', 'field_value');
        $this->deleteAssets(array_map(fn ($id) => 'com_content.field.' . $id, $ids));
    }

    public function beforeFieldGroups(array $ids): void
    {
        $ids = $this->ints($ids);
        if ($ids) {
            $this->deleteAssets(array_map(fn ($id) => 'com_content.fieldgroup.' . $id, $ids));
        }
    }

    public function beforeWorkflows(array $workflowIds, array $stageIds, array $transitionIds): void
    {
        $stageIds    = $this->ints($stageIds);
        $workflowIds = $this->ints($workflowIds);

        // Articles that stay on the site but sit in a stage of the sample
        // workflow (e.g. an article written in the sample "Blog" category,
        // which uses that workflow) would vanish from the Articles list once
        // the stage is gone: the list INNER JOINs the workflow stage. Move
        // them to the default stage of the default article workflow first.
        if ($stageIds) {
            try {
                $q = $this->db->createQuery()
                    ->select($this->db->quoteName('s.id'))
                    ->from($this->db->quoteName('#__workflow_stages', 's'))
                    ->join('INNER', $this->db->quoteName('#__workflows', 'w'), $this->db->quoteName('w.id') . ' = ' . $this->db->quoteName('s.workflow_id'))
                    ->where($this->db->quoteName('w.default') . ' = 1')
                    ->where($this->db->quoteName('w.extension') . ' = ' . $this->db->quote('com_content.article'))
                    ->where($this->db->quoteName('s.default') . ' = 1')
                    ->where($this->db->quoteName('s.id') . ' NOT IN (' . implode(',', $stageIds) . ')');
                $defaultStage = (int) $this->db->setQuery($q, 0, 1)->loadResult();
                if ($defaultStage > 0) {
                    $q = $this->db->createQuery()
                        ->update($this->db->quoteName('#__workflow_associations'))
                        ->set($this->db->quoteName('stage_id') . ' = ' . $defaultStage)
                        ->where($this->db->quoteName('stage_id') . ' IN (' . implode(',', $stageIds) . ')')
                        ->where($this->db->quoteName('extension') . ' = ' . $this->db->quote('com_content.article'));
                    $this->db->setQuery($q)->execute();
                    $moved = (int) $this->db->getAffectedRows();
                    if ($moved > 0) {
                        $this->logger->info('move-stage', 'workflow_association', '', 'Kept articles moved to the default workflow stage', ['affected' => $moved, 'stage_id' => $defaultStage]);
                    }
                }
            } catch (\Throwable $e) {
                $this->logger->error('move-stage', 'workflow_association', '', '#__workflow_associations', $e->getMessage());
            }
        }

        // Kept categories that were set to use the sample workflow go back
        // to inheriting their workflow.
        foreach ($workflowIds as $wid) {
            try {
                $q = $this->db->createQuery()
                    ->select($this->db->quoteName(['id', 'params']))
                    ->from($this->db->quoteName('#__categories'))
                    ->where($this->db->quoteName('extension') . ' = ' . $this->db->quote('com_content'))
                    ->where($this->db->quoteName('params') . ' LIKE ' . $this->db->quote('%"workflow_id":"' . $wid . '"%'));
                foreach ($this->db->setQuery($q)->loadObjectList() ?: [] as $cat) {
                    $params = json_decode((string) $cat->params, true) ?: [];
                    if ((string) ($params['workflow_id'] ?? '') !== (string) $wid) {
                        continue;
                    }
                    $params['workflow_id'] = 'use_inherited';
                    $u = $this->db->createQuery()
                        ->update($this->db->quoteName('#__categories'))
                        ->set($this->db->quoteName('params') . ' = ' . $this->db->quote(json_encode($params)))
                        ->where($this->db->quoteName('id') . ' = ' . (int) $cat->id);
                    $this->db->setQuery($u)->execute();
                    $this->logger->info('reset-workflow', 'category', (string) $cat->id, 'Category workflow set back to inherited');
                }
            } catch (\Throwable $e) {
                $this->logger->error('reset-workflow', 'category', '', '#__categories', $e->getMessage());
            }
        }

        $names = array_merge(
            array_map(fn ($id) => 'com_content.workflow.' . $id, $this->ints($workflowIds)),
            array_map(fn ($id) => 'com_content.stage.' . $id, $this->ints($stageIds)),
            array_map(fn ($id) => 'com_content.transition.' . $id, $this->ints($transitionIds))
        );
        $this->deleteAssets($names);
    }

    /**
     * Rebuild lft/rgt for every nested-set table rows were removed from, the
     * same thing the "Rebuild" toolbar buttons in Joomla do.
     */
    public function rebuildTrees(): void
    {
        // #__assets is left alone: gaps in its lft/rgt are harmless, and a
        // full asset rebuild is slow on large sites.
        $map = [
            '#__categories' => \Joomla\CMS\Table\Category::class,
            '#__menu'       => \Joomla\CMS\Table\Menu::class,
        ];
        foreach (array_keys($this->dirtyTrees) as $table) {
            try {
                if ($table === '#__tags') {
                    $class = '\\Joomla\\Component\\Tags\\Administrator\\Table\\TagTable';
                    if (!class_exists($class)) {
                        continue;
                    }
                    $t = new $class($this->db);
                } else {
                    $class = $map[$table];
                    $t     = new $class($this->db);
                }
                $t->rebuild();
                $this->logger->info('rebuild', 'tree', '', $table);
            } catch (\Throwable $e) {
                $this->logger->error('rebuild', 'tree', '', $table, $e->getMessage());
            }
        }
    }

    /**
     * True when a category still holds articles or sub-categories, i.e.
     * deleting it would orphan content the user is keeping.
     */
    public function categoryStillInUse(int $id): bool
    {
        $q = $this->db->createQuery()
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__content'))
            ->where($this->db->quoteName('catid') . ' = ' . (int) $id);
        if ((int) $this->db->setQuery($q)->loadResult() > 0) {
            return true;
        }
        $q = $this->db->createQuery()
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__categories'))
            ->where($this->db->quoteName('parent_id') . ' = ' . (int) $id);
        return (int) $this->db->setQuery($q)->loadResult() > 0;
    }

    /** True when a menu type still has menu items in it. */
    public function menuTypeStillInUse(string $menutype): bool
    {
        $q = $this->db->createQuery()
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__menu'))
            ->where($this->db->quoteName('menutype') . ' = ' . $this->db->quote($menutype))
            ->where($this->db->quoteName('client_id') . ' = 0');
        return (int) $this->db->setQuery($q)->loadResult() > 0;
    }

    private function deleteAssets(array $names): void
    {
        if (!$names) {
            return;
        }
        $this->deleteWhere(
            '#__assets',
            $this->db->quoteName('name') . ' IN (' . implode(',', array_map([$this->db, 'quote'], $names)) . ')',
            'asset'
        );
    }

    private function deleteWhere(string $table, string $where, string $logType): void
    {
        $this->run('DELETE FROM ' . $this->db->quoteName($table) . ' WHERE ' . $where, $logType, $table);
    }

    private function run(string $sql, string $logType, string $table): void
    {
        try {
            $this->db->setQuery($sql)->execute();
            $affected = (int) $this->db->getAffectedRows();
            if ($affected > 0) {
                $this->logger->info('bulk-delete', $logType, '', $table . ' rows', ['affected' => $affected]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('bulk-delete', $logType, '', $table, $e->getMessage());
        }
    }

    private function ints(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), fn ($i) => $i > 0)));
    }

    private function quotedStrings(array $ids): string
    {
        return implode(',', array_map(fn ($i) => $this->db->quote((string) $i), $ids));
    }
}
