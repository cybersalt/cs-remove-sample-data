<?php
/**
 * @package    Cybersalt.Component.Removesampledata
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

namespace Cybersalt\Component\Removesampledata\Administrator\View\Result;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    public array $result  = [];
    public array $entries = [];

    public function display($tpl = null)
    {
        $model        = $this->getModel();
        $this->result = $model->getResult();
        $runId        = $this->result['run_id'] ?? '';
        $this->entries = $model->getRunEntries((string) $runId);

        ToolbarHelper::title(Text::_('COM_REMOVESAMPLEDATA') . ' &mdash; ' . Text::_('COM_REMOVESAMPLEDATA_RESULT_TITLE'), 'checkin');

        $toolbar = $this->getDocument()->getToolbar();
        $toolbar->linkButton('back', Text::_('COM_REMOVESAMPLEDATA_BACK_TO_DASHBOARD'))
            ->url('index.php?option=com_removesampledata&view=dashboard')
            ->icon('icon-arrow-left');
        $toolbar->linkButton('log', Text::_('COM_REMOVESAMPLEDATA_VIEW_LOG'))
            ->url('index.php?option=com_removesampledata&view=log')
            ->icon('icon-list');

        $this->getDocument()->setTitle(Text::_('COM_REMOVESAMPLEDATA_VENDOR_TITLE') . ' &mdash; ' . Text::_('COM_REMOVESAMPLEDATA_RESULT_TITLE'));

        $wa = $this->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('com_removesampledata.admin', 'com_removesampledata/admin.css');

        parent::display($tpl);
    }
}
