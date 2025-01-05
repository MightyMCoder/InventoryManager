
<?php
/**
 ***********************************************************************************************
 * Import items from a csv file
 *
 * @see         https://github.com/MightyMCoder/InventoryManager/ The InventoryManager GitHub project
 * @author      MightyMCoder
 * @copyright   2024 - today MightyMCoder
 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../../adm_program/system/common.php');
require_once(__DIR__ . '/../common_function.php');
require_once(__DIR__ . '/../classes/items.php');

// Access only with valid login
require_once(__DIR__ . '/../../../adm_program/system/login_valid.php');

// only authorized user are allowed to start this module
if (!isUserAuthorizedForPreferences()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$_SESSION['import_csv_request'] = $_POST;

// check the CSRF token of the form against the session token
SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);

// go through each line from the file one by one and create the user in the DB
$line = reset($_SESSION['import_data']);
$firstRowTitle = array_key_exists('first_row', $_POST);
$startRow = 0;
$importedFields = array();

// create array with all profile fields that where assigned to columns of the import file
foreach ($_POST as $formFieldId => $importFileColumn) {
    if ($importFileColumn !== '' && $formFieldId !== 'admidio-csrf-token' && $formFieldId !== 'first_row') {
        $importItemFields[$formFieldId] = (int)$importFileColumn;
    }
}

if ($firstRowTitle) {
    // skip first line, because here are the column names
    $line = next($_SESSION['import_data']);
    $startRow = 1;
}

$assignedFieldColumn = array();

for ($i = $startRow, $iMax = count($_SESSION['import_data']); $i < $iMax; ++$i) {
    $row = array();
    foreach ($line as $columnKey => $columnValue) {
        if (empty($columnValue)) {
            $columnValue = '';
        }

        // get usf id or database column name
        $fieldId = array_search($columnKey, $importItemFields);
        if ($fieldId !== false) {
            $row[$fieldId] = trim(strip_tags($columnValue));
        }
    }
    $assignedFieldColumn[] = $row;
    $line = next($_SESSION['import_data']);
}

$items = new CItems($gDb, $gCurrentOrgId);
$items->readItems($gCurrentOrgId);
$importSuccess = false;

foreach ($items->items as $fieldId => $value) {
    $items->readItemData($value['imi_id'], $gCurrentOrgId);
    $i = 0;

    foreach ($items->mItemData as $itemData) {
        foreach ($assignedFieldColumn as $row => $values) {
            if ($itemData->getValue('imd_value') == $values['ITEMNAME']) {
                unset($assignedFieldColumn[$row]);
                continue;
            }
        }
    }
}

$valueList = array();
$importedItemData = array();

foreach ($assignedFieldColumn as $row => $values) {
    foreach ($items->mItemFields as $fields){
        $imfNameIntern = $fields->getValue('imf_name_intern');
        if ($fields->getValue('imf_type')=='CHECKBOX') {
            if ($values[$imfNameIntern] === $gL10n->get('SYS_YES')) {
                $values[$imfNameIntern] = 1;
            }
            else {
                $values[$imfNameIntern] = 0;
            }
        }

        if($imfNameIntern === 'ITEMNAME') {
            if ($values[$imfNameIntern] === '') {
                break;
            }
            $val = $values[$imfNameIntern];
        }
        elseif($imfNameIntern === 'KEEPER') {
            $sql = 'SELECT usr_id, CONCAT(last_name.usd_value, \', \', first_name.usd_value, IFNULL(CONCAT(\', \', postcode.usd_value),\'\'), IFNULL(CONCAT(\' \', city.usd_value),\'\'), IFNULL(CONCAT(\', \', street.usd_value),\'\') ) as name
                FROM ' . TBL_USERS . '
                JOIN ' . TBL_USER_DATA . ' as last_name ON last_name.usd_usr_id = usr_id AND last_name.usd_usf_id = ' . $gProfileFields->getProperty('LAST_NAME', 'usf_id') . '
                JOIN ' . TBL_USER_DATA . ' as first_name ON first_name.usd_usr_id = usr_id AND first_name.usd_usf_id = ' . $gProfileFields->getProperty('FIRST_NAME', 'usf_id') . '
                LEFT JOIN ' . TBL_USER_DATA . ' as postcode ON postcode.usd_usr_id = usr_id AND postcode.usd_usf_id = ' . $gProfileFields->getProperty('POSTCODE', 'usf_id') . '
                LEFT JOIN ' . TBL_USER_DATA . ' as city ON city.usd_usr_id = usr_id AND city.usd_usf_id = ' . $gProfileFields->getProperty('CITY', 'usf_id') . '
                LEFT JOIN ' . TBL_USER_DATA . ' as street ON street.usd_usr_id = usr_id AND street.usd_usf_id = ' . $gProfileFields->getProperty('ADDRESS', 'usf_id') . '
                WHERE usr_valid = 1 AND EXISTS (SELECT 1 FROM ' . TBL_MEMBERS . ', ' . TBL_ROLES . ', ' . TBL_CATEGORIES . ' WHERE mem_usr_id = usr_id AND mem_rol_id = rol_id AND mem_begin <= \'' . DATE_NOW . '\' AND mem_end > \'' . DATE_NOW . '\' AND rol_valid = 1 AND rol_cat_id = cat_id AND (cat_org_id = ' . $gCurrentOrgId . ' OR cat_org_id IS NULL)) ORDER BY last_name.usd_value, first_name.usd_value;';
            $result = $gDb->queryPrepared($sql);

            while ($row = $result->fetch()) {
                if ($row['name'] == $values[$imfNameIntern]) {
                    $val = $row['usr_id'];
                    break;
                }
                $val = '';
            }
        }
        elseif($imfNameIntern === 'CATEGORY') {
            // Merge the arrays
            $valueList = array_merge($items->getProperty($imfNameIntern, 'imf_value_list'), $valueList);
            // Remove duplicates
            $valueList = array_unique($valueList);
            // Re-index the array starting from 1
            $valueList = array_combine(range(1, count($valueList)), array_values($valueList));

            $val = array_search($values[$imfNameIntern], $valueList);

            if ($val === false) {
                if ($values[$imfNameIntern] == '') {
                    $val = array_search($valueList[1], $valueList);
                }
                else {
                    $itemField = new TableAccess($gDb, TBL_INVENTORY_MANAGER_FIELDS, 'imf');
                    $itemField->readDataById($items->mItemFields[$imfNameIntern]->getValue('imf_id'));
                    $valueList[] = $values[$imfNameIntern];
                    $itemField->setValue("imf_value_list", $string = implode("\n", $valueList));

                    // Save data to the database
                    $returnCode = $itemField->save();

                    if ($returnCode < 0) {
                        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
                        // => EXIT
                    }
                    else {
                        $val = array_search($values[$imfNameIntern], $valueList);
                    }
                }
            }
        }
        else {
            $val = $values[$imfNameIntern];
        }
        $_POST['imf-' . $fields->getValue('imf_id')] = '' . $val . '';
        $ItemData[] = array($items->mItemFields[$imfNameIntern]->getValue('imf_name') => array('oldValue' => "", 'newValue' => $val));
    }

    $importedItemData[] = $ItemData;
    $ItemData = array();
    if (count($assignedFieldColumn) > 0) {
 
        // save item
        $_POST['redirect'] = 0;
        $_POST['imported'] = 1;
        require(__DIR__ . '/../items/items_save.php');
        $importSuccess = true;
        unset($_POST);
    }   
}

// Send notification to all users
$items->sendNotification($gCurrentOrgId, $importedItemData);

$gNavigation->deleteLastUrl();

// Go back to item view
if ($gNavigation->count() > 2) {
	$gNavigation->deleteLastUrl();
}

 if ($importSuccess) {
    $gMessage->setForwardUrl($gNavigation->getUrl(), 1000);
    $gMessage->show($gL10n->get('SYS_SAVE_DATA'));
}
else {
    $gMessage->setForwardUrl($gNavigation->getUrl(), 1000);
    $gMessage->show($gL10n->get('SYS_REDIRECT'));
}