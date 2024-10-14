<?php
/**
 ***********************************************************************************************
 * Script to delete or mark items as former in the InventoryManager plugin
 * 
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 * 
 * Parameters:
 * 
 * mode       : 1 - Display form to delete or mark item as former
 *              2 - Delete an item
 *              3 - Mark an item as former
 * item_id    : ID of the item to be deleted or marked as former
 * item_former: 0 - Item is active
 *              1 - Item is already marked as former
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/common_function.php');
require_once(__DIR__ . '/classes/items.php');
require_once(__DIR__ . '/classes/configtable.php');

// Access only with valid login
require_once(__DIR__ . '/../../adm_program/system/login_valid.php');

// Initialize and check the parameters
$getMode      = admFuncVariableIsValid($_GET, 'mode',      'numeric', array('defaultValue' => 1));
$getItemId     = admFuncVariableIsValid($_GET, 'item_id',    'int');
$getItemFormer = admFuncVariableIsValid($_GET, 'item_former', 'bool');

$pPreferences = new CConfigTablePIM();
$pPreferences->read();

// only authorized user are allowed to start this module
if (!isUserAuthorizedForPreferences()) {
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$items = new CItems($gDb, $gCurrentOrgId);
$items->readItemData($getItemId, $gCurrentOrgId);
$user = new User($gDb, $gProfileFields);

switch ($getMode) {
	case 1:
		displayItemDeleteForm($items, $user, $getItemId, $getItemFormer);
		break;
	case 2:
		deleteItem($getItemId);
		break;
	case 3:
		makeItemFormer($getItemId);
		break;
}

/**
 * Displays the form to delete an item.
 * @param CItems $items 		The items object containing item data.
 * @param User $user 			The user object.
 * @param int $getItemId 		The ID of the item to be deleted.
 * @param bool $getItemFormer 	Indicates if the item is already marked as former.
 */
function displayItemDeleteForm($items, $user, $getItemId, $getItemFormer) {
	global $gL10n, $gNavigation;

	$headline = $gL10n->get('PLG_INVENTORY_MANAGER_ITEM_DELETE');
	$page = new HtmlPage('plg-inventory-manager-items-delete', $headline);
	$gNavigation->addUrl(CURRENT_URL, $headline);
	$page->addHtml('<p class="lead">'.$gL10n->get('PLG_INVENTORY_MANAGER_ITEM_DELETE_DESC').'</p>');

	$form = new HtmlForm('item_delete_form', null, $page);
	foreach ($items->mItemFields as $itemField) {
		$imfNameIntern = $itemField->getValue('imf_name_intern');
		$content = $items->getValue($imfNameIntern, 'database');

		if ($imfNameIntern === 'KEEPER' && strlen($content) > 0) {
			$user->readDataById($content);
			$content = $user->getValue('LAST_NAME').', '.$user->getValue('FIRST_NAME');
		} elseif ($items->getProperty($imfNameIntern, 'imf_type') === 'DATE') {
			$content = $items->getHtmlValue($imfNameIntern, $content);
		} elseif (in_array($items->getProperty($imfNameIntern, 'imf_type'), ['DROPDOWN', 'RADIO_BUTTON'])) {
			$arrListValues = $items->getProperty($imfNameIntern, 'imf_value_list', 'text');
			$content = $arrListValues[$content];
		}

		$form->addInput(
			'imf-'. $items->getProperty($imfNameIntern, 'imf_id'),
			convlanguagePIM($items->getProperty($imfNameIntern, 'imf_name')),
			$content,
			array('property' => HtmlForm::FIELD_DISABLED)
		);
	}

	$form->addButton('btn_delete', $gL10n->get('SYS_DELETE'), array('icon' => 'fa-trash-alt', 'link' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/items_delete.php', array('item_id' => $getItemId, 'mode' => 2)), 'class' => 'btn-primary offset-sm-3'));
	if (!$getItemFormer) {
		$form->addButton('btn_former', $gL10n->get('PLG_INVENTORY_MANAGER_FORMER'), array('icon' => 'fa-eye-slash', 'link' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/items_delete.php', array('item_id' => $getItemId, 'mode' => 3)), 'class' => 'btn-primary offset-sm-3'));
		$form->addCustomContent('', '<br />'.$gL10n->get('PLG_INVENTORY_MANAGER_ITEM_MAKE_TO_FORMER'));
	}

	$page->addHtml($form->show(false));
	$page->show();
}

/**
 * Deletes an item from the database.
 * @param int $getItemId 		The ID of the item to be deleted.
 */
function deleteItem($getItemId) {
	global $gDb, $gCurrentOrgId, $gMessage, $gNavigation, $gL10n;

	$sql = 'DELETE FROM '.TBL_INVENTORY_MANAGER_LOG.' WHERE iml_imi_id = ?;';
	$gDb->queryPrepared($sql, array($getItemId));

	$sql = 'DELETE FROM '.TBL_INVENTORY_MANAGER_DATA.' WHERE imd_imi_id = ?;';
	$gDb->queryPrepared($sql, array($getItemId));

	$sql = 'DELETE FROM '.TBL_INVENTORY_MANAGER_ITEMS.' WHERE imi_id = ? AND (imi_org_id = ? OR imi_org_id IS NULL);';
	$gDb->queryPrepared($sql, array($getItemId, $gCurrentOrgId));

	$gMessage->setForwardUrl($gNavigation->getPreviousUrl(), 1000);
	$gMessage->show($gL10n->get('PLG_INVENTORY_MANAGER_ITEM_DELETED'));
}

/**
 * Marks an item as former.
 * @param int $getItemId 		The ID of the item to be marked as former.
 */
function makeItemFormer($getItemId) {
	global $gDb, $gMessage, $gNavigation, $gL10n;

	$sql = 'UPDATE '.TBL_INVENTORY_MANAGER_ITEMS.' SET imi_former = 1 WHERE imi_id = ?;';
	$gDb->queryPrepared($sql, array($getItemId));

	$gMessage->setForwardUrl($gNavigation->getPreviousUrl(), 1000);
	$gMessage->show($gL10n->get('PLG_INVENTORY_MANAGER_ITEM_MADE_TO_FORMER'));
}
