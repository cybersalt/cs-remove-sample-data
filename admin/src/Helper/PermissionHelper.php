<?php
/**
 * @package    Cybersalt.Component.Removesampledata
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

namespace Cybersalt\Component\Removesampledata\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

class PermissionHelper
{
    public static function requireManage(): void
    {
        $user = Factory::getApplication()->getIdentity();
        if (!$user || !$user->authorise('core.manage', 'com_removesampledata')) {
            throw new \Joomla\CMS\Access\Exception\NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    public static function requireScan(): void
    {
        self::requireManage();
        $user = Factory::getApplication()->getIdentity();
        if (!$user->authorise('removesampledata.scan', 'com_removesampledata')
            && !$user->authorise('core.admin', 'com_removesampledata')) {
            throw new \Joomla\CMS\Access\Exception\NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    public static function requireRemove(): void
    {
        self::requireManage();
        $user = Factory::getApplication()->getIdentity();
        if (!$user->authorise('removesampledata.remove', 'com_removesampledata')
            && !$user->authorise('core.admin', 'com_removesampledata')) {
            throw new \Joomla\CMS\Access\Exception\NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }
}
