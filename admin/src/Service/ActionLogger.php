<?php
/**
 * @package    Cybersalt.Component.Removesampledata
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

namespace Cybersalt\Component\Removesampledata\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

class ActionLogger
{
    public const LEVEL_INFO    = 'info';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_ERROR   = 'error';
    public const LEVEL_SUCCESS = 'success';

    private DatabaseInterface $db;
    private string $runId;
    private string $runKind;
    private int $userId;

    public function __construct(DatabaseInterface $db, string $runKind = 'scan')
    {
        $this->db      = $db;
        $this->runKind = $runKind;
        $this->runId   = bin2hex(random_bytes(16));
        $this->runId   = substr($this->runId, 0, 8) . '-' . substr($this->runId, 8, 4)
            . '-' . substr($this->runId, 12, 4) . '-' . substr($this->runId, 16, 4)
            . '-' . substr($this->runId, 20, 12);

        $user         = Factory::getApplication()->getIdentity();
        $this->userId = $user ? (int) $user->id : 0;
    }

    public function getRunId(): string
    {
        return $this->runId;
    }

    public function log(string $level, string $action, string $itemType = '', string $itemId = '', string $itemLabel = '', $detail = null): void
    {
        // Assign every value to be bound to a local variable first.
        // DatabaseQuery::bind() takes its $value parameter by reference,
        // and PHP 8.x throws a hard Error if you pass a function-call
        // return value, a method-chain result, or a ternary expression
        // straight to a `&$value` parameter (e.g. `Factory::getDate()->toSql()`,
        // `substr(...)`, `is_string(...) ? ... : ...`). When that happens
        // inside the very first log call at the top of BlogRemover::remove(),
        // the whole removal aborts and the caller's catch tries to log the
        // exception, hitting the same Error and propagating an ugly stack
        // trace to the user. Variables sidestep this entirely.
        $createdAt  = Factory::getDate()->toSql();
        $userId     = $this->userId;
        $runId      = $this->runId;
        $runKind    = $this->runKind;
        $lvl        = $level;
        $act        = $action;
        $iType      = $itemType;
        $iId        = $itemId;
        $iLabel     = substr($itemLabel, 0, 255);
        $detailStr  = is_string($detail) ? $detail : (string) json_encode($detail);

        // Safety net: never let a logging failure bubble up and abort the
        // action we're meant to be tracking. Logging is best-effort.
        try {
            $query = $this->db->createQuery();
            $query->insert($this->db->quoteName('#__removesampledata_log'))
                ->columns($this->db->quoteName([
                    'created', 'user_id', 'run_id', 'run_kind', 'level',
                    'action', 'item_type', 'item_id', 'item_label', 'detail',
                ]))
                ->values(':created, :userId, :runId, :runKind, :level, :action, :itemType, :itemId, :itemLabel, :detail')
                ->bind(':created', $createdAt)
                ->bind(':userId', $userId, ParameterType::INTEGER)
                ->bind(':runId', $runId)
                ->bind(':runKind', $runKind)
                ->bind(':level', $lvl)
                ->bind(':action', $act)
                ->bind(':itemType', $iType)
                ->bind(':itemId', $iId)
                ->bind(':itemLabel', $iLabel)
                ->bind(':detail', $detailStr);

            $this->db->setQuery($query)->execute();
        } catch (\Throwable $e) {
            // Last resort: write to PHP's error log so the failure is
            // visible somewhere, but never throw.
            @error_log('com_removesampledata logger failed: ' . $e->getMessage());
        }
    }

    public function info(string $action, string $itemType = '', string $itemId = '', string $itemLabel = '', $detail = null): void
    {
        $this->log(self::LEVEL_INFO, $action, $itemType, $itemId, $itemLabel, $detail);
    }

    public function success(string $action, string $itemType = '', string $itemId = '', string $itemLabel = '', $detail = null): void
    {
        $this->log(self::LEVEL_SUCCESS, $action, $itemType, $itemId, $itemLabel, $detail);
    }

    public function warning(string $action, string $itemType = '', string $itemId = '', string $itemLabel = '', $detail = null): void
    {
        $this->log(self::LEVEL_WARNING, $action, $itemType, $itemId, $itemLabel, $detail);
    }

    public function error(string $action, string $itemType = '', string $itemId = '', string $itemLabel = '', $detail = null): void
    {
        $this->log(self::LEVEL_ERROR, $action, $itemType, $itemId, $itemLabel, $detail);
    }
}
