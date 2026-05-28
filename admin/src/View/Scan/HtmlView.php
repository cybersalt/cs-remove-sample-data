<?php
/**
 * @package    Cybersalt.Component.Removesampledata
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

namespace Cybersalt\Component\Removesampledata\Administrator\View\Scan;

\defined('_JEXEC') or die;

use Cybersalt\Component\Removesampledata\Administrator\Helper\PermissionHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    public string $kind     = 'blog';
    public array  $detected = [];

    public function display($tpl = null)
    {
        // Honour the removesampledata.scan ACL action. The component dispatcher
        // already enforces core.manage; this is the per-view gate that admins
        // can grant separately from the manage privilege via permissions.
        PermissionHelper::requireScan();

        $input      = Factory::getApplication()->getInput();
        $this->kind = $input->getCmd('kind', 'blog') === 'multilang' ? 'multilang' : 'blog';

        $this->detected = $this->getModel()->getDetected($this->kind);

        $titleKey = $this->kind === 'multilang'
            ? 'COM_REMOVESAMPLEDATA_SCAN_MULTILANG_TITLE'
            : 'COM_REMOVESAMPLEDATA_SCAN_BLOG_TITLE';
        ToolbarHelper::title(Text::_('COM_REMOVESAMPLEDATA') . ' &mdash; ' . Text::_($titleKey), 'trash');

        $toolbar = $this->getDocument()->getToolbar();
        $toolbar->linkButton('back', Text::_('COM_REMOVESAMPLEDATA_BACK_TO_DASHBOARD'))
            ->url('index.php?option=com_removesampledata&view=dashboard')
            ->icon('icon-arrow-left');

        $this->getDocument()->setTitle(Text::_('COM_REMOVESAMPLEDATA_VENDOR_TITLE') . ' &mdash; ' . Text::_($titleKey));

        // Inline the JS and CSS directly into the page rather than loading
        // them as separate /media files. Cloudflare (and many proxies)
        // aggressively cache static assets at the URL `admin.js` for up to
        // 30 days regardless of cache-bypass request headers. Joomla 6's
        // WAM doesn't reliably append a cache-busting query string from
        // joomla.asset.json's `version` field, so every release ended up
        // served from an ancient cached copy on the user's edge. Embedding
        // the JS and CSS in the page HTML (which admin pages mark Cache-
        // Control: no-store) guarantees the user always runs the current
        // build's code. The file is the source of truth; this just reads
        // it and emits it inline on each render.
        //
        // SAFETY: these paths MUST remain hardcoded constants. Never accept
        // any portion of these paths from a request, query string, or any
        // other user-influenced source. file_get_contents reading + inline
        // emission is safe only because the file is the extension's own
        // asset; if the path ever becomes dynamic this turns into a
        // file-disclosure / inline-script-injection primitive.
        $jsPath  = JPATH_ROOT . '/media/com_removesampledata/js/admin.js';
        $cssPath = JPATH_ROOT . '/media/com_removesampledata/css/admin.css';
        if (is_file($cssPath)) {
            $this->getDocument()->addStyleDeclaration(file_get_contents($cssPath));
        }
        if (is_file($jsPath)) {
            $this->getDocument()->addScriptDeclaration(file_get_contents($jsPath));
        }
        // Keep the admin session alive while the user reviews the scan list
        // so the form's CSRF token doesn't expire under their feet.
        HTMLHelper::_('behavior.keepalive');

        parent::display($tpl);
    }
}
