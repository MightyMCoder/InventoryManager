<?php
/**
 ***********************************************************************************************
 * Create or edit a item profile
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * item_id    : ID of the item who should be edited
 * copy      : true - The item of the item_id will be copied and the base for this new item
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/classes/items.php');
require_once(__DIR__ . '/classes/configtable.php');
require_once(__DIR__ . '/common_function.php');

// Initialize and check the parameters
$getItemId = admFuncVariableIsValid($_GET, 'item_id', 'int');
$getCopy  = admFuncVariableIsValid($_GET, 'copy', 'bool');

$pPreferences = new CConfigTablePIM();
$pPreferences->read();

$items = new CItems($gDb, $gCurrentOrgId);
$items->readItemData($getItemId, $gCurrentOrgId);

// Set headline of the script
$headline = $getItemId === 0 ? $gL10n->get('PLG_INVENTORY_MANAGER_ITEM_CREATE') : $gL10n->get('PLG_INVENTORY_MANAGER_ITEM_EDIT');
if ($getCopy) {
    $getItemId = 0;
}

$gNavigation->addUrl(CURRENT_URL, $headline);

// Create HTML page object
$page = new HtmlPage('plg-inventory-manager-items-edit-new', $headline);
$page->addJavascriptFile('adm_program/libs/zxcvbn/dist/zxcvbn.js');

// Don't show menu items (copy/print...) if a new item is created
if ($getItemId != 0) {
	// show link to view profile field change history
    if ($gSettingsManager->getBool('profile_log_edit_fields')) {
        $page->addPageFunctionsMenuItem('menu_item_change_history', $gL10n->get('SYS_CHANGE_HISTORY'),
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER . '/items_history.php', array('item_id' => $getItemId)), 'fa-history');
    }

    if (isUserAuthorizedForPreferences()) {
        $page->addPageFunctionsMenuItem('menu_copy_item', $gL10n->get('PLG_INVENTORY_MANAGER_ITEM_COPY'),
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER . '/items_edit_new.php', array('item_id' => $getItemId, 'copy' => 1)), 'fa-clone');
    }
}

// Create HTML form
$form = new HtmlForm('edit_item_form', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER . '/items_save.php', ['item_id' => $getItemId]), $page);

foreach ($items->mItemFields as $itemField) {
    $fieldProperty = isUserAuthorizedForPreferences() ? HtmlForm::FIELD_DEFAULT : HtmlForm::FIELD_DISABLED;
    $helpId = '';
    $imfNameIntern = $itemField->getValue('imf_name_intern');

    if ($itemField->getValue('imf_type') === 'DATE' && $itemField->getValue('imf_sequence') === '1') {
        $form->addInput('dummy', 'dummy', 'dummy', ['type' => 'date', 'property' => HtmlForm::FIELD_HIDDEN]);
    }

    if ($items->getProperty($imfNameIntern, 'imf_mandatory') == 1 && isUserAuthorizedForPreferences()) {
        $fieldProperty = HtmlForm::FIELD_REQUIRED;
    }

    switch ($items->getProperty($imfNameIntern, 'imf_type')) {
        case 'CHECKBOX':
            $form->addCheckbox(
                'imf-' . $items->getProperty($imfNameIntern, 'imf_id'),
                convlanguagePIM($items->getProperty($imfNameIntern, 'imf_name')),
                (bool) $items->getValue($imfNameIntern),
                array(
                    'property' => $fieldProperty,
                    'helpTextIdLabel' => $helpId,
                    'icon' => $items->getProperty($imfNameIntern, 'imf_icon', 'database')
                )
            );
            break;
        case 'DROPDOWN':
            $form->addSelectBox(
                'imf-' . $items->getProperty($imfNameIntern, 'imf_id'),
                convlanguagePIM($items->getProperty($imfNameIntern, 'imf_name')),
                $items->getProperty($imfNameIntern, 'imf_value_list'),
                array(
                    'property' => $fieldProperty,
                    'defaultValue' => $items->getValue($imfNameIntern, 'database'),
                    'helpTextIdLabel' => $helpId,
                    'icon' => $items->getProperty($imfNameIntern, 'imf_icon', 'database')
                )
            );
            break;
        case 'RADIO_BUTTON':
            $form->addRadioButton(
                'imf-' . $items->getProperty($imfNameIntern, 'imf_id'),
                convlanguagePIM($items->getProperty($imfNameIntern, 'imf_name')),
                $items->getProperty($imfNameIntern, 'imf_value_list'),
                array(
                    'property' => $fieldProperty,
                    'defaultValue' => $items->getValue($imfNameIntern, 'database'),
                    'showNoValueButton' => $items->getProperty($imfNameIntern, 'imf_mandatory') == 0,
                    'helpTextIdLabel' => $helpId,
                    'icon' => $items->getProperty($imfNameIntern, 'imf_icon', 'database')
                )
            );
            break;
        case 'TEXT_BIG':
            $form->addMultilineTextInput(
                'imf-' . $items->getProperty($imfNameIntern, 'imf_id'),
                convlanguagePIM($items->getProperty($imfNameIntern, 'imf_name')),
                $items->getValue($imfNameIntern),
                3,
                array(
                    'maxLength' => 4000,
                    'property' => $fieldProperty,
                    'helpTextIdLabel' => $helpId,
                    'icon' => $items->getProperty($imfNameIntern, 'imf_icon', 'database')
                )
            );
            break;
        default:
            $fieldType = 'text';
            $maxlength = '50';

            if ($imfNameIntern === 'KEEPER') {
                $sql = 'SELECT usr_id, CONCAT(last_name.usd_value, \', \', first_name.usd_value, IFNULL(CONCAT(\', \', postcode.usd_value),\'\'), IFNULL(CONCAT(\' \', city.usd_value),\'\'), IFNULL(CONCAT(\', \', street.usd_value),\'\') ) as name
                        FROM ' . TBL_USERS . '
                        JOIN ' . TBL_USER_DATA . ' as last_name ON last_name.usd_usr_id = usr_id AND last_name.usd_usf_id = ' . $gProfileFields->getProperty('LAST_NAME', 'usf_id') . '
                        JOIN ' . TBL_USER_DATA . ' as first_name ON first_name.usd_usr_id = usr_id AND first_name.usd_usf_id = ' . $gProfileFields->getProperty('FIRST_NAME', 'usf_id') . '
                        LEFT JOIN ' . TBL_USER_DATA . ' as postcode ON postcode.usd_usr_id = usr_id AND postcode.usd_usf_id = ' . $gProfileFields->getProperty('POSTCODE', 'usf_id') . '
                        LEFT JOIN ' . TBL_USER_DATA . ' as city ON city.usd_usr_id = usr_id AND city.usd_usf_id = ' . $gProfileFields->getProperty('CITY', 'usf_id') . '
                        LEFT JOIN ' . TBL_USER_DATA . ' as street ON street.usd_usr_id = usr_id AND street.usd_usf_id = ' . $gProfileFields->getProperty('ADDRESS', 'usf_id') . '
                        WHERE usr_valid = 1 AND EXISTS (SELECT 1 FROM ' . TBL_MEMBERS . ', ' . TBL_ROLES . ', ' . TBL_CATEGORIES . ' WHERE mem_usr_id = usr_id AND mem_rol_id = rol_id AND mem_begin <= \'' . DATE_NOW . '\' AND mem_end > \'' . DATE_NOW . '\' AND rol_valid = 1 AND rol_cat_id = cat_id AND (cat_org_id = ' . $gCurrentOrgId . ' OR cat_org_id IS NULL)) ORDER BY last_name.usd_value, first_name.usd_value;';
                $form->addSelectBoxFromSql(
                    'imf-' . $items->getProperty($imfNameIntern, 'imf_id'),
                    convlanguagePIM($items->getProperty($imfNameIntern, 'imf_name')),
                    $gDb,
                    $sql,
                    array(
                        'property' => $fieldProperty,
                        'helpTextIdLabel' => $helpId,
                        'icon' => $items->getProperty($imfNameIntern, 'imf_icon', 'database'),
                        'defaultValue' => $items->getValue($imfNameIntern),
                        'multiselect' => false
                    )
                );
            }
            else {
                if ($items->getProperty($imfNameIntern, 'imf_type') === 'DATE') {
                    $fieldType = $imfNameIntern === 'BIRTHDAY' ? 'birthday' : 'date';
                    $maxlength = '10';
                }
                elseif ($items->getProperty($imfNameIntern, 'imf_type') === 'NUMBER') {
                    $fieldType = 'number';
                    $maxlength = [0, 9999999999, 1];
                }

                $form->addInput(
                    'imf-' . $items->getProperty($imfNameIntern, 'imf_id'),
                    convlanguagePIM($items->getProperty($imfNameIntern, 'imf_name')),
                    $items->getValue($imfNameIntern),
                    array(
                        'type' => $fieldType,
                        'maxLength' => $maxlength,
                        'property' => $fieldProperty,
                        'helpTextIdLabel' => $helpId,
                        'icon' => $items->getProperty($imfNameIntern, 'imf_icon', 'database')
                    )
                );
            }
            break;
    }
}

if ($getCopy) {
    $form->addLine();
    $form->addDescription($gL10n->get('PLG_INVENTORY_MANAGER_COPY_PREFERENCES') . '<br/>');
    $form->addInput('copy_number', $gL10n->get('PLG_INVENTORY_MANAGER_NUMBER'), 1, array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1, 'helpTextIdInline' => 'PLG_INVENTORY_MANAGER_NUMBER_DESC'));
    $sql = 'SELECT imf_id, imf_name FROM ' . TBL_INVENTORY_MANAGER_FIELDS . ' WHERE imf_type = \'NUMBER\' AND (imf_org_id = ' . $gCurrentOrgId . ' OR imf_org_id IS NULL);';
    $form->addSelectBoxFromSql('copy_field', $gL10n->get('PLG_INVENTORY_MANAGER_FIELD'), $gDb, $sql, array('multiselect' => false, 'helpTextIdInline' => 'PLG_INVENTORY_MANAGER_FIELD_DESC'));
    $form->addLine();
}

$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => 'fa-check', 'class' => 'offset-sm-3'));

$infoItem = new TableAccess($gDb, TBL_INVENTORY_MANAGER_ITEMS, 'imi', (int) $getItemId);

// Show information about item who creates the recordset and changed it
$form->addHtml(admFuncShowCreateChangeInfoById(
    (int) $infoItem->getValue('imi_usr_id_create'), $infoItem->getValue('imi_timestamp_create'),
    (int) $infoItem->getValue('imi_usr_id_change'), $infoItem->getValue('imi_timestamp_change')
));

$page->addHtml($form->show(false));
$page->show();
