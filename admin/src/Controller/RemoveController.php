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
        // Tier 1: try Joomla's standard CSRF token.
        $session   = $this->app->getSession();
        $formToken = $session->getFormToken();
        $input     = $this->app->getInput();
        $diag      = [];

        $diag[] = 't1-name=' . substr($formToken, 0, 6) . '...';
        if ($input->get($formToken, '', 'alnum') === '1') {
            return;
        }
        if ($input->post->get($formToken, '', 'alnum') === '1') {
            return;
        }
        $diag[] = 't1-not-found';

        // Tier 2: same-origin check.
        $server = $input->server;

        $siteRoot = \Joomla\CMS\Uri\Uri::root();
        $siteHost = parse_url($siteRoot, PHP_URL_HOST);
        $diag[]   = 't2-expected=' . ($siteHost ?: 'NULL');

        $origin      = (string) $server->get('HTTP_ORIGIN', '', 'string');
        $referer     = (string) $server->get('HTTP_REFERER', '', 'string');
        $originHost  = $origin  !== '' ? parse_url($origin, PHP_URL_HOST)  : null;
        $refererHost = $referer !== '' ? parse_url($referer, PHP_URL_HOST) : null;

        $diag[] = 'origin=' . ($originHost ?: '-');
        $diag[] = 'referer=' . ($refererHost ?: '-');

        if ($siteHost) {
            if ($originHost !== null && strcasecmp($originHost, $siteHost) === 0) {
                \Joomla\CMS\Log\Log::add('com_removesampledata: token stale; Origin same-origin OK.', \Joomla\CMS\Log\Log::INFO, 'com_removesampledata');
                return;
            }
            if ($refererHost !== null && strcasecmp($refererHost, $siteHost) === 0) {
                \Joomla\CMS\Log\Log::add('com_removesampledata: token stale; Referer same-origin OK.', \Joomla\CMS\Log\Log::INFO, 'com_removesampledata');
                return;
            }
        }

        // Both tiers failed — redirect back to the dashboard with the diagnostic
        // shown as a flash message so the user (Tim) can SEE what didn't match
        // instead of getting a generic 403 error page. Throwing here renders
        // an ugly Joomla error page; the message in the yellow alert box on
        // the dashboard is far more useful for diagnosis.
        $this->app->enqueueMessage(
            \Joomla\CMS\Language\Text::_('JINVALID_TOKEN') . ' [' . implode(' | ', $diag) . ']',
            'error'
        );
        $this->setRedirect(\Joomla\CMS\Router\Route::_('index.php?option=com_removesampledata&view=dashboard', false));
        $this->redirect();
        // redirect() exits the application; the throw below is unreachable
        // but satisfies static analyzers and serves as a safety net.
        throw new \RuntimeException(\Joomla\CMS\Language\Text::_('JINVALID_TOKEN'), 403);
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
                $disableFilter = (bool) $input->getInt('disable_languagefilter', 1);
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
            $this->app->enqueueMessage(Text::sprintf('COM_REMOVESAMPLEDATA_ERROR_FAILED', $e->getMessage()), 'error');
            $this->setRedirect(Route::_('index.php?option=com_removesampledata&view=dashboard', false));
        }
    }

    private function filterByRemove(array $detected, array $toRemove): array
    {
        $selected = [];
        foreach ($detected as $group => $items) {
            if (!is_array($items)) {
                // Non-list payload (e.g. multilang's languagefilter / main_menu_module
                // metadata) gets passed through untouched — the remover needs it.
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
