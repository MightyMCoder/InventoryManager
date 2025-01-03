<?php
/**
 ***********************************************************************************************
 * Script to save item data in the InventoryManager plugin
 *
 * @see         https://github.com/MightyMCoder/InventoryManager/ The InventoryManager GitHub project
 * @author      MightyMCoder
 * @copyright   2024 - today MightyMCoder
 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 only
 * 
 * Parameters:
 * 
 * item_id    : >0 - ID of the item to be saved, 0 - a new item will be added
 * copy_number: number of new items to be created
 * copy_field : field for the current number
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/classes/items.php');
require_once(__DIR__ . '/common_function.php');

// Access only with valid login
require_once(__DIR__ . '/../../adm_program/system/login_valid.php');

// Initialize and check the parameters
$getItemId = admFuncVariableIsValid($_GET, 'item_id', 'int');
$postCopyNumber = admFuncVariableIsValid($_POST, 'copy_number', 'numeric', ['defaultValue' => 1]);
$postCopyField = admFuncVariableIsValid($_POST, 'copy_field', 'int');
$postRedirect = admFuncVariableIsValid($_POST, 'redirect', 'numeric', ['defaultValue' => 1]);

$items = new CItems($gDb, $gCurrentOrgId);

$startIdx = 1;
if ($postCopyField > 0 && isset($_POST['imf-' . $postCopyField])) {
	$startIdx = (int)$_POST['imf-' . $postCopyField] + 1;
}
$stopIdx = $startIdx + $postCopyNumber;

for ($i = $startIdx; $i < $stopIdx; ++$i) {
	$_POST['imf-' . $postCopyField] = $i;

	$items->readItemData($getItemId, $gCurrentOrgId);

	if ($getItemId == 0) {
		$items->getNewItemId($gCurrentOrgId);
	}

	// check all item fields
	foreach ($items->mItemFields as $itemField) {
		$postId = 'imf-' . $itemField->getValue('imf_id');

		if (isset($_POST[$postId])) {
			if (strlen($_POST[$postId]) === 0 && $itemField->getValue('imf_mandatory') == 1) {
				$gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array(convlanguagePIM($itemField->getValue('imf_name')))));
			}

			// Write value from field to the item class object
			if (!$items->setValue($itemField->getValue('imf_name_intern'), $_POST[$postId])) {
				$gMessage->show($gL10n->get('SYS_DATABASE_ERROR'), $gL10n->get('SYS_ERROR'));
			}
		}
		elseif ($itemField->getValue('imf_type') === 'CHECKBOX') {
			// Set value to '0' for unchecked checkboxes
			$items->setValue($itemField->getValue('imf_name_intern'), '0');
		}
	}

	// Save item data to database
	$gDb->startTransaction();

	try {
		$items->saveItemData();
	}
	catch (AdmException $e) {
		$gMessage->setForwardUrl($gNavigation->getPreviousUrl());
		$gNavigation->deleteLastUrl();
		$e->showHtml();
	}

	$gDb->endTransaction();
}

// Send notification to all users
$items->sendNotification($gCurrentOrgId);

if ($postRedirect == 1) {
	$gNavigation->deleteLastUrl();

	// Go back to item view
	if ($gNavigation->count() > 2) {
		$gNavigation->deleteLastUrl();
	}

	$gMessage->setForwardUrl($gNavigation->getUrl(), 1000);
	$gMessage->show($gL10n->get('SYS_SAVE_DATA'));
}