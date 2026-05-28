<?php
/**
 * @package    Cybersalt.Component.Removesampledata
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 *
 * @var \Cybersalt\Component\Removesampledata\Administrator\View\Log\HtmlView $this
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

HTMLHelper::_('bootstrap.modal');

$entries = $this->entries;
$stats   = $this->stats;
$token   = Session::getFormToken();
?>

<div class="row mb-3">
    <?php
    $cards = [
        ['label' => 'COM_REMOVESAMPLEDATA_STAT_TOTAL',    'value' => $stats['total'],    'class' => 'bg-primary',    'filter' => ''],
        ['label' => 'COM_REMOVESAMPLEDATA_STAT_LAST24',   'value' => $stats['last_24h'], 'class' => 'bg-info',       'filter' => 'last24'],
        ['label' => 'COM_REMOVESAMPLEDATA_STAT_SUCCESS',  'value' => $stats['success'],  'class' => 'bg-success',    'filter' => 'success'],
        ['label' => 'COM_REMOVESAMPLEDATA_STAT_WARNING', 'value' => $stats['warning'],  'class' => 'bg-warning text-dark', 'filter' => 'warning'],
        ['label' => 'COM_REMOVESAMPLEDATA_STAT_ERROR',   'value' => $stats['error'],    'class' => 'bg-danger',     'filter' => 'error'],
    ];
    foreach ($cards as $card): ?>
        <div class="col-sm-6 col-md mb-2">
            <a href="#" class="text-decoration-none rsd-stat-filter" data-filter="<?php echo $this->escape($card['filter']); ?>">
                <div class="card text-white <?php echo $card['class']; ?>">
                    <div class="card-body py-2">
                        <h2 class="mb-0"><?php echo (int) $card['value']; ?></h2>
                        <small><?php echo Text::_($card['label']); ?></small>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="btn-toolbar mb-3 gap-2">
    <a href="<?php echo Route::_('index.php?option=com_removesampledata&view=log'); ?>" class="btn btn-secondary">
        <span class="icon-loop" aria-hidden="true"></span>
        <?php echo Text::_('COM_REMOVESAMPLEDATA_LOG_REFRESH'); ?>
    </a>
    <a href="<?php echo Route::_('index.php?option=com_removesampledata&task=log.download&' . $token . '=1'); ?>" class="btn btn-success">
        <span class="icon-download" aria-hidden="true"></span>
        <?php echo Text::_('COM_REMOVESAMPLEDATA_LOG_DOWNLOAD'); ?>
    </a>
    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rsd-clear-modal">
        <span class="icon-trash" aria-hidden="true"></span>
        <?php echo Text::_('COM_REMOVESAMPLEDATA_LOG_CLEAR_ALL'); ?>
    </button>
</div>

<div class="input-group mb-2">
    <span class="input-group-text icon-search"></span>
    <input type="text"
           id="rsd-log-filter-input"
           class="form-control"
           placeholder="<?php echo $this->escape(Text::_('COM_REMOVESAMPLEDATA_LOG_FILTER_PLACEHOLDER')); ?>">
</div>

<div class="table-responsive">
    <table class="table table-sm table-striped" id="rsd-log-table">
        <thead>
            <tr>
                <th><?php echo Text::_('COM_REMOVESAMPLEDATA_LOG_COL_CREATED'); ?></th>
                <th><?php echo Text::_('COM_REMOVESAMPLEDATA_LOG_COL_LEVEL'); ?></th>
                <th><?php echo Text::_('COM_REMOVESAMPLEDATA_LOG_COL_RUN'); ?></th>
                <th><?php echo Text::_('COM_REMOVESAMPLEDATA_LOG_COL_ACTION'); ?></th>
                <th><?php echo Text::_('COM_REMOVESAMPLEDATA_LOG_COL_ITEM'); ?></th>
                <th><?php echo Text::_('COM_REMOVESAMPLEDATA_LOG_COL_DETAIL'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$entries): ?>
                <tr><td colspan="6"><?php echo Text::_('COM_REMOVESAMPLEDATA_LOG_EMPTY'); ?></td></tr>
            <?php else: foreach ($entries as $entry): ?>
                <?php
                $levelClass = match ($entry->level) {
                    'success' => 'bg-success',
                    'warning' => 'bg-warning text-dark',
                    'error'   => 'bg-danger',
                    default   => 'bg-secondary',
                };
                ?>
                <tr data-level="<?php echo $this->escape($entry->level); ?>"
                    data-created="<?php echo $this->escape($entry->created); ?>">
                    <td><code><?php echo $this->escape($entry->created); ?></code></td>
                    <td><span class="badge <?php echo $levelClass; ?>"><?php echo $this->escape($entry->level); ?></span></td>
                    <td><code class="small"><?php echo $this->escape(substr($entry->run_kind . ':' . $entry->run_id, 0, 14)); ?>&hellip;</code></td>
                    <td><?php echo $this->escape($entry->action); ?></td>
                    <td>
                        <?php if ($entry->item_type): ?>
                            <span class="badge bg-secondary"><?php echo $this->escape($entry->item_type); ?></span>
                        <?php endif; ?>
                        <?php echo $this->escape($entry->item_label); ?>
                        <?php if ($entry->item_id): ?>
                            <code class="text-muted small">#<?php echo $this->escape($entry->item_id); ?></code>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($entry->detail): ?>
                            <details>
                                <summary><?php echo Text::_('COM_REMOVESAMPLEDATA_LOG_DETAIL_TOGGLE'); ?></summary>
                                <pre class="border p-2 small mb-0"><?php echo $this->escape($entry->detail); ?></pre>
                            </details>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<!-- Clear-all modal -->
<div class="modal fade" id="rsd-clear-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="<?php echo Route::_('index.php?option=com_removesampledata&task=log.clear'); ?>" method="post">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo Text::_('COM_REMOVESAMPLEDATA_LOG_CLEAR_TITLE'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><?php echo Text::_('COM_REMOVESAMPLEDATA_LOG_CLEAR_BODY'); ?></p>
                </div>
                <div class="modal-footer">
                    <?php echo HTMLHelper::_('form.token'); ?>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo Text::_('JCANCEL'); ?></button>
                    <button type="submit" class="btn btn-danger"><?php echo Text::_('COM_REMOVESAMPLEDATA_LOG_CLEAR_CONFIRM'); ?></button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const input = document.getElementById('rsd-log-filter-input');
    const rows  = document.querySelectorAll('#rsd-log-table tbody tr');
    if (input) {
        input.addEventListener('input', () => {
            const q = input.value.toLowerCase();
            rows.forEach(r => {
                r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }
    document.querySelectorAll('.rsd-stat-filter').forEach(el => {
        el.addEventListener('click', (e) => {
            e.preventDefault();
            const filter = el.dataset.filter || '';
            const cutoff = filter === 'last24'
                ? new Date(Date.now() - 24 * 3600 * 1000)
                : null;
            rows.forEach(r => {
                if (!filter) {
                    r.style.display = '';
                    return;
                }
                if (filter === 'last24') {
                    const c = new Date(r.dataset.created.replace(' ', 'T'));
                    r.style.display = c >= cutoff ? '' : 'none';
                    return;
                }
                r.style.display = r.dataset.level === filter ? '' : 'none';
            });
        });
    });
})();
</script>
