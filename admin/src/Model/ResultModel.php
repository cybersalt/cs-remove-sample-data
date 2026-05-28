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

class ResultModel extends BaseDatabaseModel
{
    public function getResult(): array
    {
        $state = Factory::getApplication()->getUserState('com_removesampledata.last_run');
        return is_array($state) ? $state : [];
    }

    public function getRunEntries(string $runId): array
    {
        if ($runId === '') {
            return [];
        }
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->createQuery()
            ->select('*')
            ->from($db->quoteName('#__removesampledata_log'))
            ->where($db->quoteName('run_id') . ' = ' . $db->quote($runId))
            ->order($db->quoteName('id') . ' ASC');
        return $db->setQuery($query)->loadObjectList() ?: [];
    }
}
