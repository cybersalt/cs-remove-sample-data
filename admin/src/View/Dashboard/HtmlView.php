<?php
/**
 * @package    Cybersalt.Component.Removesampledata
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

namespace Cybersalt\Component\Removesampledata\Administrator\View\Dashboard;

\defined('_JEXEC') or die;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    public array $summary = [];

    public function display($tpl = null)
    {
        $this->summary = $this->getModel()->getSummary();

        ToolbarHelper::title(Text::_('COM_REMOVESAMPLEDATA') . ' &mdash; ' . Text::_('COM_REMOVESAMPLEDATA_DASHBOARD'), 'trash');

        $toolbar = $this->getDocument()->getToolbar();
        $toolbar->linkButton('items', Text::_('COM_REMOVESAMPLEDATA_BROWSE_ITEMS'))
            ->url('index.php?option=com_removesampledata&view=items')
            ->icon('icon-list');
        $toolbar->linkButton('log', Text::_('COM_REMOVESAMPLEDATA_VIEW_LOG'))
            ->url('index.php?option=com_removesampledata&view=log')
            ->icon('icon-pencil-2');
        $toolbar->preferences('com_removesampledata');
        $toolbar->help('', false, 'https://cybersalt.com');

        /** @var HtmlDocument $doc */
        $doc = $this->getDocument();
        $doc->setTitle(Text::_('COM_REMOVESAMPLEDATA_VENDOR_TITLE') . ' &mdash; ' . Text::_('COM_REMOVESAMPLEDATA_DASHBOARD'));

        parent::display($tpl);
    }
}
