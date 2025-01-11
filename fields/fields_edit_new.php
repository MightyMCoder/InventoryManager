<?php
/**
 ***********************************************************************************************
 * Script to create and edit item fields in the InventoryManager plugin
 *
 * @see         https://github.com/MightyMCoder/InventoryManager/ The InventoryManager GitHub project
 * @author      MightyMCoder
 * @copyright   2024 - today MightyMCoder
 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 only
 * 
 * Parameters:
 * 
 * imf_id               : ID of the item field that should be edited
 * field_name           : Name of the field that should be set
 * redirect_to_import   : If true, the user will be redirected to the import page after saving the field
 * 
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../../adm_program/system/common.php');
require_once(__DIR__ . '/../common_function.php');
require_once(__DIR__ . '/../classes/configtable.php');

// Access only with valid login
require_once(__DIR__ . '/../../../adm_program/system/login_valid.php');

// Initialize and check the parameters
$getimfId = admFuncVariableIsValid($_GET, 'imf_id', 'int');
$getFieldName = admFuncVariableIsValid($_GET, 'field_name', 'string', array('defaultValue' => "", 'directOutput' => true));
$getRedirectToImport = admFuncVariableIsValid($_GET, 'redirect_to_import', 'bool', array('defaultValue' => false));

$pPreferences = new CConfigTablePIM();
$pPreferences->read();

// only authorized user are allowed to start this module
if (!isUserAuthorizedForPreferencesPIM()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// set headline of the script
$headline = $getimfId > 0 ? $gL10n->get('PLG_INVENTORY_MANAGER_ITEMFIELD_EDIT') : $gL10n->get('PLG_INVENTORY_MANAGER_ITEMFIELD_CREATE');
$gNavigation->addUrl(CURRENT_URL, $headline);

$itemField = new TableAccess($gDb, TBL_INVENTORY_MANAGER_FIELDS, 'imf');

if ($getimfId > 0) {
    $itemField->readDataById($getimfId);

    // Check if the field belongs to the current organization
    if ($itemField->getValue('imf_org_id') > 0 && (int) $itemField->getValue('imf_org_id') !== (int) $gCurrentOrgId) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }
}

// Set the name of the field if it was passed as a parameter
if ($getFieldName !== "") {
    $itemField->setValue('imf_name', $getFieldName);
}

if (isset($_SESSION['fields_request'])) {
    // User returned to this form due to incorrect input, now write the previously entered content into the object
    $itemField->setArray($_SESSION['fields_request']);
    unset($_SESSION['fields_request']);
}

// create html page object
$page = new HtmlPage('plg-inventory-manager-fields-edit-new', $headline);

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
    $("#imf_type").click(function() { setValueList(); });
', true);

// show form
$form = new HtmlForm('item_fields_edit_form', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_IM . '/fields/fields_function.php', ['imf_id' => $getimfId, 'mode' => 1, 'redirect_to_import' => $getRedirectToImport]), $page);

$form->addInput('imf_name', $gL10n->get('SYS_NAME'), $itemField->getValue('imf_name', 'database'), array(
    'maxLength' => 100,
    'property' => $itemField->getValue('imf_system') == 1 ? HtmlForm::FIELD_DISABLED : HtmlForm::FIELD_REQUIRED
    )
);

if ($getimfId > 0) {
    $form->addInput('imf_name_intern', $gL10n->get('SYS_INTERNAL_NAME'), $itemField->getValue('imf_name_intern'), array(
        'maxLength' => 100,
        'property' => HtmlForm::FIELD_DISABLED,
        'helpTextIdLabel' => 'SYS_INTERNAL_NAME_DESC'
        )
    );
}

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

//bei Systemfeldern darf der Datentyp nicht mehr veraendert werden
$form->addSelectBox('imf_type', $gL10n->get('ORG_DATATYPE'), $itemFieldText, array(
    'property' => $itemField->getValue('imf_system') == 1 ? HtmlForm::FIELD_DISABLED : HtmlForm::FIELD_REQUIRED,
    'defaultValue' => $itemField->getValue('imf_type')
    )
);

$form->addMultilineTextInput('imf_value_list', $gL10n->get('ORG_VALUE_LIST'), (string)$itemField->getValue('imf_value_list', 'database'), 6, array(
    'property' => HtmlForm::FIELD_REQUIRED,
    'helpTextIdLabel' => 'ORG_VALUE_LIST_DESC'
    )
);

$form->addCheckbox('imf_mandatory', $gL10n->get('SYS_REQUIRED_INPUT'), (bool)$itemField->getValue('imf_mandatory'), array(
    'property' => $itemField->getValue('imf_system') == 1 ? HtmlForm::FIELD_DISABLED : HtmlForm::FIELD_DEFAULT,
    'icon' => 'fa-asterisk'
    )
);

$form->addMultilineTextInput('imf_description', $gL10n->get('SYS_DESCRIPTION'), $itemField->getValue('imf_description'), 3);

$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array(
    'icon' => 'fa-check',
    'class' => 'offset-sm-3'
    )
);

$form->addHtml(admFuncShowCreateChangeInfoById(
    (int)$itemField->getValue('imf_usr_id_create'), $itemField->getValue('imf_timestamp_create'),
    (int)$itemField->getValue('imf_usr_id_change'), $itemField->getValue('imf_timestamp_change')
));

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();
