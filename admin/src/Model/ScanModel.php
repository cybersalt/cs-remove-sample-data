<?php
/**
 * @package    Cybersalt.Component.Removesampledata
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

namespace Cybersalt\Component\Removesampledata\Administrator\Model;

\defined('_JEXEC') or die;

use Cybersalt\Component\Removesampledata\Administrator\Service\BlogDetector;
use Cybersalt\Component\Removesampledata\Administrator\Service\MultilangDetector;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\DatabaseInterface;

class ScanModel extends BaseDatabaseModel
{
    public function getDetected(string $kind): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        if ($kind === 'multilang') {
            return (new MultilangDetector($db))->detect();
        }
        return (new BlogDetector($db))->detect();
    }
}
