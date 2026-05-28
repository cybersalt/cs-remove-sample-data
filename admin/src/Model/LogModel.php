<?php
/**
 * @package    Cybersalt.Component.Removesampledata
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

namespace Cybersalt\Component\Removesampledata\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\DatabaseInterface;

class LogModel extends BaseDatabaseModel
{
    public function getEntries(int $limit = 500): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->createQuery()
            ->select('*')
            ->from($db->quoteName('#__removesampledata_log'))
            ->order($db->quoteName('id') . ' DESC');
        $db->setQuery($query, 0, $limit);
        return $db->loadObjectList() ?: [];
    }

    public function getStats(): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $stats = [
            'total'    => 0,
            'last_24h' => 0,
            'success'  => 0,
            'warning'  => 0,
            'error'    => 0,
        ];
        try {
            $stats['total']    = (int) $db->setQuery('SELECT COUNT(*) FROM ' . $db->quoteName('#__removesampledata_log'))->loadResult();
            $stats['last_24h'] = (int) $db->setQuery(
                'SELECT COUNT(*) FROM ' . $db->quoteName('#__removesampledata_log')
                . ' WHERE ' . $db->quoteName('created') . ' >= ' . $db->quote(date('Y-m-d H:i:s', strtotime('-24 hours')))
            )->loadResult();
            foreach (['success', 'warning', 'error'] as $level) {
                $stats[$level] = (int) $db->setQuery(
                    'SELECT COUNT(*) FROM ' . $db->quoteName('#__removesampledata_log')
                    . ' WHERE ' . $db->quoteName('level') . ' = ' . $db->quote($level)
                )->loadResult();
            }
        } catch (\Throwable $e) {
            // table not present yet — return zeros
        }
        return $stats;
    }
}
