<?php
/**
 * @package    Cybersalt.Component.Removesampledata
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 *
 * @var \Cybersalt\Component\Removesampledata\Administrator\View\Dashboard\HtmlView $this
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$blog      = $this->summary['blog']      ?? ['summary' => ['total' => 0, 'edited' => 0]];
$multilang = $this->summary['multilang'] ?? ['summary' => ['total' => 0, 'edited' => 0]];
?>

<div class="alert alert-warning" role="alert">
    <strong><?php echo Text::_('COM_REMOVESAMPLEDATA_BACKUP_WARNING_TITLE'); ?></strong>
    <?php echo Text::_('COM_REMOVESAMPLEDATA_BACKUP_WARNING_BODY'); ?>
</div>

<p><?php echo Text::_('COM_REMOVESAMPLEDATA_DASHBOARD_INTRO'); ?></p>

<div class="row">
    <div class="col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <h3 class="card-title"><?php echo Text::_('COM_REMOVESAMPLEDATA_CARD_BLOG_TITLE'); ?></h3>
                <p class="card-text"><?php echo Text::_('COM_REMOVESAMPLEDATA_CARD_BLOG_DESC'); ?></p>
                <p class="fs-5">
                    <a href="<?php echo Route::_('index.php?option=com_removesampledata&view=items&filter[source]=blog'); ?>"
                       class="text-decoration-none"
                       title="<?php echo $this->escape(Text::_('COM_REMOVESAMPLEDATA_BADGE_CLICK_HINT')); ?>">
                        <span class="badge bg-primary"><?php echo (int) $blog['summary']['total']; ?> <?php echo Text::_('COM_REMOVESAMPLEDATA_ITEMS_DETECTED'); ?></span>
                    </a>
                    <?php if (!empty($blog['summary']['edited'])): ?>
                        <a href="<?php echo Route::_('index.php?option=com_removesampledata&view=items&filter[source]=blog&filter[status]=edited'); ?>"
                           class="text-decoration-none"
                           title="<?php echo $this->escape(Text::_('COM_REMOVESAMPLEDATA_EDITED_HINT')); ?>">
                            <span class="badge bg-warning text-dark">
                                <?php echo (int) $blog['summary']['edited']; ?> <?php echo Text::_('COM_REMOVESAMPLEDATA_ITEMS_EDITED'); ?>
                            </span>
                        </a>
                    <?php endif; ?>
                </p>
                <a href="<?php echo Route::_('index.php?option=com_removesampledata&view=scan&kind=blog'); ?>" class="btn btn-primary">
                    <span class="icon-search" aria-hidden="true"></span>
                    <?php echo Text::_('COM_REMOVESAMPLEDATA_SCAN_AND_REMOVE'); ?>
                </a>
                <a href="<?php echo Route::_('index.php?option=com_removesampledata&view=items&filter[source]=blog'); ?>" class="btn btn-secondary">
                    <span class="icon-list" aria-hidden="true"></span>
                    <?php echo Text::_('COM_REMOVESAMPLEDATA_BROWSE_ITEMS'); ?>
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <h3 class="card-title"><?php echo Text::_('COM_REMOVESAMPLEDATA_CARD_MULTILANG_TITLE'); ?></h3>
                <p class="card-text"><?php echo Text::_('COM_REMOVESAMPLEDATA_CARD_MULTILANG_DESC'); ?></p>
                <p class="fs-5">
                    <a href="<?php echo Route::_('index.php?option=com_removesampledata&view=items&filter[source]=multilang'); ?>"
                       class="text-decoration-none"
                       title="<?php echo $this->escape(Text::_('COM_REMOVESAMPLEDATA_BADGE_CLICK_HINT')); ?>">
                        <span class="badge bg-primary"><?php echo (int) $multilang['summary']['total']; ?> <?php echo Text::_('COM_REMOVESAMPLEDATA_ITEMS_DETECTED'); ?></span>
                    </a>
                </p>
                <a href="<?php echo Route::_('index.php?option=com_removesampledata&view=scan&kind=multilang'); ?>" class="btn btn-primary">
                    <span class="icon-search" aria-hidden="true"></span>
                    <?php echo Text::_('COM_REMOVESAMPLEDATA_SCAN_AND_REMOVE'); ?>
                </a>
                <a href="<?php echo Route::_('index.php?option=com_removesampledata&view=items&filter[source]=multilang'); ?>" class="btn btn-secondary">
                    <span class="icon-list" aria-hidden="true"></span>
                    <?php echo Text::_('COM_REMOVESAMPLEDATA_BROWSE_ITEMS'); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body">
        <h4 class="card-title"><?php echo Text::_('COM_REMOVESAMPLEDATA_HELP_TITLE'); ?></h4>
        <p><?php echo Text::_('COM_REMOVESAMPLEDATA_HELP_BODY'); ?></p>
        <a class="btn btn-secondary" href="https://cybersalt.com" target="_blank" rel="noopener">
            <?php echo Text::_('COM_REMOVESAMPLEDATA_GET_HELP'); ?>
        </a>
    </div>
</div>
