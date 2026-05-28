<?php
/**
 * @package    Cybersalt.Component.Removesampledata
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

return new class () implements InstallerScriptInterface {

    public const MIN_PHP = '8.1.0';
    public const MIN_JOOMLA = '5.0.0';

    public function install(InstallerAdapter $parent): bool { return true; }
    public function update(InstallerAdapter $parent): bool { return true; }
    public function uninstall(InstallerAdapter $parent): bool { return true; }

    public function preflight(string $type, InstallerAdapter $parent): bool
    {
        if (!in_array($type, ['install', 'update', 'discover_install'], true)) {
            return true;
        }
        if (version_compare(PHP_VERSION, self::MIN_PHP, '<')) {
            Factory::getApplication()->enqueueMessage(
                'Remove Sample Data requires PHP ' . self::MIN_PHP . ' or higher. You are running PHP ' . PHP_VERSION . '.',
                'error'
            );
            return false;
        }
        $joomlaVersion = (new \Joomla\CMS\Version())->getShortVersion();
        if (version_compare($joomlaVersion, self::MIN_JOOMLA, '<')) {
            Factory::getApplication()->enqueueMessage(
                'Remove Sample Data requires Joomla ' . self::MIN_JOOMLA . ' or higher. You are running Joomla ' . $joomlaVersion . '.',
                'error'
            );
            return false;
        }
        return true;
    }

    public function postflight(string $type, InstallerAdapter $parent): bool
    {
        if (!in_array($type, ['install', 'update', 'discover_install'], true)) {
            return true;
        }
        Factory::getApplication()->getLanguage()->load('com_removesampledata.sys', JPATH_ADMINISTRATOR, null, true, true);

        $messageKey = $type === 'update'
            ? 'COM_REMOVESAMPLEDATA_POSTINSTALL_UPDATED'
            : 'COM_REMOVESAMPLEDATA_POSTINSTALL_INSTALLED';

        $url   = 'index.php?option=com_removesampledata';
        $title = htmlspecialchars(Text::_('COM_REMOVESAMPLEDATA'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $body  = htmlspecialchars(Text::_($messageKey), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $backup = htmlspecialchars(Text::_('COM_REMOVESAMPLEDATA_POSTINSTALL_BACKUP_REMINDER'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $open  = htmlspecialchars(Text::_('COM_REMOVESAMPLEDATA_POSTINSTALL_OPEN'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        echo '<div class="card mb-3" style="margin: 20px 0;">'
            . '<div class="card-body">'
            . '<h3 class="card-title">' . $title . '</h3>'
            . '<p class="card-text">' . $body . '</p>'
            . '<div class="alert alert-warning mb-3" role="alert">' . $backup . '</div>'
            . '<a href="' . $url . '" class="btn btn-primary text-white">' . $open . '</a>'
            . '</div></div>';

        return true;
    }
};
