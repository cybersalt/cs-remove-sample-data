<?php
/**
 * @package    Cybersalt.Component.Removesampledata
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 *
 * @var \Cybersalt\Component\Removesampledata\Administrator\View\Scan\HtmlView $this
 */

\defined('_JEXEC') or die;

use Cybersalt\Component\Removesampledata\Administrator\Helper\EditUrlHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

$kind     = $this->kind;
$detected = $this->detected;

HTMLHelper::_('bootstrap.modal');

// Detector group key -> EditUrlHelper item type
$groupToType = [
    'articles'             => 'article',
    'categories'           => 'category',
    'tags'                 => 'tag',
    'menu_items'           => 'menu_item',
    'menu_types'           => 'menu_type',
    'modules'              => 'module',
    'field_groups'         => 'field_group',
    'fields'               => 'field',
    'workflows'            => 'workflow',
    'workflow_stages'      => 'workflow_stage',
    'workflow_transitions' => 'workflow_transition',
];

$typeLabelKey = [
    'article'             => 'COM_REMOVESAMPLEDATA_TYPE_ARTICLE',
    'category'            => 'COM_REMOVESAMPLEDATA_TYPE_CATEGORY',
    'tag'                 => 'COM_REMOVESAMPLEDATA_TYPE_TAG',
    'menu_item'           => 'COM_REMOVESAMPLEDATA_TYPE_MENU_ITEM',
    'menu_type'           => 'COM_REMOVESAMPLEDATA_TYPE_MENU_TYPE',
    'module'              => 'COM_REMOVESAMPLEDATA_TYPE_MODULE',
    'field_group'         => 'COM_REMOVESAMPLEDATA_TYPE_FIELD_GROUP',
    'field'               => 'COM_REMOVESAMPLEDATA_TYPE_FIELD',
    'workflow'            => 'COM_REMOVESAMPLEDATA_TYPE_WORKFLOW',
    'workflow_stage'      => 'COM_REMOVESAMPLEDATA_TYPE_WORKFLOW_STAGE',
    'workflow_transition' => 'COM_REMOVESAMPLEDATA_TYPE_WORKFLOW_TRANSITION',
];

$extrasFor = function (string $group, array $item): array {
    $extras = [];
    if ($group === 'modules') {
        $extras['client_id'] = (int) ($item['client_id'] ?? 0);
    }
    if ($group === 'fields' || $group === 'field_groups') {
        $extras['context'] = 'com_content.article';
    }
    if (in_array($group, ['workflows', 'workflow_stages', 'workflow_transitions'], true)) {
        $extras['extension'] = 'com_content.article';
    }
    if ($group === 'workflow_stages' || $group === 'workflow_transitions') {
        $extras['workflow_id'] = (int) ($item['workflow_id'] ?? 0);
    }
    return $extras;
};

// Flatten the grouped detection result into a single list, ordered by
// type then title for a natural reading order.
$rows = [];
foreach ($detected as $group => $items) {
    if (!is_array($items) || !isset($groupToType[$group])) {
        continue;
    }
    $type = $groupToType[$group];
    foreach ($items as $item) {
        if (!is_array($item) || !isset($item['id'])) {
            continue;
        }
        $rows[] = [
            'group'        => $group,
            'type'         => $type,
            'id'           => (int) $item['id'],
            'title'        => (string) ($item['title'] ?? $item['menutype'] ?? ''),
            'alias'        => (string) ($item['alias'] ?? ''),
            'edited'       => !empty($item['edited']),
            'edit_reasons' => $item['edit_reasons'] ?? [],
            'edit_url'     => EditUrlHelper::for($type, (int) $item['id'], $extrasFor($group, $item)),
            'detail_bits'  => array_filter([
                'alias'    => $item['alias']    ?? null,
                'menutype' => $item['menutype'] ?? null,
                'module'   => $item['module']   ?? null,
                'position' => $item['position'] ?? null,
                'language' => $item['language'] ?? null,
            ]),
        ];
    }
}
usort($rows, function ($a, $b) {
    if ($a['type'] !== $b['type']) {
        return strcmp($a['type'], $b['type']);
    }
    return strcmp(mb_strtolower($a['title']), mb_strtolower($b['title']));
});

