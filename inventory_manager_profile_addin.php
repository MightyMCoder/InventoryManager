<?php
/**
 ***********************************************************************************************
 * Shows issued items in a member's profile for the Admidio plugin InventoryManager
 *
 * @see         https://github.com/MightyMCoder/InventoryManager/ The InventoryManager GitHub project
 * @author      MightyMCoder
 * @copyright   2024 - today MightyMCoder
 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 only
 * 
 * Note:
 * 
 * Add the following line to profile.php (before $page->show();):
 * "require_once(ADMIDIO_PATH . FOLDER_PLUGINS .'/InventoryManager/inventory_manager_profile_addin.php');"
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/common_function.php');
require_once(__DIR__ . '/classes/items.php');
require_once(__DIR__ . '/classes/configtable.php');
require_once(__DIR__ . '/../../adm_program/system/classes/MenuNode.php');

// Access only with valid login
require_once(__DIR__ . '/../../adm_program/system/login_valid.php');

// Check if the user is authorized to see the plugin addin on the profile page
$userIsAuthorized = isUserAuthorizedForAddinPIM();

if ($userIsAuthorized) {
	$getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'string', array('defaultValue' => $gCurrentUser->getValue('usr_uuid')));

	$creationMode = '';
	$user = new User($gDb, $gProfileFields);
	$user->readDataByUuid($getUserUuid);

	$itemsKeeper = new CItems($gDb, $gCurrentOrgId);
	$itemsReceiver = new CItems($gDb, $gCurrentOrgId);

	// first read all items the user is keeper of
	$itemsKeeper->readItemsByUser($gCurrentOrgId, $user->getValue('usr_id'), array('KEEPER'));

	//then read all items the user is last receiver of
	$itemsReceiver->readItemsByUser($gCurrentOrgId, $user->getValue('usr_id'), array('LAST_RECEIVER'));

	if (!empty($itemsKeeper->items) && empty($itemsReceiver->items)) {
		$creationMode = 'keeper';
	}
	elseif (empty($itemsKeeper->items) && !empty($itemsReceiver->items)) {
		$creationMode = 'receiver';
	}
	elseif (!empty($itemsKeeper->items) && !empty($itemsReceiver->items)) {
		$creationMode = 'both';
	}

	switch($creationMode)
	{
		case 'keeper':
			insertKeeperView($page, $user, $itemsKeeper);
			break;
		case 'receiver':
			insertReceiverView($page, $user, $itemsReceiver);
			break;
		case 'both':
			insertKeeperView($page, $user, $itemsKeeper);
			insertReceiverView($page, $user, $itemsReceiver);
			break;
		default:
			return;
	}
}

function insertKeeperView($page, $user, $itemsKeeper) {
	global $gL10n, $gCurrentOrgId;

	$pPreferences = new CConfigTablePIM();
	$pPreferences->read();

	$page->addHtml('<div class="card admidio-field-group" id="inventory_manager_box_keeper">
					<div class="card-header">' . $gL10n->get('PLG_INVENTORY_MANAGER_INVENTORY_MANAGER') . ' (' . $gL10n->get('SYS_VIEW') . ': ' . $gL10n->get('PIM_KEEPER') . ')
					<a class="admidio-icon-link float-right" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_IM . '/inventory_manager.php', array(
						'export_and_filter' => true,
						'show_all' => true,
						'same_side' => true,
						'filter_keeper' => $user->getValue('usr_id')
					)) . '">
						<i class="fas fa-warehouse" data-toggle="tooltip" title="' . $gL10n->get('PLG_INVENTORY_MANAGER_INVENTORY_MANAGER') . '"></i>
					</a>
					</div>
					<div id="inventory_manager_box_body" class="card-body">
					<ul class="list-group">');

	// prepare header for table
	$inventoryAddinTable = new HtmlTable('adm_inventory_addin_table', $page, true, true, 'table table-condensed');
	$inventoryAddinTable->setDatatablesRowsPerPage(10);

	// create array with all column heading values

	// initialize array parameters for table and set the first column for the counter
	$columnAlign  = array('right');
	$columnHeading = array($gL10n->get('SYS_ABR_NO'));

	// headlines for columns
	$columnNumber = 1;

	$addinItemFields = array('ITEMNAME', $pPreferences->config['Optionen']['profile_addin'], 'LAST_RECEIVER');
	$itemsKeeper->readItemData($itemsKeeper->items[0]['imi_id'], $gCurrentOrgId);

	foreach ($itemsKeeper->mItemFields as $itemField) {  
		$imfNameIntern = $itemField->getValue('imf_name_intern');

		if (!in_array($imfNameIntern, $addinItemFields, true)) {
			continue;
		}

		$columnHeader = convlanguagePIM($itemsKeeper->getProperty($imfNameIntern, 'imf_name'));

		switch ($itemsKeeper->getProperty($imfNameIntern, 'imf_type')) {
			case 'CHECKBOX':
			case 'RADIO_BUTTON':
			case 'GENDER':
				$columnAlign[] = 'center';
				break;
			case 'NUMBER':
			case 'DECIMAL':
				$columnAlign[] = 'right';
				break;
			default:
				$columnAlign[] = 'left';
				break;
		}

		$columnHeading[] = $columnHeader;

		$columnNumber++;

	}

	// add column for edit and delete icons
	$columnAlign[]  = 'center';
	$columnHeading[] = '&nbsp;';

	$inventoryAddinTable->setColumnAlignByArray($columnAlign);
	$inventoryAddinTable->addRowHeadingByArray($columnHeading);
	$inventoryAddinTable->disableDatatablesColumnsSort(array(count($columnHeading))); //disable sort in last column
	$inventoryAddinTable->setDatatablesColumnsNotHideResponsive(array(count($columnHeading)));

	$listRowNumber = 1;

	foreach ($itemsKeeper->items as $item) {
		$itemsKeeper->readItemData($item['imi_id'], $gCurrentOrgId);
		$columnValues = array();
		$strikethrough = $item['imi_former'];
		$columnNumber = 1;

		foreach ($itemsKeeper->mItemFields as $itemField) {
			$imfNameIntern = $itemField->getValue('imf_name_intern');

			if ($columnNumber === 1) {
				$columnValues[] = $listRowNumber;
			}

			if (!in_array($imfNameIntern, $addinItemFields, true)) {
				continue;
			}

			$content = $itemsKeeper->getValue($imfNameIntern, 'database');

			if (($imfNameIntern == 'KEEPER' || $imfNameIntern == 'LAST_RECEIVER') && strlen($content) > 0) {
				$user->readDataById($content);
                if (!$user->getValue('usr_uuid') == '') {
                    $content = '<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', array('user_uuid' => $user->getValue('usr_uuid'))) . '">' . $user->getValue('LAST_NAME') . ', ' . $user->getValue('FIRST_NAME') . '</a>';
                }
			}

			if ($itemsKeeper->getProperty($imfNameIntern, 'imf_type') == 'CHECKBOX') {
				$content = ($content != 1) ? 0 : 1;
				$content = $itemsKeeper->getHtmlValue($imfNameIntern, $content);
			}
			elseif ($itemsKeeper->getProperty($imfNameIntern, 'imf_type') == 'DATE') {
				$content = $itemsKeeper->getHtmlValue($imfNameIntern, $content);
			}
			elseif (in_array($itemsKeeper->getProperty($imfNameIntern, 'imf_type'), array('DROPDOWN', 'RADIO_BUTTON'))) {
				$content = 	$itemsKeeper->getHtmlValue($imfNameIntern, $content);
			}

			$columnValues[] = ($strikethrough) ? '<s>' . $content . '</s>' : $content;

			$columnNumber++;
		}

		$tempValue = '';
		if ($pPreferences->isPffInst()) {
			$tempValue .= '<a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . '/' . PLUGIN_FOLDER_IM . '/items/items_export_to_pff.php', array('item_id' => $item['imi_id'])) . '">
							<i class="fas fa-print" title="' . $gL10n->get('PLG_INVENTORY_MANAGER_ITEM_PRINT') . '"></i>
						</a>';
		}
		if (isUserAuthorizedForPreferencesPIM()) {
			$tempValue .= '<a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_IM . '/items/items_edit_new.php', array('item_id' => $item['imi_id'], 'item_former' => $item['imi_former'])) . '">
								<i class="fas fa-edit" title="' . $gL10n->get('PLG_INVENTORY_MANAGER_ITEM_EDIT') . '"></i>
							</a>';
			$tempValue .= '<a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . '/' . PLUGIN_FOLDER_IM . '/items/items_delete.php', array('item_id' => $item['imi_id'], 'item_former' => $item['imi_former'])) . '">
							<i class="fas fa-trash-alt" title="' . $gL10n->get('PLG_INVENTORY_MANAGER_ITEM_DELETE') . '"></i>
						</a>';
		}
		$columnValues[] = $tempValue;

		$inventoryAddinTable->addRowByArray($columnValues, '', array('nobr' => 'true'));

		++$listRowNumber;
	}

	$page->addHtml($inventoryAddinTable->show());
	$page->addHtml('</ul></div></div>');
	$page->addHtml('<script>$("#inventory_manager_box_keeper").insertBefore("#profile_roles_box");</script>');
}

function insertReceiverView($page, $user, $itemsReceiver) {
	global $gL10n, $gCurrentOrgId;

	$pPreferences = new CConfigTablePIM();
	$pPreferences->read();

	$page->addHtml('<div class="card admidio-field-group" id="inventory_manager_box_receiver">
					<div class="card-header">' . $gL10n->get('PLG_INVENTORY_MANAGER_INVENTORY_MANAGER') . ' (' . $gL10n->get('SYS_VIEW') . ': ' . $gL10n->get('PIM_LAST_RECEIVER') . ')
					<a class="admidio-icon-link float-right" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_IM . '/inventory_manager.php', array(
						'export_and_filter' => true,
						'show_all' => true,
						'same_side' => true,
						'filter_keeper' => $user->getValue('usr_id')
					)) . '">
						<i class="fas fa-warehouse" data-toggle="tooltip" title="' . $gL10n->get('PLG_INVENTORY_MANAGER_INVENTORY_MANAGER') . '"></i>
					</a>
					</div>
					<div id="inventory_manager_box_body" class="card-body">
					<ul class="list-group">');

	// prepare header for table
	$inventoryAddinTable = new HtmlTable('adm_inventory_addin_table', $page, true, true, 'table table-condensed');
	$inventoryAddinTable->setDatatablesRowsPerPage(10);

	// create array with all column heading values

	// initialize array parameters for table and set the first column for the counter
	$columnAlign  = array('right');
	$columnHeading = array($gL10n->get('SYS_ABR_NO'));

	// headlines for columns
	$columnNumber = 1;

	$addinItemFields = array('ITEMNAME', $pPreferences->config['Optionen']['profile_addin'], 'LAST_RECEIVER');
	$itemsReceiver->readItemData($itemsReceiver->items[0]['imi_id'], $gCurrentOrgId);

	foreach ($itemsReceiver->mItemFields as $itemField) {  
		$imfNameIntern = $itemField->getValue('imf_name_intern');

		if (!in_array($imfNameIntern, $addinItemFields, true)) {
			continue;
		}

		$columnHeader = convlanguagePIM($itemsReceiver->getProperty($imfNameIntern, 'imf_name'));

		switch ($itemsReceiver->getProperty($imfNameIntern, 'imf_type')) {
			case 'CHECKBOX':
			case 'RADIO_BUTTON':
			case 'GENDER':
				$columnAlign[] = 'center';
				break;
			case 'NUMBER':
			case 'DECIMAL':
				$columnAlign[] = 'right';
				break;
			default:
				$columnAlign[] = 'left';
				break;
		}

		$columnHeading[] = $columnHeader;

		$columnNumber++;

	}

	// add column for edit and delete icons
	$columnAlign[]  = 'center';
	$columnHeading[] = '&nbsp;';

	$inventoryAddinTable->setColumnAlignByArray($columnAlign);
	$inventoryAddinTable->addRowHeadingByArray($columnHeading);
	$inventoryAddinTable->disableDatatablesColumnsSort(array(count($columnHeading))); //disable sort in last column
	$inventoryAddinTable->setDatatablesColumnsNotHideResponsive(array(count($columnHeading)));

	$listRowNumber = 1;

	foreach ($itemsReceiver->items as $item) {
		$itemsReceiver->readItemData($item['imi_id'], $gCurrentOrgId);
		$columnValues = array();
		$strikethrough = $item['imi_former'];
		$columnNumber = 1;

		foreach ($itemsReceiver->mItemFields as $itemField) {
			$imfNameIntern = $itemField->getValue('imf_name_intern');

			if ($columnNumber === 1) {
				$columnValues[] = $listRowNumber;
			}

			if (!in_array($imfNameIntern, $addinItemFields, true)) {
				continue;
			}

			$content = $itemsReceiver->getValue($imfNameIntern, 'database');

			if (($imfNameIntern == 'KEEPER' || $imfNameIntern == 'LAST_RECEIVER') && strlen($content) > 0) {
				$user->readDataById($content);
                if (!$user->getValue('usr_uuid') == '') {
                    $content = '<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', array('user_uuid' => $user->getValue('usr_uuid'))) . '">' . $user->getValue('LAST_NAME') . ', ' . $user->getValue('FIRST_NAME') . '</a>';
                }
			}

			if ($itemsReceiver->getProperty($imfNameIntern, 'imf_type') == 'CHECKBOX') {
				$content = ($content != 1) ? 0 : 1;
				$content = $itemsReceiver->getHtmlValue($imfNameIntern, $content);
			}
			elseif ($itemsReceiver->getProperty($imfNameIntern, 'imf_type') == 'DATE') {
				$content = $itemsReceiver->getHtmlValue($imfNameIntern, $content);
			}
			elseif (in_array($itemsReceiver->getProperty($imfNameIntern, 'imf_type'), array('DROPDOWN', 'RADIO_BUTTON'))) {
				$content = 	$itemsReceiver->getHtmlValue($imfNameIntern, $content);
			}

			$columnValues[] = ($strikethrough) ? '<s>' . $content . '</s>' : $content;

			$columnNumber++;
		}

		$tempValue = '';
		if ($pPreferences->isPffInst()) {
			$tempValue .= '<a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . '/' . PLUGIN_FOLDER_IM . '/items/items_export_to_pff.php', array('item_id' => $item['imi_id'])) . '">
							<i class="fas fa-print" title="' . $gL10n->get('PLG_INVENTORY_MANAGER_ITEM_PRINT') . '"></i>
						</a>';
		}
		if (isUserAuthorizedForPreferencesPIM()) {
			$tempValue .= '<a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_IM . '/items/items_edit_new.php', array('item_id' => $item['imi_id'], 'item_former' => $item['imi_former'])) . '">
								<i class="fas fa-edit" title="' . $gL10n->get('PLG_INVENTORY_MANAGER_ITEM_EDIT') . '"></i>
							</a>';
			$tempValue .= '<a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . '/' . PLUGIN_FOLDER_IM . '/items/items_delete.php', array('item_id' => $item['imi_id'], 'item_former' => $item['imi_former'])) . '">
							<i class="fas fa-trash-alt" title="' . $gL10n->get('PLG_INVENTORY_MANAGER_ITEM_DELETE') . '"></i>
						</a>';
		}
		$columnValues[] = $tempValue;

		$inventoryAddinTable->addRowByArray($columnValues, '', array('nobr' => 'true'));

		++$listRowNumber;
	}

	$page->addHtml($inventoryAddinTable->show());
	$page->addHtml('</ul></div></div>');
	$page->addHtml('<script>$("#inventory_manager_box_receiver").insertBefore("#profile_roles_box");</script>');
}