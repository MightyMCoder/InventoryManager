<?php

/**
 ***********************************************************************************************
 * Import items from a file
 *
 * @see         https://github.com/MightyMCoder/InventoryManager/ The InventoryManager GitHub project
 * @author      MightyMCoder
 * @copyright   2024 - today MightyMCoder
 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 only
 *
 *
 * Methods:
 * compareArrays(array $array1, array $array2)      : Compares two arrays to determine if they are
 *                                                    different based on specific criteria
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../../adm_program/system/common.php');
require_once(__DIR__ . '/../common_function.php');
require_once(__DIR__ . '/../classes/items.php');
require_once(__DIR__ . '/../classes/configtable.php');

// Access only with valid login
require_once(__DIR__ . '/../../../adm_program/system/login_valid.php');

// only authorized user are allowed to start this module
if (!isUserAuthorizedForPreferencesPIM()) {
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

$pPreferences = new CConfigTablePIM();
$pPreferences->read();

$user = new User($gDb, $gProfileFields);

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

// check if the item already exists
foreach ($items->items as $fieldId => $value) {
    $items->readItemData($value['imi_id'], $gCurrentOrgId);
    $itemValues = array();
    foreach ($items->mItemData as $key => $itemData) {
        $itemValue = $itemData->getValue('imd_value');
        if ($itemData->getValue('imf_name_intern') === 'KEEPER' || $itemData->getValue('imf_name_intern') === 'LAST_RECEIVER' ||
            $itemData->getValue('imf_name_intern') === 'IN_INVENTORY' || $itemData->getValue('imf_name_intern') === 'RECEIVED_ON' ||
            $itemData->getValue('imf_name_intern') === 'RECEIVED_BACK_ON') {
            continue;
        }

        if ($itemData->getValue('imf_name_intern') === 'CATEGORY') {
            $imfNameIntern = $itemData->getValue('imf_name_intern');
            // get value list of the item field
            $valueList = $items->getProperty($imfNameIntern, 'imf_value_list');

            // select the value from the value list
            $val = $valueList[$itemValue];

            $itemValues[] = array($itemData->getValue('imf_name_intern') => $val);
            continue;
        }

        $itemValues[] = array($itemData->getValue('imf_name_intern') => $itemValue);
    }
    $itemValues = array_merge_recursive(...$itemValues);

    if (count($assignedFieldColumn) === 0) {
        break;
    }

    foreach ($assignedFieldColumn as $key => $values) {
        $ret = compareArrays($values, $itemValues);
        if (!$ret) {
            unset($assignedFieldColumn[$key]);
        }
    }
}

// get all values of the item fields
$valueList = array();
$importedItemData = array();

foreach ($assignedFieldColumn as $row => $values) {
    foreach ($items->mItemFields as $fields) {
        $imfNameIntern = $fields->getValue('imf_name_intern');

        if (isset($values[$imfNameIntern])) {
            if ($fields->getValue('imf_type') == 'CHECKBOX') {
                if ($values[$imfNameIntern] === $gL10n->get('SYS_YES')) {
                    $values[$imfNameIntern] = 1;
                } else {
                    $values[$imfNameIntern] = 0;
                }
            }

            if ($imfNameIntern === 'ITEMNAME') {
                if ($values[$imfNameIntern] === '') {
                    break;
                }
                $val = $values[$imfNameIntern];
            } elseif ($imfNameIntern === 'KEEPER') {
                if (substr_count($values[$imfNameIntern], ',') === 1) {
                    $sql = getSqlOrganizationsUsersShortPIM();
                } else {
                    $sql = getSqlOrganizationsUsersCompletePIM();
                }

                $result = $gDb->queryPrepared($sql);

                while ($row = $result->fetch()) {
                    if ($row['name'] == $values[$imfNameIntern]) {
                        $val = $row['usr_id'];
                        break;
                    }
                    $val = '-1';
                }
            } elseif ($imfNameIntern === 'LAST_RECEIVER') {
                if (substr_count($values[$imfNameIntern], ',') === 1) {
                    $sql = getSqlOrganizationsUsersShortPIM();
                } else {
                    $sql = getSqlOrganizationsUsersCompletePIM();
                }

                $result = $gDb->queryPrepared($sql);

                while ($row = $result->fetch()) {
                    if ($row['name'] == $values[$imfNameIntern]) {
                        $val = $row['usr_id'];
                        break;
                    }
                    $val = $values[$imfNameIntern];
                }
            } elseif ($imfNameIntern === 'CATEGORY') {
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
                    } else {
                        $itemField = new TableAccess($gDb, TBL_INVENTORY_MANAGER_FIELDS, 'imf');
                        $itemField->readDataById($items->mItemFields[$imfNameIntern]->getValue('imf_id'));
                        $valueList[] = $values[$imfNameIntern];
                        $itemField->setValue('imf_value_list', $string = implode("\n", $valueList));

                        // Save data to the database
                        $returnCode = $itemField->save();

                        if ($returnCode < 0) {
                            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
                            // => EXIT
                        } else {
                            $val = array_search($values[$imfNameIntern], $valueList);
                        }
                    }
                }
            } elseif ($imfNameIntern === 'RECEIVED_ON' || $imfNameIntern === 'RECEIVED_BACK_ON') {
                $val = $values[$imfNameIntern];
                if ($val !== '') {
                    // date must be formatted
                    if ($pPreferences->config['Optionen']['field_date_time_format'] === 'datetime') {
                        //check if date is datetime or only date
                        if (strpos($val, ' ') === false) {
                            $val .= '00:00';
                        }
                        // check if date is wrong formatted
                        $dateObject = DateTime::createFromFormat('d.m.Y H:i', $val);
                        if ($dateObject instanceof DateTime) {
                            // convert date to correct format
                            $val = $dateObject->format('Y-m-d H:i');
                        }
                        // check if date is right formatted
                        $date = DateTime::createFromFormat('Y-m-d H:i', $val);
                        if ($date instanceof DateTime) {
                            $val = $date->format($gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time'));
                        }
                    } else {
                        // check if date is date or datetime
                        if (strpos($val, ' ') !== false) {
                            $val = substr($val, 0, 10);
                        }
                        // check if date is wrong formatted
                        $dateObject = DateTime::createFromFormat('d.m.Y', $val);
                        if ($dateObject instanceof DateTime) {
                            // convert date to correct format
                            $val = $dateObject->format('Y-m-d');
                        }
                        // check if date is right formatted
                        $date = DateTime::createFromFormat('Y-m-d', $val);
                        if ($date instanceof DateTime) {
                            $htmlValue = $date->format($gSettingsManager->getString('system_date'));
                        }
                    }
                }
            } else {
                $val = $values[$imfNameIntern];
            }
        } else {
            $val = '';
        }
        $_POST['imf-' . $fields->getValue('imf_id')] = '' . $val;
        $ItemData[] = array($items->mItemFields[$imfNameIntern]->getValue('imf_name') => array('oldValue' => '', 'newValue' => $val));
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
$items->sendNotification($importedItemData);

$gNavigation->deleteLastUrl();

// Go back to item view
if ($gNavigation->count() > 2) {
    $gNavigation->deleteLastUrl();
}

if ($importSuccess) {
    $gMessage->setForwardUrl($gNavigation->getUrl(), 1000);
    $gMessage->show($gL10n->get('SYS_SAVE_DATA'));
} else {
    $gMessage->setForwardUrl($gNavigation->getUrl());
    $gMessage->show($gL10n->get('PLG_INVENTORY_MANAGER_NO_NEW_IMPORT_DATA'));
}

/**
 * Compares two arrays to determine if they are different based on specific criteria
 *
 * @param array $array1 The first array to compare
 * @param array $array2 The second array to compare
 * @return bool             true if the arrays are different based on the criteria, otherwise false
 */
function compareArrays(array $array1, array $array2): bool
{
    $array1 = array_filter($array1, function ($key) {
        return $key !== 'KEEPER' && $key !== 'LAST_RECEIVER' && $key !== 'IN_INVENTORY' && $key !== 'RECEIVED_ON' && $key !== 'RECEIVED_BACK_ON';
    }, ARRAY_FILTER_USE_KEY);

    foreach ($array1 as $value) {
        if ($value === '') {
            continue;
        }

        if (!in_array($value, $array2, true)) {
            return true;
        }
    }
    return false;
}
