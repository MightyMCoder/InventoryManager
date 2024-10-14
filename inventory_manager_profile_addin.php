<?php
/**
 ***********************************************************************************************
 * Shows issued items in a member's profile for the Admidio plugin InventoryManager
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 * 
 * Note:
 * 
 * Add the following line to profile.php (before $page->show();):
 * require_once(ADMIDIO_PATH . FOLDER_PLUGINS . PLUGIN_FOLDER .'/inventory_manager_profile_addin.php');
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/common_function.php');
require_once(__DIR__ . '/classes/items.php');
require_once(__DIR__ . '/classes/configtable.php');

// Access only with valid login
require_once(__DIR__ . '/../../adm_program/system/login_valid.php');

$getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'string', array('defaultValue' => $gCurrentUser->getValue('usr_uuid')));

$pPreferences = new CConfigTablePIM();
$pPreferences->read();

$user = new User($gDb, $gProfileFields);
$user->readDataByUuid($getUserUuid);

$items = new CItems($gDb, $gCurrentOrgId);
$items->readItemsByUser($gCurrentOrgId, $user->getValue('usr_id'));

if (empty($items->items)) {
	return;
}

$page->addHtml('<div class="card admidio-field-group" id="inventory_manager_box">
				<div class="card-header">' . $gL10n->get('PLG_INVENTORY_MANAGER_INVENTORY_MANAGER') . '
				<a class="admidio-icon-link float-right" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER . '/inventory_manager.php', array(
					'export_and_filter' => true,
					'show_all' => true,
					'same_side' => true,
					'filter_keeper' => $user->getValue('usr_id')
				)) . '">
					<i class="fas fa-key" data-toggle="tooltip" title="' . $gL10n->get('PLG_INVENTORY_MANAGER_INVENTORY_MANAGER') . '"></i>
				</a>
				</div>
				<div id="inventory_manager_box_body" class="card-body">
				<ul class="list-group">');

foreach ($items->items as $item) {
	$items->readItemData($item['imi_id'], $gCurrentOrgId);

	$content = '<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER . '/items_edit_new.php', array('item_id' => $item['imi_id'])) . '">' . $items->getValue('ITEMNAME') . '</a>';
	$contentAdd = $items->getValue($pPreferences->config['Optionen']['profile_addin']);

	if (!empty($contentAdd)) {
		$contentAdd = strlen($contentAdd) > 50 ? substr($contentAdd, 0, 50) . '...' : $contentAdd;
		$content .= ' - ' . $contentAdd;
	}

	if ($item['imi_former']) {
		$content = '<s>' . $content . '</s>';
	}

	$receivedOn = !empty($items->getValue('RECEIVED_ON')) ? $gL10n->get('PIM_RECEIVED_ON') . ' ' . date('d.m.Y', strtotime($items->getValue('RECEIVED_ON'))) : '';
	if ($item['imi_former']) {
		$receivedOn = '<s>' . $receivedOn . '</s>';
	}

	$page->addHtml('<li class="list-group-item">
					<div style="text-align: left; float: left;">' . $content . '</div>
					<div style="text-align: right; float: right;">' . $receivedOn . ' ');

	if ($pPreferences->isPffInst()) {
		$page->addHtml('<a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER . '/items_export_to_pff.php', array('item_id' => $item['imi_id'])) . '">
						<i class="fas fa-print" data-toggle="tooltip" title="' . $gL10n->get('PLG_INVENTORY_MANAGER_ITEM_PRINT') . '"></i>
					</a>');
	}

	if (isUserAuthorizedForPreferences()) {
		$page->addHtml('<a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER . '/items_delete.php', array('item_id' => $item['imi_id'], 'item_former' => $item['imi_former'])) . '">
						<i class="fas fa-minus-circle" data-toggle="tooltip" title="' . $gL10n->get('PLG_INVENTORY_MANAGER_ITEM_DELETE') . '"></i>
					</a>');
	}

	$page->addHtml('</div><div style="clear: both;"></div></li>');
}

$page->addHtml('</ul></div></div>');
$page->addHtml('<script>$("#inventory_manager_box").insertBefore("#profile_roles_box");</script>');
