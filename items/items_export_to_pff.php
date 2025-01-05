<?php
/**
 ***********************************************************************************************
 * Script to export item data to PFF in the InventoryManager plugin
 *
 * @see         https://github.com/MightyMCoder/InventoryManager/ The InventoryManager GitHub project
 * @author      MightyMCoder
 * @copyright   2024 - today MightyMCoder
 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 only
 * 
 * Parameters:
 * 
 * item_id : ID of the item to be exported
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../../adm_program/system/common.php');
require_once(__DIR__ . '/../classes/items.php');
require_once(__DIR__ . '/../common_function.php');
require_once(__DIR__ . '/../classes/configtable.php');

// Access only with valid login
require_once(__DIR__ . '/../../../adm_program/system/login_valid.php');

// Initialize and check the parameters
$getItemId = admFuncVariableIsValid($_GET, 'item_id', 'int');

$pimArray = array();

$pPreferences = new CConfigTablePIM();
$pPreferences->read();
$pPreferences->readPff();

if (substr_count($gNavigation->getUrl(), 'items_export_to_pff') === 1) {
	admRedirect(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER . '/inventory_manager.php');
	// => EXIT
}

$headline = $gL10n->get('PLG_INVENTORY_MANAGER_ITEM_PRINT');
$gNavigation->addUrl(CURRENT_URL, $headline);

if (!array_key_exists($pPreferences->config['Optionen']['interface_pff'], $pPreferences->configPff['Formular']['desc'])) {
	$gMessage->show($gL10n->get('PLG_INVENTORY_MANAGER_PFF_CONFIG_NOT_FOUND'));
}
else {
	$pimArray['form_id'] = $pPreferences->config['Optionen']['interface_pff'];
}

$items = new CItems($gDb, $gCurrentOrgId);
$items->readItemData($getItemId, $gCurrentOrgId);

foreach ($items->mItemFields as $itemField) {
	$imfNameIntern = $itemField->getValue('imf_name_intern');
	$content = $items->getValue($imfNameIntern, 'database');

	switch ($items->getProperty($imfNameIntern, 'imf_type')) {
		case 'DATE':
			$content = $items->getHtmlValue($imfNameIntern, $content);
			break;
		case 'DROPDOWN':
		case 'RADIO_BUTTON':
			$arrListValues = $items->getProperty($imfNameIntern, 'imf_value_list', 'text');
			$content = $arrListValues[$content];
			break;
		case 'CHECKBOX':
			$content = $content == 1 ? $gL10n->get('SYS_YES') : $gL10n->get('SYS_NO');
			break;
	}

	$pimArray['imf-' . $imfNameIntern] = $content;
}

admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . '/' . $pPreferences->pffDir() . '/createpdf.php', $pimArray));
