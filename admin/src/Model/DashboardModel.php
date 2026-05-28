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

class DashboardModel extends BaseDatabaseModel
{
    public function getSummary(): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $blog = (new BlogDetector($db))->detect();
        $ml   = (new MultilangDetector($db))->detect();

        return [
            'blog'      => [
                'detected' => $blog,
                'summary'  => (new BlogDetector($db))->summary($blog),
            ],
            'multilang' => [
                'detected' => $ml,
                'summary'  => (new MultilangDetector($db))->summary($ml),
            ],
        ];
    }
}
