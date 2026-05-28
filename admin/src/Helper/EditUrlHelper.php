<?php
/**
 * @package    Cybersalt.Component.Removesampledata
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 *
 * Maps detected sample-data items to the URL that opens them in core
 * Joomla's edit screen, so a row click takes the user straight to
 * the underlying article / menu item / module / etc.
 */

namespace Cybersalt\Component\Removesampledata\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;

class EditUrlHelper
{
    public static function for(string $type, int $id, array $extras = []): ?string
    {
        if ($id <= 0) {
            return null;
        }

        switch ($type) {
            case 'article':
                return Route::_('index.php?option=com_content&task=article.edit&id=' . $id);

            case 'category':
                return Route::_('index.php?option=com_categories&task=category.edit&id=' . $id . '&extension=com_content');

            case 'tag':
                return Route::_('index.php?option=com_tags&task=tag.edit&id=' . $id);

            case 'menu_item':
                return Route::_('index.php?option=com_menus&task=item.edit&id=' . $id);

            case 'menu_type':
                return Route::_('index.php?option=com_menus&task=menu.edit&id=' . $id);

            case 'module':
                $clientId = (int) ($extras['client_id'] ?? 0);
                return Route::_('index.php?option=com_modules&task=module.edit&id=' . $id . '&client_id=' . $clientId);

            case 'field':
                $context = $extras['context'] ?? 'com_content.article';
                return Route::_('index.php?option=com_fields&task=field.edit&id=' . $id . '&context=' . $context);

            case 'field_group':
                $context = $extras['context'] ?? 'com_content.article';
                return Route::_('index.php?option=com_fields&task=group.edit&id=' . $id . '&context=' . $context);

            case 'workflow':
                $extension = $extras['extension'] ?? 'com_content.article';
                return Route::_('index.php?option=com_workflow&task=workflow.edit&id=' . $id . '&extension=' . $extension);

            case 'workflow_stage':
                $extension  = $extras['extension'] ?? 'com_content.article';
                $workflowId = (int) ($extras['workflow_id'] ?? 0);
                return Route::_('index.php?option=com_workflow&task=stage.edit&id=' . $id . '&extension=' . $extension . '&workflow_id=' . $workflowId);

            case 'workflow_transition':
                $extension  = $extras['extension'] ?? 'com_content.article';
                $workflowId = (int) ($extras['workflow_id'] ?? 0);
                return Route::_('index.php?option=com_workflow&task=transition.edit&id=' . $id . '&extension=' . $extension . '&workflow_id=' . $workflowId);
        }

        return null;
    }
}
