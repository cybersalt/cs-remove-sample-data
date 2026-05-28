<?php
/**
 * @package    Cybersalt.Component.Removesampledata
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 *
 * Returns a fresh CSRF form token for the current admin session. The
 * scan view's JS calls this on submit so the request always carries the
 * current session's token, regardless of how long the user spent on
 * the page. Joomla 6 dropped plg_system_token; this is our replacement.
 */

namespace Cybersalt\Component\Removesampledata\Administrator\Controller;

\defined('_JEXEC') or die;

use Cybersalt\Component\Removesampledata\Administrator\Helper\PermissionHelper;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

class TokenController extends BaseController
{
    /**
     * NOTE: do NOT call $this->checkToken() here — providing the token
     * is the whole point of this endpoint. The authentication boundary
     * is the admin session cookie + the requireManage() permission gate.
     */
    public function fetch(): void
    {
        PermissionHelper::requireManage();

        $token = Session::getFormToken();

        $this->app->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
        $this->app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
        $this->app->setHeader('Pragma', 'no-cache', true);
        $this->app->setHeader('Expires', '0', true);
        $this->app->setHeader('X-Content-Type-Options', 'nosniff', true);
        $this->app->sendHeaders();

        echo $token;
        $this->app->close();
    }
}
