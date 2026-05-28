<?php
/**
 * @package    Cybersalt.Component.Removesampledata
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

namespace Cybersalt\Component\Removesampledata\Administrator\Controller;

\defined('_JEXEC') or die;

use Cybersalt\Component\Removesampledata\Administrator\Helper\PermissionHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;

class LogController extends BaseController
{
    public function clear(): void
    {
        PermissionHelper::requireManage();
        $this->checkToken();
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $db->setQuery('TRUNCATE TABLE ' . $db->quoteName('#__removesampledata_log'))->execute();
        $this->app->enqueueMessage(Text::_('COM_REMOVESAMPLEDATA_LOG_CLEARED'), 'success');
        $this->setRedirect(Route::_('index.php?option=com_removesampledata&view=log', false));
    }

    public function download(): void
    {
        PermissionHelper::requireManage();
        $this->checkToken('get');
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->createQuery()
            ->select('*')
            ->from($db->quoteName('#__removesampledata_log'))
            ->order($db->quoteName('id') . ' DESC');
        $rows = $db->setQuery($query)->loadAssocList() ?: [];

        $filename = 'removesampledata-log-' . date('Ymd-His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '-', $filename) . '"');
        $out = fopen('php://output', 'w');

        // CSV-injection sanitiser. Cells that begin with =, +, -, @, tab or
        // carriage return are interpreted as formulas by Excel/LibreOffice/
        // Numbers when the CSV is opened. Log rows include user-influenced
        // strings (article titles, exception messages) so we prefix any such
        // cell with a single apostrophe before writing.
        $sanitize = static function ($cell) {
            $s = (string) $cell;
            if ($s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                return "'" . $s;
            }
            return $s;
        };

        if ($rows) {
            fputcsv($out, array_map($sanitize, array_keys($rows[0])));
            foreach ($rows as $row) {
                fputcsv($out, array_map($sanitize, $row));
            }
        } else {
            fputcsv($out, ['id', 'created', 'user_id', 'run_id', 'run_kind', 'level', 'action', 'item_type', 'item_id', 'item_label', 'detail']);
        }
        fclose($out);
        $this->app->close();
    }
}
