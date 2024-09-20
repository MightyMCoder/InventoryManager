<?php
/**
 ***********************************************************************************************
 * Create and edit item fields
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
 
/******************************************************************************
 * Parameters:
 *
 * imf_id : item field id that should be edited
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/common_function.php');
require_once(__DIR__ . '/classes/configtable.php');

// Initialize and check the parameters
$getimfId = admFuncVariableIsValid($_GET, 'imf_id', 'int');

$pPreferences = new CConfigTablePIM();
$pPreferences->read();

// only authorized user are allowed to start this module
if (!isUserAuthorizedForPreferences())
{
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// set headline of the script
if ($getimfId > 0)
{
    $headline = $gL10n->get('PLG_INVENTORY_MANAGER_ITEMFIELD_EDIT');
}
else
{
    $headline = $gL10n->get('PLG_INVENTORY_MANAGER_ITEMFIELD_CREATE');
}

$gNavigation->addUrl(CURRENT_URL, $headline);

$itemField = new TableAccess($gDb, TBL_INVENTORY_MANAGER_FIELDS, 'imf');

if ($getimfId > 0)
{
	$itemField->readDataById($getimfId);

    // Pruefung, ob das Feld zur aktuellen Organisation gehoert
    if ($itemField->getValue('imf_org_id') > 0
    && (int) $itemField->getValue('imf_org_id') !== (int) $gCurrentOrgId)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
}

if (isset($_SESSION['fields_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte ins Objekt schreiben
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
    $("#imf_type").click(function() { setValueList(); });',
    true
);

// show form
$form = new HtmlForm('item_fields_edit_form', SecurityUtils::encodeUrl(ADMIDIO_URL. FOLDER_PLUGINS . PLUGIN_FOLDER .'/fields_function.php', array('imf_id' => $getimfId, 'mode' => 1)), $page);

if ($itemField->getValue('imf_system') == 1)
{
    $form->addInput('imf_name', $gL10n->get('SYS_NAME'), $itemField->getValue('imf_name', 'database'),
                    array('maxLength' => 100, 'property' => HtmlForm::FIELD_DISABLED));
}
else
{
    $form->addInput('imf_name', $gL10n->get('SYS_NAME'), $itemField->getValue('imf_name', 'database'),
                    array('maxLength' => 100, 'property' => HtmlForm::FIELD_REQUIRED));
}

// show internal field name for information
if ($getimfId > 0)
{
    $form->addInput('imf_name_intern', $gL10n->get('SYS_INTERNAL_NAME'), $itemField->getValue('imf_name_intern'),
                    array('maxLength' => 100, 'property' => HtmlForm::FIELD_DISABLED, 'helpTextIdLabel' => 'SYS_INTERNAL_NAME_DESC'));
}

$itemFieldText = array(
	'CHECKBOX'     => $gL10n->get('SYS_CHECKBOX'),
    'DATE'         => $gL10n->get('SYS_DATE'),
    'DECIMAL'      => $gL10n->get('SYS_DECIMAL_NUMBER'),
    'DROPDOWN'     => $gL10n->get('SYS_DROPDOWN_LISTBOX'),
    'NUMBER'       => $gL10n->get('SYS_NUMBER'),
    'RADIO_BUTTON' => $gL10n->get('SYS_RADIO_BUTTON'),
    'TEXT'         => $gL10n->get('SYS_TEXT').' (100 '.$gL10n->get('SYS_CHARACTERS').')',
    'TEXT_BIG'     => $gL10n->get('SYS_TEXT').' (4000 '.$gL10n->get('SYS_CHARACTERS').')',
);
asort($itemFieldText);

if ($itemField->getValue('imf_system') == 1)
{
    //bei Systemfeldern darf der Datentyp nicht mehr veraendert werden
    $form->addInput('imf_type', $gL10n->get('ORG_DATATYPE'), $itemFieldText[$itemField->getValue('imf_type')],
              array('maxLength' => 30, 'property' => HtmlForm::FIELD_DISABLED));
}
else
{
    // fuer jeden Feldtypen einen Eintrag in der Combobox anlegen
    $form->addSelectBox('imf_type', $gL10n->get('ORG_DATATYPE'), $itemFieldText,
                  array('property' => HtmlForm::FIELD_REQUIRED, 'defaultValue' => $itemField->getValue('imf_type')));
}
$form->addMultilineTextInput('imf_value_list', $gL10n->get('ORG_VALUE_LIST'), $itemField->getValue('imf_value_list', 'database'), 6,
                       array('property' => HtmlForm::FIELD_REQUIRED, 'helpTextIdLabel' => 'ORG_VALUE_LIST_DESC'));

if ($itemField->getValue('imf_system') != 1)
{
	$form->addCheckbox('imf_mandatory', $gL10n->get('SYS_REQUIRED_INPUT'), (bool) $itemField->getValue('imf_mandatory'),
	    array('property' => HtmlForm::FIELD_DEFAULT,  'icon' => 'fa-asterisk'));
}

$form->addMultilineTextInput('imf_description', $gL10n->get('SYS_DESCRIPTION'), $itemField->getValue('imf_description'), 3);

$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => 'fa-check', 'class' => 'offset-sm-3'));
$form->addHtml(admFuncShowCreateChangeInfoById(
    (int) $itemField->getValue('imf_usr_id_create'), $itemField->getValue('imf_timestamp_create'),
    (int) $itemField->getValue('imf_usr_id_change'), $itemField->getValue('imf_timestamp_change')          
));

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();

