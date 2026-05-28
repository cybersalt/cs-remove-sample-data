<?php
/**
 * @package    Cybersalt.Component.Removesampledata
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

namespace Cybersalt\Component\Removesampledata\Administrator\Controller;

\defined('_JEXEC') or die;

use Cybersalt\Component\Removesampledata\Administrator\Helper\PermissionHelper;
use Joomla\CMS\MVC\Controller\BaseController;

class DisplayController extends BaseController
{
    protected $default_view = 'dashboard';

    public function display($cachable = false, $urlparams = [])
    {
        PermissionHelper::requireManage();
        return parent::display($cachable, $urlparams);
    }
}
