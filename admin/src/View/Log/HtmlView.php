<?php
/**
 * @package    Cybersalt.Component.Removesampledata
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

namespace Cybersalt\Component\Removesampledata\Administrator\View\Log;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    public array $entries = [];
    public array $stats   = [];

    public function display($tpl = null)
    {
        $model         = $this->getModel();
        $this->entries = $model->getEntries(500);
        $this->stats   = $model->getStats();

        ToolbarHelper::title(Text::_('COM_REMOVESAMPLEDATA') . ' &mdash; ' . Text::_('COM_REMOVESAMPLEDATA_LOG_TITLE'), 'list');

        $toolbar = $this->getDocument()->getToolbar();
        $toolbar->linkButton('back', Text::_('COM_REMOVESAMPLEDATA_BACK_TO_DASHBOARD'))
            ->url('index.php?option=com_removesampledata&view=dashboard')
            ->icon('icon-arrow-left');

        $this->getDocument()->setTitle(Text::_('COM_REMOVESAMPLEDATA_VENDOR_TITLE') . ' &mdash; ' . Text::_('COM_REMOVESAMPLEDATA_LOG_TITLE'));

        $wa = $this->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('com_removesampledata.admin', 'com_removesampledata/admin.css');

        parent::display($tpl);
    }
}
