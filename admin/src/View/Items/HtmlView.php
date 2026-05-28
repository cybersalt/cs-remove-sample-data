<?php
/**
 * @package    Cybersalt.Component.Removesampledata
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

namespace Cybersalt\Component\Removesampledata\Administrator\View\Items;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Registry\Registry;

class HtmlView extends BaseHtmlView
{
    public array      $items         = [];
    public Pagination $pagination;
    public Registry   $state;
    public            $filterForm;
    public array      $activeFilters = [];
    public array      $stats         = [];

    public function display($tpl = null)
    {
        /** @var \Cybersalt\Component\Removesampledata\Administrator\Model\ItemsModel $model */
        $model               = $this->getModel();
        $this->items         = $model->getItems();
        $this->pagination    = $model->getPagination();
        $this->state         = $model->getState();
        $this->filterForm    = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();
        $this->stats         = $model->getStats();

        ToolbarHelper::title(Text::_('COM_REMOVESAMPLEDATA') . ' &mdash; ' . Text::_('COM_REMOVESAMPLEDATA_ITEMS_TITLE'), 'list');

        $toolbar = $this->getDocument()->getToolbar();
        $toolbar->linkButton('dashboard', Text::_('COM_REMOVESAMPLEDATA_BACK_TO_DASHBOARD'))
            ->url('index.php?option=com_removesampledata&view=dashboard')
            ->icon('icon-arrow-left');
        $toolbar->linkButton('scan-blog', Text::_('COM_REMOVESAMPLEDATA_ITEMS_GO_TO_SCAN_BLOG'))
            ->url('index.php?option=com_removesampledata&view=scan&kind=blog')
            ->icon('icon-trash');
        $toolbar->linkButton('scan-multilang', Text::_('COM_REMOVESAMPLEDATA_ITEMS_GO_TO_SCAN_MULTILANG'))
            ->url('index.php?option=com_removesampledata&view=scan&kind=multilang')
            ->icon('icon-trash');

        $this->getDocument()->setTitle(Text::_('COM_REMOVESAMPLEDATA_VENDOR_TITLE') . ' &mdash; ' . Text::_('COM_REMOVESAMPLEDATA_ITEMS_TITLE'));

        $wa = $this->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('com_removesampledata.admin', 'com_removesampledata/admin.css');

        parent::display($tpl);
    }
}
