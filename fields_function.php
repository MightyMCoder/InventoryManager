<?php
/**
 ***********************************************************************************************
 * Script to manage item fields in the InventoryManager plugin
 *
 * @see         https://github.com/MightyMCoder/InventoryManager/ The InventoryManager GitHub project
 * @author      MightyMCoder
 * @copyright   2024 - today MightyMCoder
 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 only
 * 
 * Parameters:
 * 
 * imf_id               : ID of the item field to be managed
 * mode                 : 1 - create or edit item field
 *                        2 - delete item field
 *                        4 - change sequence of item field
 * sequence             : direction to move the item field, values are TableUserField::MOVE_UP, TableUserField::MOVE_DOWN
 * redirect_to_import   : If true, the user will be redirected to the import page after saving the field
 * 
 * Methods:
 * 
 * handleCreateOrUpdate($itemField, $getRedirectToImport)               : Handles the creation or update of an item field
 * handleDelete($itemField)                                             : Handles the deletion of an item field
 * handleChangeSequence($itemField, $getSequence)                       : Handles changing the sequence of an item field
 * validateRequiredFields($itemField)                                   : Validates the required fields for an item field
 * checkFieldExists($itemField)                                         : Checks if an item field already exists
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/common_function.php');
require_once(__DIR__ . '/classes/configtable.php');

// Access only with valid login
require_once(__DIR__ . '/../../adm_program/system/login_valid.php');

// Initialize and check the parameters
$getimfId    = admFuncVariableIsValid($_GET, 'imf_id',   'int');
$getMode     = admFuncVariableIsValid($_GET, 'mode',     'int',    array('requireValue' => true));
$getSequence = admFuncVariableIsValid($_GET, 'sequence', 'string', array('validValues' => array(TableUserField::MOVE_UP, TableUserField::MOVE_DOWN)));
$getRedirectToImport = admFuncVariableIsValid($_GET, 'redirect_to_import', 'bool', array('defaultValue' => false));

$pPreferences = new CConfigTablePIM();
$pPreferences->read();

// only authorized user are allowed to start this module
if (!isUserAuthorizedForPreferences()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

$itemField = new TableAccess($gDb, TBL_INVENTORY_MANAGER_FIELDS, 'imf');

if ($getimfId > 0) {
    $itemField->readDataById($getimfId);

    // check if item field belongs to actual organization
    if ($itemField->getValue('imf_org_id') > 0 && (int) $itemField->getValue('imf_org_id') !== (int) $gCurrentOrgId) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
}

switch ($getMode) {
    case 1:
        handleCreateOrUpdate($itemField, $getRedirectToImport);
        break;
    case 2:
        handleDelete($itemField);
        break;
    case 4:
        handleChangeSequence($itemField, $getSequence);
        break;
}

/**
 * Handles the creation or update of an item field.
 * @param TableAccess $itemField        The item field object to be created or updated.
 */
function handleCreateOrUpdate($itemField, $redirectToImport = false) {
    global $gMessage, $gL10n, $gDb, $gCurrentOrgId;

    $_SESSION['fields_request'] = $_POST;

    // Validate required fields
    validateRequiredFields($itemField);

    // Check if the field already exists
    if (isset($_POST['imf_name']) && $itemField->getValue('imf_name') !== $_POST['imf_name']) {
        checkFieldExists($_POST['imf_name']);
    }

    // Make HTML in description secure
    $_POST['imf_description'] = admFuncVariableIsValid($_POST, 'imf_description', 'html');

    if (!isset($_POST['imf_mandatory']) && $itemField->getValue('imf_system') == 0) {
        $_POST['imf_mandatory'] = 0;
    }

    try {
        // Write POST variables to the UserField object
        foreach ($_POST as $item => $value) {
            if (StringUtils::strStartsWith($item, 'imf_')) {
                $itemField->setValue($item, $value);
            }
        }
    } catch (AdmException $e) {
        $e->showHtml();
    }

    $itemField->setValue('imf_org_id', (int) $gCurrentOrgId);

    if ($itemField->isNewRecord()) {
        $itemField->setValue('imf_name_intern', getNewNameIntern($itemField->getValue('imf_name', 'database'), 1));
        $itemField->setValue('imf_sequence', genNewSequence());
    }

    // Save data to the database
    $returnCode = $itemField->save();

    if ($returnCode < 0) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }

    unset($_SESSION['fields_request']);

    if ($redirectToImport) {
        $gMessage->setForwardUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER . '/import/import_column_config.php', 1000);
    } else {
        $gMessage->setForwardUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER . '/fields.php', 1000);
    }
    $gMessage->show($gL10n->get('SYS_SAVE_DATA'));
    // => EXIT
}

