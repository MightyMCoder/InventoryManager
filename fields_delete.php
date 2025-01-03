<?php
/**
 ***********************************************************************************************
 * Script to delete an item field in the InventoryManager plugin
 *
 * @see         https://github.com/MightyMCoder/InventoryManager/ The InventoryManager GitHub project
 * @author      MightyMCoder
 * @copyright   2024 - today MightyMCoder
 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 only
 * 
 * Parameters:
 * 
 * mode       : 1 - Menu and preview of the item field that should be deleted
 *              2 - Delete an item field
 * imf_id     : ID of the item field that should be deleted
 * 
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/common_function.php');
require_once(__DIR__ . '/classes/items.php');
require_once(__DIR__ . '/classes/configtable.php');

// Access only with valid login
require_once(__DIR__ . '/../../adm_program/system/login_valid.php');

$getMode  = admFuncVariableIsValid($_GET, 'mode', 'numeric', array('defaultValue' => 1));
$getimfId = admFuncVariableIsValid($_GET, 'imf_id', 'int');

$pPreferences = new CConfigTablePIM();
$pPreferences->read();

// only authorized user are allowed to start this module
if (!isUserAuthorizedForPreferences()) {
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$itemField = new TableAccess($gDb, TBL_INVENTORY_MANAGER_FIELDS, 'imf', $getimfId);

switch ($getMode) {
	case 1:
		$headline = $gL10n->get('PLG_INVENTORY_MANAGER_ITEMFIELD_DELETE');

		// create html page object
		$page = new HtmlPage('plg-inventory-manager-fields-delete', $headline);

		$page->addJavascript('
		function setValueList() {
			if ($("#imf_type").val() === "DROPDOWN" || $("#imf_type").val() === "RADIO_BUTTON") {
				$("#imf_value_list_group").show("slow");
				$("#imf_value_list").attr("required", "required");
			} else {
				$("#imf_value_list").removeAttr("required");
				$("#imf_value_list_group").hide();
			}
		}

		setValueList();
		$("#imf_type").click(function() { setValueList(); });', true);

		// add current url to navigation stack
		$gNavigation->addUrl(CURRENT_URL, $headline);

		$page->addHtml('<p class="lead">' . $gL10n->get('PLG_INVENTORY_MANAGER_ITEMFIELD_DELETE_DESC') . '</p>');

		// show form
		$form = new HtmlForm('itemfield_delete_form', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER . '/fields_delete.php', array('imf_id' => $getimfId, 'mode' => 2)), $page);

		$form->addInput('imf_name', $gL10n->get('SYS_NAME'), $itemField->getValue('imf_name', 'database'), array('maxLength' => 100, 'property' => HtmlForm::FIELD_DISABLED));

		// show internal field name for information
		$form->addInput('imf_name_intern', $gL10n->get('SYS_INTERNAL_NAME'), $itemField->getValue('imf_name_intern'), array('maxLength' => 100, 'property' => HtmlForm::FIELD_DISABLED));

		$itemFieldText = array(
			'CHECKBOX' => $gL10n->get('SYS_CHECKBOX'),
			'DATE' => $gL10n->get('SYS_DATE'),
			'DECIMAL' => $gL10n->get('SYS_DECIMAL_NUMBER'),
			'DROPDOWN' => $gL10n->get('SYS_DROPDOWN_LISTBOX'),
			'NUMBER' => $gL10n->get('SYS_NUMBER'),
			'RADIO_BUTTON' => $gL10n->get('SYS_RADIO_BUTTON'),
			'TEXT' => $gL10n->get('SYS_TEXT') . ' (100 ' . $gL10n->get('SYS_CHARACTERS') . ')',
			'TEXT_BIG' => $gL10n->get('SYS_TEXT') . ' (4000 ' . $gL10n->get('SYS_CHARACTERS') . ')',
		);
		asort($itemFieldText);

		$form->addInput('imf_type', $gL10n->get('ORG_DATATYPE'), $itemFieldText[$itemField->getValue('imf_type')], array('maxLength' => 30, 'property' => HtmlForm::FIELD_DISABLED));

		$form->addMultilineTextInput('imf_value_list', $gL10n->get('ORG_VALUE_LIST'), (string) $itemField->getValue('imf_value_list', 'database'), 6, array('property' => HtmlForm::FIELD_DISABLED));

		$form->addMultilineTextInput('imf_description', $gL10n->get('SYS_DESCRIPTION'), $itemField->getValue('imf_description'), 3, array('property' => HtmlForm::FIELD_DISABLED));

		$form->addSubmitButton('btn_delete', $gL10n->get('SYS_DELETE'), array('icon' => 'fa-trash-alt', 'class' => ' offset-sm-3'));

		$page->addHtml($form->show(false));
		$page->show();
		break;

	case 2:
		$sql = 'DELETE FROM ' . TBL_INVENTORY_MANAGER_LOG . ' WHERE iml_imf_id = ?;';
    	$gDb->queryPrepared($sql, array($getimfId));
    	
    	$sql = 'DELETE FROM '.TBL_INVENTORY_MANAGER_DATA.' WHERE imd_imf_id = ?;';
    	$gDb->queryPrepared($sql, array($getimfId));
    	
    	$sql = 'DELETE FROM '.TBL_INVENTORY_MANAGER_FIELDS.' WHERE imf_id = ? AND (imf_org_id = ? OR imf_org_id IS NULL);';
    	$gDb->queryPrepared($sql, array($getimfId, $gCurrentOrgId));
    	
    	// go back to item view
    	$gMessage->setForwardUrl($gNavigation->getPreviousUrl(), 1000);
    	$gMessage->show($gL10n->get('PLG_INVENTORY_MANAGER_ITEMFIELD_DELETED'));

    	break;
    	// => EXIT
}