<?php
/**
 * @package    Cybersalt.Component.Removesampledata
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 *
 * @var \Cybersalt\Component\Removesampledata\Administrator\View\Items\HtmlView $this
 */

\defined('_JEXEC') or die;

use Cybersalt\Component\Removesampledata\Administrator\Helper\EditUrlHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.multiselect');

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
?>

<form action="<?php echo Route::_('index.php?option=com_removesampledata&view=items'); ?>"
      method="post"
      name="adminForm"
      id="adminForm">

    <div class="row mb-3">
        <div class="col-md-12">
            <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>
        </div>
    </div>

    <div class="row mb-3 g-2">
        <div class="col-sm">
            <a href="<?php echo Route::_('index.php?option=com_removesampledata&view=items'); ?>"
               class="text-decoration-none">
                <div class="card text-white bg-primary">
                    <div class="card-body py-2">
                        <h2 class="mb-0"><?php echo (int) $this->stats['total']; ?></h2>
                        <small><?php echo Text::_('COM_REMOVESAMPLEDATA_ITEMS_STAT_TOTAL'); ?></small>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm">
            <a href="<?php echo Route::_('index.php?option=com_removesampledata&view=items&filter[status]=edited'); ?>"
               class="text-decoration-none">
                <div class="card text-dark bg-warning">
                    <div class="card-body py-2">
                        <h2 class="mb-0"><?php echo (int) $this->stats['edited']; ?></h2>
                        <small><?php echo Text::_('COM_REMOVESAMPLEDATA_ITEMS_STAT_EDITED'); ?></small>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm">
            <a href="<?php echo Route::_('index.php?option=com_removesampledata&view=items&filter[source]=blog'); ?>"
               class="text-decoration-none">
                <div class="card text-white bg-success">
                    <div class="card-body py-2">
                        <h2 class="mb-0"><?php echo (int) ($this->stats['by_source']['blog'] ?? 0); ?></h2>
                        <small><?php echo Text::_('COM_REMOVESAMPLEDATA_SOURCE_BLOG'); ?></small>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm">
            <a href="<?php echo Route::_('index.php?option=com_removesampledata&view=items&filter[source]=multilang'); ?>"
               class="text-decoration-none">
                <div class="card text-white bg-info">
                    <div class="card-body py-2">
                        <h2 class="mb-0"><?php echo (int) ($this->stats['by_source']['multilang'] ?? 0); ?></h2>
                        <small><?php echo Text::_('COM_REMOVESAMPLEDATA_SOURCE_MULTILANG'); ?></small>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <?php if (empty($this->items)): ?>
        <div class="alert alert-info">
            <?php echo Text::_('COM_REMOVESAMPLEDATA_ITEMS_NONE_MATCH'); ?>
        </div>
    <?php else: ?>
        <table class="table" id="rsd-items-table">
            <caption class="visually-hidden"><?php echo Text::_('COM_REMOVESAMPLEDATA_ITEMS_TITLE'); ?></caption>
            <thead>
                <tr>
                    <th scope="col" style="width:1%"><?php echo Text::_('COM_REMOVESAMPLEDATA_COL_STATUS'); ?></th>
                    <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_REMOVESAMPLEDATA_COL_SOURCE', 'source', $listDirn, $listOrder); ?></th>
                    <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_REMOVESAMPLEDATA_COL_TYPE', 'type', $listDirn, $listOrder); ?></th>
                    <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_REMOVESAMPLEDATA_COL_TITLE', 'title', $listDirn, $listOrder); ?></th>
                    <th scope="col"><?php echo Text::_('COM_REMOVESAMPLEDATA_COL_DETAILS'); ?></th>
                    <th scope="col" style="width:1%"><?php echo Text::_('JGRID_HEADING_ID'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($this->items as $item):
                    $editUrl = EditUrlHelper::for($item['type'], (int) $item['id'], $item['extras'] ?? []);
                ?>
                    <tr>
                        <td>
                            <?php if (!empty($item['edited'])): ?>
                                <span class="badge bg-warning text-dark"
                                      title="<?php echo $this->escape(implode(', ', $item['edit_reasons'] ?? [])); ?>">
                                    <?php echo Text::_('COM_REMOVESAMPLEDATA_STATUS_EDITED'); ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><?php echo Text::_('COM_REMOVESAMPLEDATA_STATUS_PRISTINE'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-secondary">
                                <?php echo Text::_($item['source'] === 'blog' ? 'COM_REMOVESAMPLEDATA_SOURCE_BLOG' : 'COM_REMOVESAMPLEDATA_SOURCE_MULTILANG'); ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $typeKey = 'COM_REMOVESAMPLEDATA_TYPE_' . strtoupper($item['type']);
                            echo Text::_($typeKey);
                            ?>
                        </td>
                        <td>
                            <?php if ($editUrl): ?>
                                <a href="<?php echo $this->escape($editUrl); ?>"
                                   title="<?php echo $this->escape(Text::_('COM_REMOVESAMPLEDATA_OPEN_IN_JOOMLA')); ?>">
                                    <strong><?php echo $this->escape((string) $item['title']); ?></strong>
                                </a>
                            <?php else: ?>
                                <strong><?php echo $this->escape((string) $item['title']); ?></strong>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $bits   = $item['details'] ? explode(' · ', $item['details']) : [];
                            $pretty = [];
                            foreach ($bits as $b) {
                                if (strpos($b, '=') !== false) {
                                    [$k, $v]  = explode('=', $b, 2);
                                    $pretty[] = '<span class="text-muted small">' . $this->escape($k) . ':</span> <code>' . $this->escape($v) . '</code>';
                                }
                            }
                            echo implode(' &nbsp; ', $pretty);
                            ?>
                        </td>
                        <td><code class="text-muted small">#<?php echo (int) $item['id']; ?></code></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php echo $this->pagination->getListFooter(); ?>
    <?php endif; ?>

    <input type="hidden" name="task" value="">
    <input type="hidden" name="boxchecked" value="0">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