/**
 * Handles the deletion of an item field.
 * @param TableAccess $itemField        The item field object to be deleted.
 */
function handleDelete($itemField) {
    if ($itemField->delete()) {
        // Deletion successful -> Return for XMLHttpRequest
        echo 'done';
    }
    exit();
}

/**
 * Handles changing the sequence of an item field.
 * @param TableAccess $itemField        The item field object whose sequence is to be changed.
 * @param string $getSequence           The direction to move the item field, values are TableUserField::MOVE_UP, TableUserField::MOVE_DOWN.
 */
function handleChangeSequence($itemField, $getSequence) {
    global $gDb, $gCurrentOrgId;

    $imfSequence = (int) $itemField->getValue('imf_sequence');

    $sql = 'UPDATE ' . TBL_INVENTORY_MANAGER_FIELDS . '
            SET imf_sequence = ? -- $imf_sequence
            WHERE imf_sequence = ? -- $imf_sequence -/+ 1
            AND (imf_org_id = ? -- $gCurrentOrgId
                OR imf_org_id IS NULL);';

    // Field will get one number lower and therefore move a position up in the list
    if ($getSequence === TableUserField::MOVE_UP) {
        $newSequence = $imfSequence - 1;
    }
    // Field will get one number higher and therefore move a position down in the list
    elseif ($getSequence === TableUserField::MOVE_DOWN) {
        $newSequence = $imfSequence + 1;
    }

    // Update the existing entry with the sequence of the field that should get the new sequence
    $gDb->queryPrepared($sql, array($imfSequence, $newSequence, $gCurrentOrgId));

    $itemField->setValue('imf_sequence', $newSequence);
    $itemField->save();

    exit();
}

/**
 * Validates the required fields for an item field.
 * @param TableAccess $itemField        The item field object to be validated.
 */
function validateRequiredFields($itemField) {
    global $gMessage, $gL10n;

    if ($itemField->getValue('imf_system') == 0) {
        if ($_POST['imf_name'] === '') {
            $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_NAME'))));
            // => EXIT
        }

        if ($_POST['imf_type'] === '') {
            $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('ORG_DATATYPE'))));
            // => EXIT
        }

        if (($_POST['imf_type'] === 'DROPDOWN' || $_POST['imf_type'] === 'RADIO_BUTTON') && $_POST['imf_value_list'] === '') {
            $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('ORG_VALUE_LIST'))));
            // => EXIT
        }
    }
}

/**
 * Checks if an item field already exists.
 * @param string $itemField        The item field string to be checked.
 */
function checkFieldExists($itemField) {
    global $gMessage, $gL10n, $gDb, $gCurrentOrgId, $getimfId;

    $sql = 'SELECT COUNT(*) AS count FROM ' . TBL_INVENTORY_MANAGER_FIELDS . '
            WHERE imf_name = ? -- $_POST[\'imf_name\']
            AND (imf_org_id = ? -- $gCurrentOrgId
                OR imf_org_id IS NULL)
            AND imf_id <> ? -- $getimfId;';
    $statement = $gDb->queryPrepared($sql, array($itemField, $gCurrentOrgId, $getimfId));

    if ((int) $statement->fetchColumn() > 0) {
        $gMessage->show($gL10n->get('ORG_FIELD_EXIST'));
        // => EXIT
    }
}
