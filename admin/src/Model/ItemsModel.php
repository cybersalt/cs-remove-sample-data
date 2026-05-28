<?php
/**
 * @package    Cybersalt.Component.Removesampledata
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

namespace Cybersalt\Component\Removesampledata\Administrator\Model;

\defined('_JEXEC') or die;

use Cybersalt\Component\Removesampledata\Administrator\Service\BlogDetector;
use Cybersalt\Component\Removesampledata\Administrator\Service\MultilangDetector;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\DatabaseInterface;

/**
 * Virtual list of every sample-data item the detectors find, surfaced as a
 * sortable / filterable Joomla list view. The list does not come from a SQL
 * query, so we override _getList() and getTotal() to operate on the in-memory
 * detector output instead.
 */
class ItemsModel extends ListModel
{
    private ?array $allItems = null;
    private int $cachedTotal = 0;

    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'source',
                'type',
                'title',
                'alias',
                'status',
            ];
        }
        parent::__construct($config, $factory);
    }

    protected function populateState($ordering = 'source', $direction = 'asc')
    {
        $app   = Factory::getApplication();
        $input = $app->getInput();

        // The searchtools form submits filter values as a nested array
        // (filter[search], filter[source], etc.) — but deep-links from the
        // dashboard may use either the array form or the flat filter_source
        // form. Read both, prefer array, fall back to flat.
        $filterArray = $input->get('filter', [], 'array');
        $flatMap     = [
            'search' => $input->getString('filter_search', null),
            'source' => $input->getCmd('filter_source', null),
            'type'   => $input->getCmd('filter_type', null),
            'status' => $input->getCmd('filter_status', null),
        ];

        foreach (['search', 'source', 'type', 'status'] as $key) {
            $value = $filterArray[$key] ?? $flatMap[$key] ?? null;
            $stateKey = $this->context . '.filter.' . $key;
            if ($value !== null) {
                $app->setUserState($stateKey, $value);
            } else {
                $value = $app->getUserState($stateKey, '');
            }
            $this->setState('filter.' . $key, (string) $value);
        }

        parent::populateState($ordering, $direction);
    }

    protected function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.source');
        $id .= ':' . $this->getState('filter.type');
        $id .= ':' . $this->getState('filter.status');
        return parent::getStoreId($id);
    }

    /**
     * Required by ListModel; we return an empty query because we override _getList().
     */
    protected function getListQuery()
    {
        return $this->getDatabase()->createQuery();
    }

    protected function _getList($query, $limitstart = 0, $limit = 0)
    {
        $items = $this->getFilteredSortedItems();
        $this->cachedTotal = count($items);

        if ($limit > 0) {
            $items = array_slice($items, $limitstart, $limit);
        }
        return $items;
    }

    public function getTotal()
    {
        if ($this->allItems === null) {
            $this->getFilteredSortedItems();
        }
        return $this->cachedTotal;
    }

    public function getStats(): array
    {
        $all = $this->getAllItems();
        $stats = [
            'total'     => count($all),
            'edited'    => 0,
            'by_source' => ['blog' => 0, 'multilang' => 0],
            'by_type'   => [],
        ];
        foreach ($all as $i) {
            if (!empty($i['edited'])) {
                $stats['edited']++;
            }
            $stats['by_source'][$i['source']] = ($stats['by_source'][$i['source']] ?? 0) + 1;
            $stats['by_type'][$i['type']] = ($stats['by_type'][$i['type']] ?? 0) + 1;
        }
        return $stats;
    }

    private function getFilteredSortedItems(): array
    {
        $items = $this->getAllItems();

        $search = trim((string) $this->getState('filter.search'));
        $source = (string) $this->getState('filter.source');
        $type   = (string) $this->getState('filter.type');
        $status = (string) $this->getState('filter.status');

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $items  = array_values(array_filter($items, function ($i) use ($needle) {
                $hay = mb_strtolower(($i['title'] ?? '') . ' ' . ($i['alias'] ?? '') . ' ' . ($i['details'] ?? ''));
                return strpos($hay, $needle) !== false;
            }));
        }
        if ($source !== '') {
            $items = array_values(array_filter($items, fn ($i) => $i['source'] === $source));
        }
        if ($type !== '') {
            $items = array_values(array_filter($items, fn ($i) => $i['type'] === $type));
        }
        if ($status === 'edited') {
            $items = array_values(array_filter($items, fn ($i) => !empty($i['edited'])));
        } elseif ($status === 'pristine') {
            $items = array_values(array_filter($items, fn ($i) => empty($i['edited'])));
        }

        $ordering  = $this->getState('list.ordering', 'source');
        $direction = strtoupper($this->getState('list.direction', 'asc')) === 'DESC' ? -1 : 1;

        usort($items, function ($a, $b) use ($ordering, $direction) {
            switch ($ordering) {
                case 'status':
                    $av = empty($a['edited']) ? 0 : 1;
                    $bv = empty($b['edited']) ? 0 : 1;
                    break;
                case 'type':
                    $av = $a['type']; $bv = $b['type'];
                    break;
                case 'title':
                    $av = mb_strtolower((string) $a['title']);
                    $bv = mb_strtolower((string) $b['title']);
                    break;
                case 'source':
                default:
                    $av = $a['source']; $bv = $b['source'];
                    break;
            }
            if ($av === $bv) {
                return 0;
            }
            return ($av < $bv ? -1 : 1) * $direction;
        });

        return $items;
    }

    private function getAllItems(): array
    {
        if ($this->allItems !== null) {
            return $this->allItems;
        }
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $blog = (new BlogDetector($db))->detect();
        $ml   = (new MultilangDetector($db))->detect();

        $flat = [];
        $flat = array_merge($flat, $this->flatten('blog', $blog));
        $flat = array_merge($flat, $this->flatten('multilang', $ml));

        $this->allItems    = $flat;
        $this->cachedTotal = count($flat);
        return $flat;
    }

    private function flatten(string $source, array $detected): array
    {
        $groupToType = [
            'articles'             => 'article',
            'categories'           => 'category',
            'tags'                 => 'tag',
            'menu_items'           => 'menu_item',
            'menu_types'           => 'menu_type',
            'modules'              => 'module',
            'fields'               => 'field',
            'field_groups'         => 'field_group',
            'workflows'            => 'workflow',
            'workflow_stages'      => 'workflow_stage',
            'workflow_transitions' => 'workflow_transition',
        ];

        $flat = [];
        foreach ($detected as $group => $items) {
            if (!isset($groupToType[$group]) || !is_array($items)) {
                continue;
            }
            $type = $groupToType[$group];
            foreach ($items as $item) {
                if (!is_array($item) || !isset($item['id'])) {
                    continue;
                }
                $flat[] = [
                    'source'       => $source,
                    'type'         => $type,
                    'id'           => (int) $item['id'],
                    'title'        => (string) ($item['title'] ?? $item['menutype'] ?? ''),
                    'alias'        => (string) ($item['alias'] ?? ''),
                    'details'      => $this->buildDetails($item),
                    'edited'       => !empty($item['edited']),
                    'edit_reasons' => $item['edit_reasons'] ?? [],
                    'extras'       => $this->buildExtras($type, $item),
                ];
            }
        }
        return $flat;
    }

    private function buildExtras(string $type, array $item): array
    {
        $extras = [];
        if ($type === 'module') {
            $extras['client_id'] = (int) ($item['client_id'] ?? 0);
        }
        if ($type === 'field' || $type === 'field_group') {
            $extras['context'] = 'com_content.article';
        }
        if ($type === 'workflow' || $type === 'workflow_stage' || $type === 'workflow_transition') {
            $extras['extension'] = 'com_content.article';
        }
        if ($type === 'workflow_stage' || $type === 'workflow_transition') {
            $extras['workflow_id'] = (int) ($item['workflow_id'] ?? 0);
        }
        return $extras;
    }

    private function buildDetails(array $item): string
    {
        $bits = [];
        foreach (['alias', 'menutype', 'module', 'position', 'language'] as $key) {
            if (!empty($item[$key])) {
                $bits[] = $key . '=' . $item[$key];
            }
        }
        return implode(' · ', $bits);
    }
}