$total  = count($rows);
$edited = count(array_filter($rows, fn ($r) => !empty($r['edited'])));

// Type filter options — only include types actually present in this kind.
$presentTypes = array_values(array_unique(array_column($rows, 'type')));
sort($presentTypes);

// Title filter options — list every distinct title detected.
$presentTitles = array_values(array_unique(array_filter(array_column($rows, 'title'), 'strlen')));
usort($presentTitles, fn ($a, $b) => strcmp(mb_strtolower($a), mb_strtolower($b)));
?>

<form action="<?php echo Route::_('index.php?option=com_removesampledata&task=remove.run'); ?>"
      method="post"
      id="rsd-remove-form">

    <input type="hidden" name="kind" value="<?php echo $this->escape($kind); ?>">
    <?php echo HTMLHelper::_('form.token'); ?>

    <div class="alert alert-warning" role="alert">
        <strong><?php echo Text::_('COM_REMOVESAMPLEDATA_BACKUP_WARNING_TITLE'); ?></strong>
        <?php echo Text::_('COM_REMOVESAMPLEDATA_BACKUP_WARNING_BODY'); ?>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <span class="badge bg-primary"><?php echo (int) $total; ?> <?php echo Text::_('COM_REMOVESAMPLEDATA_ITEMS_DETECTED'); ?></span>
        <?php if ($edited > 0): ?>
            <span class="badge bg-warning text-dark"><?php echo (int) $edited; ?> <?php echo Text::_('COM_REMOVESAMPLEDATA_ITEMS_EDITED'); ?></span>
        <?php endif; ?>
        <span class="ms-auto"></span>
        <?php if ($total > 0): ?>
            <button type="button" class="btn btn-danger" id="rsd-open-confirm-top">
                <span class="icon-trash" aria-hidden="true"></span>
                <?php echo Text::_('COM_REMOVESAMPLEDATA_REMOVE_TICKED'); ?>
                <span class="ms-1">(<span id="rsd-ticked-count-top">0</span>)</span>
            </button>
        <?php endif; ?>
    </div>

    <?php if ($kind === 'multilang'): ?>
        <?php
        $lf = $detected['languagefilter']   ?? ['present' => false, 'enabled' => false];
        $mm = $detected['main_menu_module'] ?? ['present' => false, 'published' => 1];
        ?>
        <div class="card mb-3">
            <div class="card-body">
                <h4 class="card-title"><?php echo Text::_('COM_REMOVESAMPLEDATA_SIDE_EFFECTS_TITLE'); ?></h4>
                <p class="card-text"><?php echo Text::_('COM_REMOVESAMPLEDATA_SIDE_EFFECTS_DESC'); ?></p>
                <ul class="list-group">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>
                            <strong><?php echo Text::_('COM_REMOVESAMPLEDATA_SIDE_LANGUAGEFILTER'); ?></strong>
                            <span class="text-muted">
                                <?php echo $lf['enabled']
                                    ? Text::_('COM_REMOVESAMPLEDATA_SIDE_LANGUAGEFILTER_NOW_ENABLED')
                                    : Text::_('COM_REMOVESAMPLEDATA_SIDE_LANGUAGEFILTER_NOW_DISABLED'); ?>
                            </span>
                        </span>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input"
                                   type="checkbox"
                                   role="switch"
                                   name="disable_languagefilter"
                                   value="1"
                                   <?php echo $lf['enabled'] ? 'checked' : ''; ?>>
                            <label class="form-check-label"><?php echo Text::_('COM_REMOVESAMPLEDATA_SIDE_LANGUAGEFILTER_DISABLE'); ?></label>
                        </div>
                    </li>
                </ul>
                <?php if (!empty($mm['present']) && (int) $mm['published'] === 0): ?>
                    <p class="mt-3 mb-0 text-muted">
                        <?php echo Text::sprintf('COM_REMOVESAMPLEDATA_SIDE_MAIN_MENU_RESTORE', $this->escape((string) ($mm['title'] ?? ''))); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($total === 0): ?>
        <div class="alert alert-info">
            <?php echo Text::_('COM_REMOVESAMPLEDATA_NOTHING_DETECTED'); ?>
        </div>
    <?php else: ?>

        <!-- Filter bar: one dropdown per filterable column. -->
        <div class="row g-2 mb-2">
            <div class="col-md-4">
                <label for="rsd-filter-type" class="form-label small mb-1 text-muted">
                    <?php echo Text::_('COM_REMOVESAMPLEDATA_COL_TYPE'); ?>
                </label>
                <select id="rsd-filter-type" class="form-select">
                    <option value=""><?php echo Text::_('COM_REMOVESAMPLEDATA_SCAN_FILTER_ALL_TYPES'); ?></option>
                    <?php foreach ($presentTypes as $t): ?>
                        <option value="<?php echo $this->escape($t); ?>">
                            <?php echo Text::_($typeLabelKey[$t] ?? $t); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="rsd-filter-title" class="form-label small mb-1 text-muted">
                    <?php echo Text::_('COM_REMOVESAMPLEDATA_COL_TITLE'); ?>
                </label>
                <select id="rsd-filter-title" class="form-select">
                    <option value=""><?php echo Text::_('COM_REMOVESAMPLEDATA_SCAN_FILTER_ALL_TITLES'); ?></option>
                    <?php foreach ($presentTitles as $t): ?>
                        <option value="<?php echo $this->escape($t); ?>">
                            <?php echo $this->escape($t); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="rsd-filter-status" class="form-label small mb-1 text-muted">
                    <?php echo Text::_('COM_REMOVESAMPLEDATA_COL_STATUS'); ?>
                </label>
                <select id="rsd-filter-status" class="form-select">
                    <option value=""><?php echo Text::_('COM_REMOVESAMPLEDATA_SCAN_FILTER_ALL_STATUSES'); ?></option>
                    <option value="pristine"><?php echo Text::_('COM_REMOVESAMPLEDATA_STATUS_PRISTINE'); ?></option>
                    <option value="edited"><?php echo Text::_('COM_REMOVESAMPLEDATA_STATUS_EDITED'); ?></option>
                </select>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="button" class="btn btn-secondary w-100" id="rsd-filter-clear" title="<?php echo $this->escape(Text::_('COM_REMOVESAMPLEDATA_SCAN_FILTER_CLEAR')); ?>">
                    <span class="icon-remove" aria-hidden="true"></span>
                </button>
            </div>
        </div>

        <p class="text-muted small mb-2" id="rsd-row-count">
            <?php echo Text::sprintf('COM_REMOVESAMPLEDATA_SCAN_SHOWING', $total, $total); ?>
        </p>

        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th style="width:1%">
                            <input type="checkbox"
                                   id="rsd-master-toggle"
                                   class="form-check-input"
                                   checked
                                   aria-label="<?php echo $this->escape(Text::_('COM_REMOVESAMPLEDATA_MASTER_TOGGLE_ARIA')); ?>">
                        </th>
                        <th><?php echo Text::_('COM_REMOVESAMPLEDATA_COL_TYPE'); ?></th>
                        <th><?php echo Text::_('COM_REMOVESAMPLEDATA_COL_TITLE'); ?></th>
                        <th><?php echo Text::_('COM_REMOVESAMPLEDATA_COL_DETAILS'); ?></th>
                        <th style="width:1%"><?php echo Text::_('COM_REMOVESAMPLEDATA_COL_STATUS'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row):
                        $editUrl   = $row['edit_url'];
                        $typeLabel = Text::_($typeLabelKey[$row['type']] ?? $row['type']);
                    ?>
                        <tr class="rsd-row<?php echo $row['edited'] ? ' rsd-row-edited' : ''; ?>"
                            data-type="<?php echo $this->escape($row['type']); ?>"
                            data-status="<?php echo $row['edited'] ? 'edited' : 'pristine'; ?>"
                            data-title="<?php echo $this->escape($row['title']); ?>">
                            <td>
                                <input type="checkbox"
                                       class="form-check-input rsd-remove"
                                       name="remove[<?php echo $this->escape($row['group']); ?>][]"
                                       value="<?php echo (int) $row['id']; ?>"
                                       checked
                                       aria-label="<?php echo $this->escape(Text::_('COM_REMOVESAMPLEDATA_COL_REMOVE')); ?>">
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?php echo $this->escape($typeLabel); ?></span>
                            </td>
                            <td>
                                <?php if ($editUrl): ?>
                                    <a href="<?php echo $this->escape($editUrl); ?>"
                                       title="<?php echo $this->escape(Text::_('COM_REMOVESAMPLEDATA_OPEN_IN_JOOMLA')); ?>">
                                        <strong><?php echo $this->escape($row['title']); ?></strong>
                                    </a>
                                <?php else: ?>
                                    <strong><?php echo $this->escape($row['title']); ?></strong>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $pretty = [];
                                foreach ($row['detail_bits'] as $k => $v) {
                                    $pretty[] = '<span class="text-muted small">' . $this->escape($k) . ':</span> <code>' . $this->escape((string) $v) . '</code>';
                                }
                                echo implode(' &nbsp; ', $pretty) ?: '&nbsp;';
                                ?>
                            </td>
                            <td>
                                <?php if ($row['edited']): ?>
                                    <span class="badge bg-warning text-dark"
                                          title="<?php echo $this->escape(implode(', ', $row['edit_reasons'])); ?>">
                                        <?php echo Text::_('COM_REMOVESAMPLEDATA_STATUS_EDITED'); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?php echo Text::_('COM_REMOVESAMPLEDATA_STATUS_PRISTINE'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p class="text-muted">
            <?php echo Text::_('COM_REMOVESAMPLEDATA_REMOVE_LEGEND'); ?>
        </p>

        <button type="button" class="btn btn-danger btn-lg" id="rsd-open-confirm">
            <span class="icon-trash" aria-hidden="true"></span>
            <?php echo Text::_('COM_REMOVESAMPLEDATA_REMOVE_TICKED'); ?>
            <span class="ms-1">(<span id="rsd-ticked-count-bottom">0</span>)</span>
        </button>
    <?php endif; ?>

    <!-- Confirm modal -->
    <div class="modal fade" id="rsd-confirm" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rsd-modal-content">
                <div class="modal-header rsd-modal-header">
                    <h5 class="modal-title"><?php echo Text::_('COM_REMOVESAMPLEDATA_CONFIRM_TITLE'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body rsd-modal-body">
                    <div id="rsd-edited-warning" class="alert alert-warning d-none" role="alert">
                        <?php echo Text::_('COM_REMOVESAMPLEDATA_EDITED_WARNING'); ?>
                    </div>
                    <p><?php echo Text::_('COM_REMOVESAMPLEDATA_CONFIRM_BODY'); ?></p>
                    <div class="form-check">
                        <input type="checkbox"
                               class="form-check-input"
                               id="rsd-confirm-checkbox"
                               name="confirm_remove"
                               value="1"
                               form="rsd-remove-form">
                        <label class="form-check-label ms-1" for="rsd-confirm-checkbox">
                            <?php echo Text::_('COM_REMOVESAMPLEDATA_CONFIRM_CHECKBOX_LABEL'); ?>
                        </label>
                    </div>
                </div>
                <div class="modal-footer rsd-modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <?php echo Text::_('JCANCEL'); ?>
                    </button>
                    <button type="submit" form="rsd-remove-form" id="rsd-final-submit" class="btn btn-danger" disabled>
                        <span class="icon-trash" aria-hidden="true"></span>
                        <?php echo Text::_('COM_REMOVESAMPLEDATA_REMOVE_NOW'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Lock-out overlay -->
    <div id="rsd-lockout" class="rsd-lockout d-none" aria-live="polite">
        <div class="rsd-lockout-inner">
            <div class="spinner-border" role="status"></div>
            <h4><?php echo Text::_('COM_REMOVESAMPLEDATA_REMOVING'); ?></h4>
            <p><?php echo Text::_('COM_REMOVESAMPLEDATA_REMOVING_DETAIL'); ?></p>
        </div>
    </div>
</form>
