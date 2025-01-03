<?php
/**
 ***********************************************************************************************
 * Show history of item field changes in the InventoryManager plugin
 * 
 * @see         https://github.com/MightyMCoder/InventoryManager/ The InventoryManager GitHub project
 * @author      MightyMCoder
 * @copyright   2024 - today MightyMCoder
 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 only
 * 
 * Parameters:
 * 
 * item_id         : If set, only show the item field history of that item
 * filter_date_from: Set to the actual date if no date information is delivered
 * filter_date_to  : Set to 31.12.9999 if no date information is delivered
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/common_function.php');
require_once(__DIR__ . '/classes/items.php');
require_once(__DIR__ . '/classes/configtable.php');

// Access only with valid login
require_once(__DIR__ . '/../../adm_program/system/login_valid.php');

// calculate default date from which the item fields history should be shown
$filterDateFrom = DateTime::createFromFormat('Y-m-d', DATE_NOW);
$filterDateFrom->modify('-'.$gSettingsManager->getInt('contacts_field_history_days').' day');

$getItemId    = admFuncVariableIsValid($_GET, 'item_id', 'int');
$getDateFrom  = admFuncVariableIsValid($_GET, 'filter_date_from', 'date', ['defaultValue' => $filterDateFrom->format($gSettingsManager->getString('system_date'))]);
$getDateTo    = admFuncVariableIsValid($_GET, 'filter_date_to', 'date', ['defaultValue' => DATE_NOW]);

$items = new CItems($gDb, $gCurrentOrgId);
$items->readItemData($getItemId, $gCurrentOrgId);

$user = new User($gDb, $gProfileFields);

$headline = $gL10n->get('SYS_CHANGE_HISTORY_OF', array($items->getValue('ITEMNAME')));

// if profile log is activated then the item field history will be shown otherwise show error
if (!$gSettingsManager->getBool('profile_log_edit_fields')) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// add page to navigation history
$gNavigation->addUrl(CURRENT_URL, $headline);

// filter_date_from and filter_date_to can have different formats
// now we try to get a default format for intern use and html output
$objDateFrom = DateTime::createFromFormat('Y-m-d', $getDateFrom) ?: DateTime::createFromFormat($gSettingsManager->getString('system_date'), $getDateFrom) ?: DateTime::createFromFormat('Y-m-d', '1970-01-01');
$objDateTo   = DateTime::createFromFormat('Y-m-d', $getDateTo) ?: DateTime::createFromFormat($gSettingsManager->getString('system_date'), $getDateTo) ?: DateTime::createFromFormat('Y-m-d', '1970-01-01');

// DateTo should be greater than DateFrom
if ($objDateFrom > $objDateTo) {
    $gMessage->show($gL10n->get('SYS_DATE_END_BEFORE_BEGIN'));
    // => EXIT
}

$dateFromIntern = $objDateFrom->format('Y-m-d');
$dateFromHtml   = $objDateFrom->format($gSettingsManager->getString('system_date'));
$dateToIntern   = $objDateTo->format('Y-m-d');
$dateToHtml     = $objDateTo->format($gSettingsManager->getString('system_date'));

// create select statement with all necessary data
$sql = 'SELECT iml_imi_id, iml_imf_id, iml_usr_id_create, iml_timestamp_create, iml_value_old, iml_value_new 
        FROM '.TBL_INVENTORY_MANAGER_LOG.'
        WHERE iml_timestamp_create BETWEEN ? AND ? 
        AND iml_imi_id = ?
        ORDER BY iml_timestamp_create DESC;';
      
$fieldHistoryStatement = $gDb->queryPrepared($sql, array($dateFromIntern.' 00:00:00', $dateToIntern.' 23:59:59', $getItemId));

if ($fieldHistoryStatement->rowCount() === 0) {
    // message is shown, so delete this page from navigation stack
    $gNavigation->deleteLastUrl();
    $gMessage->setForwardUrl($gNavigation->getUrl(), 1000);
    $gMessage->show($gL10n->get('SYS_NO_CHANGES_LOGGED_PROFIL', array($items->getValue('ITEMNAME'))));
    // => EXIT
}

// create html page object
$page = new HtmlPage('plg-inventory-manager-items-history', $headline);

// create filter menu with input elements for Startdate and Enddate
$FilterNavbar = new HtmlNavbar('menu_profile_field_history_filter', '', null, 'filter');
$form = new HtmlForm('navbar_filter_form', ADMIDIO_URL. FOLDER_PLUGINS . PLUGIN_FOLDER .'/items_history.php', $page, array('type' => 'navbar', 'setFocus' => false));
$form->addInput('filter_date_from', $gL10n->get('SYS_START'), $dateFromHtml, array('type' => 'date', 'maxLength' => 10));
$form->addInput('filter_date_to', $gL10n->get('SYS_END'), $dateToHtml, array('type' => 'date', 'maxLength' => 10));
$form->addInput('item_id', '', $getItemId, array('property' => HtmlForm::FIELD_HIDDEN));
$form->addSubmitButton('btn_send', $gL10n->get('SYS_OK'));
$FilterNavbar->addForm($form->show(false));
$page->addHtml($FilterNavbar->show());

$table = new HtmlTable('profile_field_history_table', $page, true, true);

$columnHeading = array(
    $gL10n->get('SYS_FIELD'),
    $gL10n->get('SYS_NEW_VALUE'),
    $gL10n->get('SYS_PREVIOUS_VALUE'),
    $gL10n->get('SYS_EDITED_BY'),
    $gL10n->get('SYS_CHANGED_AT')
);

$table->setDatatablesOrderColumns(array(array(5, 'desc')));
$table->addRowHeadingByArray($columnHeading);

while ($row = $fieldHistoryStatement->fetch()) {
    $timestampCreate = DateTime::createFromFormat('Y-m-d H:i:s', $row['iml_timestamp_create']);
    $columnValues    = array();
    $columnValues[]  = convlanguagePIM($items->getPropertyById((int) $row['iml_imf_id'], 'imf_name'));

    $imlValueNew = $items->getHtmlValue($items->getPropertyById((int) $row['iml_imf_id'], 'imf_name_intern'), $row['iml_value_new']);
    if ($imlValueNew !== '') {
        if ($items->getPropertyById((int) $row['iml_imf_id'], 'imf_name_intern') === 'KEEPER') {
            $user->readDataById((int) $imlValueNew);
            $columnValues[] = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $user->getValue('usr_uuid'))).'">'.$user->getValue('LAST_NAME').', '.$user->getValue('FIRST_NAME').'</a>';
        }
        else {
            $columnValues[] = $imlValueNew;
        }
    }
    else {
        $columnValues[] = '&nbsp;';
    }
    
    $imlValueOld = $items->getHtmlValue($items->getPropertyById((int) $row['iml_imf_id'], 'imf_name_intern'), $row['iml_value_old']);
    if ($imlValueOld !== '') {
        if ($items->getPropertyById((int) $row['iml_imf_id'], 'imf_name_intern') === 'KEEPER') {
            $user->readDataById((int) $imlValueOld);
            $columnValues[] = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $user->getValue('usr_uuid'))).'">'.$user->getValue('LAST_NAME').', '.$user->getValue('FIRST_NAME').'</a>';
        }
        else {
            $columnValues[] = $imlValueOld;
        }
    }
    else {
        $columnValues[] = '&nbsp;';
    }
   
    $user->readDataById($row['iml_usr_id_create']);
    $columnValues[] = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $user->getValue('usr_uuid'))).'">'.$user->getValue('LAST_NAME').', '.$user->getValue('FIRST_NAME').'</a>';
    $columnValues[] = $timestampCreate->format($gSettingsManager->getString('system_date').' '.$gSettingsManager->getString('system_time'));
    $table->addRowByArray($columnValues);
}

$page->addHtml($table->show());
$page->show();
