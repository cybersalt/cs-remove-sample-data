<?php
/**
 * @package    Cybersalt.Component.Removesampledata
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 *
 * @var \Cybersalt\Component\Removesampledata\Administrator\View\Result\HtmlView $this
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$result   = $this->result;
$entries  = $this->entries;
$counts   = $result['counts'] ?? ['removed' => 0, 'failed' => 0];
$kind     = $result['kind']   ?? '';
$runId    = $result['run_id'] ?? '';
?>

<?php if (!$result): ?>
    <div class="alert alert-info">
        <?php echo Text::_('COM_REMOVESAMPLEDATA_RESULT_NO_RUN'); ?>
    </div>
    <a href="<?php echo Route::_('index.php?option=com_removesampledata&view=dashboard'); ?>" class="btn btn-primary">
        <?php echo Text::_('COM_REMOVESAMPLEDATA_BACK_TO_DASHBOARD'); ?>
    </a>
<?php else: ?>
    <div class="card mb-3">
        <div class="card-body">
            <h3 class="card-title">
                <?php echo Text::sprintf(
                    $kind === 'multilang'
                        ? 'COM_REMOVESAMPLEDATA_RESULT_HEADING_MULTILANG'
                        : 'COM_REMOVESAMPLEDATA_RESULT_HEADING_BLOG',
                    (int) $counts['removed']
                ); ?>
            </h3>
            <p class="fs-5">
                <span class="badge bg-success"><?php echo (int) $counts['removed']; ?> <?php echo Text::_('COM_REMOVESAMPLEDATA_RESULT_REMOVED'); ?></span>
                <?php if (!empty($counts['failed'])): ?>
                    <span class="badge bg-danger"><?php echo (int) $counts['failed']; ?> <?php echo Text::_('COM_REMOVESAMPLEDATA_RESULT_FAILED'); ?></span>
                <?php endif; ?>
                <span class="badge bg-secondary"><?php echo Text::_('COM_REMOVESAMPLEDATA_RESULT_RUN_ID'); ?> <code><?php echo $this->escape((string) $runId); ?></code></span>
            </p>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <strong><?php echo Text::_('COM_REMOVESAMPLEDATA_RESULT_ACTIONS_HEADING'); ?></strong>
            <span class="badge bg-secondary"><?php echo count($entries); ?></span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th><?php echo Text::_('COM_REMOVESAMPLEDATA_LOG_COL_CREATED'); ?></th>
                        <th><?php echo Text::_('COM_REMOVESAMPLEDATA_LOG_COL_LEVEL'); ?></th>
                        <th><?php echo Text::_('COM_REMOVESAMPLEDATA_LOG_COL_ACTION'); ?></th>
                        <th><?php echo Text::_('COM_REMOVESAMPLEDATA_LOG_COL_ITEM'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry):
                        $levelClass = match ($entry->level) {
                            'success' => 'bg-success',
                            'warning' => 'bg-warning text-dark',
                            'error'   => 'bg-danger',
                            default   => 'bg-secondary',
                        };
                    ?>
                        <tr>
                            <td><code><?php echo $this->escape($entry->created); ?></code></td>
                            <td><span class="badge <?php echo $levelClass; ?>"><?php echo $this->escape($entry->level); ?></span></td>
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
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
