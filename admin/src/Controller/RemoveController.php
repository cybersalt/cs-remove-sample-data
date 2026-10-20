<?php
/**
 * @package    Cybersalt.Component.Removesampledata
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

namespace Cybersalt\Component\Removesampledata\Administrator\Controller;

\defined('_JEXEC') or die;

use Cybersalt\Component\Removesampledata\Administrator\Helper\PermissionHelper;
use Cybersalt\Component\Removesampledata\Administrator\Service\ActionLogger;
use Cybersalt\Component\Removesampledata\Administrator\Service\BlogDetector;
use Cybersalt\Component\Removesampledata\Administrator\Service\BlogRemover;
use Cybersalt\Component\Removesampledata\Administrator\Service\MultilangDetector;
use Cybersalt\Component\Removesampledata\Administrator\Service\MultilangRemover;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

class RemoveController extends BaseController
{
    public function execute($task)
    {
        return parent::execute($task);
    }

    /**
     * Validates this submission against CSRF, with a fallback so a stale Joomla
     * token (session rotated mid-page, Remember-Me re-auth, edge-cached form
     * with a token from a since-rotated session, etc.) doesn't lock the user
     * out of a destructive operation they explicitly confirmed.
     *
     * Tier 1 (strict): Joomla's standard form token. If valid, return.
     * Tier 2 (fallback): Origin or Referer header points at this site's host.
     *   Browsers strip those on cross-origin POST, so an attacker on another
     *   domain cannot forge them — defence is preserved.
     */
    private function verifySubmission(): void
    {
        // Tier 1: Joomla's standard CSRF token (validates against the live
        // session). If valid, return.
        $session   = $this->app->getSession();
        $formToken = $session->getFormToken();
        $input     = $this->app->getInput();

        if ($input->get($formToken, '', 'alnum') === '1') {
            return;
        }
        if ($input->post->get($formToken, '', 'alnum') === '1') {
            return;
        }

        // Tier 2: same-origin check via Origin/Referer host. Browsers strip
        // both on cross-origin POST so an attacker on another domain cannot
        // forge them. This is a defence-in-depth fallback for the legitimate
        // case where the session token rotated mid-page (e.g. Remember-Me
        // re-auth) — without it the user gets an unrecoverable token error
        // even though their submission is genuinely same-origin.
        $server      = $input->server;
        $siteHost    = parse_url(\Joomla\CMS\Uri\Uri::root(), PHP_URL_HOST) ?: '';
        $origin      = (string) $server->get('HTTP_ORIGIN', '', 'string');
        $referer     = (string) $server->get('HTTP_REFERER', '', 'string');
        $originHost  = $origin  !== '' ? (parse_url($origin, PHP_URL_HOST)  ?: '') : '';
        $refererHost = $referer !== '' ? (parse_url($referer, PHP_URL_HOST) ?: '') : '';

        if ($siteHost !== '') {
            if ($originHost !== '' && strcasecmp($originHost, $siteHost) === 0) {
                \Joomla\CMS\Log\Log::add('com_removesampledata: token stale; Origin same-origin OK.', \Joomla\CMS\Log\Log::INFO, 'com_removesampledata');
                return;
            }
            if ($refererHost !== '' && strcasecmp($refererHost, $siteHost) === 0) {
                \Joomla\CMS\Log\Log::add('com_removesampledata: token stale; Referer same-origin OK.', \Joomla\CMS\Log\Log::INFO, 'com_removesampledata');
                return;
            }
        }

        // Both tiers failed — bounce back to the dashboard with a clean
        // session-expired message. The diagnostic values are HTML-escaped
        // because Joomla's flash-message renderer treats messages as HTML
        // (it permits <br>, <strong>, etc.), and the Origin/Referer values
        // are attacker-influenceable on a cross-origin CSRF attempt.
        // The token prefix that earlier versions exposed for debugging has
        // been dropped — leaking even six hex chars of the form token to a
        // user-visible message is gratuitous.
        $safeOrigin   = htmlspecialchars($originHost  !== '' ? $originHost  : '-', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeReferer  = htmlspecialchars($refererHost !== '' ? $refererHost : '-', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeExpected = htmlspecialchars($siteHost    !== '' ? $siteHost    : '-', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $this->app->enqueueMessage(
            \Joomla\CMS\Language\Text::_('JINVALID_TOKEN')
                . ' [origin=' . $safeOrigin . ' | referer=' . $safeReferer . ' | expected=' . $safeExpected . ']',
            'error'
        );
        $this->setRedirect(\Joomla\CMS\Router\Route::_('index.php?option=com_removesampledata&view=dashboard', false));
        $this->redirect();
    }

    public function run(): void
    {
        PermissionHelper::requireRemove();
        $this->verifySubmission();

        $input        = $this->app->getInput();
        $kind         = $input->getCmd('kind', '');
        $confirmed    = (int) $input->getInt('confirm_remove', 0) === 1;
        $params       = \Joomla\CMS\Component\ComponentHelper::getParams('com_removesampledata');
        $db           = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

        if (!$confirmed) {
            $this->app->enqueueMessage(Text::_('COM_REMOVESAMPLEDATA_ERROR_CONFIRM'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_removesampledata&view=dashboard', false));
            return;
        }

        if (!in_array($kind, ['blog', 'multilang'], true)) {
            $this->app->enqueueMessage(Text::_('COM_REMOVESAMPLEDATA_ERROR_BAD_KIND'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_removesampledata&view=dashboard', false));
            return;
        }

        $logger = new ActionLogger($db, $kind);

        // Apply the "Log retention" option: drop entries older than N days.
        $retention = (int) $params->get('log_retention_days', 90);
        if ($retention > 0) {
            try {
                $cutoff = Factory::getDate('-' . $retention . ' days')->toSql();
                $db->setQuery(
                    $db->createQuery()
                        ->delete($db->quoteName('#__removesampledata_log'))
                        ->where($db->quoteName('created') . ' < ' . $db->quote($cutoff))
                )->execute();
            } catch (\Throwable $e) {
                // Pruning the log must never block a removal.
            }
        }

        // The scan view posts remove[<group>][] = id for items the user ticked
        // for removal. Anything NOT ticked stays on the site.
        $toRemove = $input->get('remove', [], 'array');

        try {
            if ($kind === 'blog') {
                $detector = new BlogDetector($db);
                $detected = $detector->detect();
                $selected = $this->filterByRemove($detected, $toRemove);
                $remover  = new BlogRemover($db, $logger);
                $counts   = $remover->remove($selected);
            } else {
                $detector = new MultilangDetector($db);
                $detected = $detector->detect();
                $selected = $this->filterByRemove($detected, $toRemove);
                // An unticked checkbox is simply absent from the POST, so the default
                // must be 0 (before 1.1.0 it was 1, which ignored the switch).
                $disableFilter = (bool) $input->getInt('disable_languagefilter', 0);
                $restoreMenu   = (bool) $params->get('restore_main_menu_module', 1);
                $remover  = new MultilangRemover($db, $logger);
                $counts   = $remover->remove($selected, $disableFilter, $restoreMenu);
            }
            $this->app->setUserState('com_removesampledata.last_run', [
                'kind'   => $kind,
                'run_id' => $logger->getRunId(),
                'counts' => $counts,
            ]);
            $this->setRedirect(Route::_('index.php?option=com_removesampledata&view=result', false));
        } catch (\Throwable $e) {
            $logger->error('exception', $kind, '', 'Removal aborted with exception', $e->getMessage());
            // Escape the exception message before interpolating into a flash
            // message — Joomla renders messages as HTML, and exception
            // strings can carry attacker-influenced content via DB errors
            // echoing item titles.
            $safeMessage = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $this->app->enqueueMessage(Text::sprintf('COM_REMOVESAMPLEDATA_ERROR_FAILED', $safeMessage), 'error');
            $this->setRedirect(Route::_('index.php?option=com_removesampledata&view=dashboard', false));
        }
    }

    private function filterByRemove(array $detected, array $toRemove): array
    {
        $selected = [];
        foreach ($detected as $group => $items) {
            if (!is_array($items) || ($items !== [] && !array_is_list($items))) {
                // Non-list payload (e.g. multilang's languagefilter / main_menu_module
                // metadata) gets passed through untouched — the remover needs it.
                // Before 1.1.0 these associative arrays were treated as item
                // lists and emptied, so the Language Filter plugin was never
                // disabled and the main menu module never republished.
                $selected[$group] = $items;
                continue;
            }
            $removeIds = isset($toRemove[$group]) ? array_map('strval', (array) $toRemove[$group]) : [];
            $selected[$group] = [];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $id = isset($item['id']) ? (string) $item['id'] : '';
                if ($id === '' || !in_array($id, $removeIds, true)) {
                    continue;
                }
                $selected[$group][] = $item;
            }
        }
        return $selected;
    }
}
