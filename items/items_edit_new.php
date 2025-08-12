<?php

/**
 ***********************************************************************************************
 * Create or edit an item profile
 *
 * @see         https://github.com/MightyMCoder/InventoryManager/ The InventoryManager GitHub project
 * @author      MightyMCoder
 * @copyright   2024 - today MightyMCoder
 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 only
 *
 *
 * Parameters:
 * item_id      : ID of the item who should be edited
 * item_former  : indicates that the item was made to former item
 * copy         : true - The item of the item_id will be copied and the base for this new item
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../../adm_program/system/common.php');
require_once(__DIR__ . '/../classes/items.php');
require_once(__DIR__ . '/../classes/configtable.php');
require_once(__DIR__ . '/../common_function.php');

// Access only with valid login
require_once(__DIR__ . '/../../../adm_program/system/login_valid.php');

// Initialize and check the parameters
$getItemId = admFuncVariableIsValid($_GET, 'item_id', 'int');
$getItemFormer = admFuncVariableIsValid($_GET, 'item_former', 'int');
$getCopy = admFuncVariableIsValid($_GET, 'copy', 'bool');

$pPreferences = new CConfigTablePIM();
$pPreferences->read();

$items = new CItems($gDb, $gCurrentOrgId);
$items->readItemData($getItemId, $gCurrentOrgId);

$authorizedPreferences = false;
$keeperEditFields = array();

if (!isUserAuthorizedForPreferencesPIM()) {
    if (!isKeeperAuthorizedToEdit((int)$items->getValue('KEEPER', 'database'))) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }
    $keeperEditFields = $pPreferences->config['Optionen']['allowed_keeper_edit_fields'];
} else {
    $authorizedPreferences = true;
}

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
        $page->addPageFunctionsMenuItem(
            'menu_item_change_history',
            $gL10n->get('SYS_CHANGE_HISTORY'),
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_IM . '/items/items_history.php', array('item_id' => $getItemId)),
            'fa-history'
        );
    }

    if ($authorizedPreferences) {
        $page->addPageFunctionsMenuItem(
            'menu_copy_item',
            $gL10n->get('PLG_INVENTORY_MANAGER_ITEM_COPY'),
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_IM . '/items/items_edit_new.php', array('item_id' => $getItemId, 'copy' => 1)),
            'fa-clone'
        );
    }
    $page->addPageFunctionsMenuItem(
        'menu_delete_item',
        $gL10n->get('PLG_INVENTORY_MANAGER_ITEM_DELETE'),
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . '/' . PLUGIN_FOLDER_IM . '/items/items_delete.php', array('item_id' => $getItemId, 'item_former' => $getItemFormer)),
        'fa-trash'
    );
}

// Create HTML form
$form = new HtmlForm('edit_item_form', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_IM . '/items/items_save.php', array('item_id' => $getItemId)), $page);

$disableBorrowing = $pPreferences->config['Optionen']['disable_borrowing'];

foreach ($items->mItemFields as $itemField) {
    $imfNameIntern = $itemField->getValue('imf_name_intern');
    if ($imfNameIntern === 'IN_INVENTORY') {
        $pimInInventoryId = $items->getProperty($imfNameIntern, 'imf_id');
    }
    if ($imfNameIntern === 'LAST_RECEIVER' && $disableBorrowing == 0) {
        $pimLastReceiverId = $items->getProperty($imfNameIntern, 'imf_id');
    }
    if ($imfNameIntern === 'RECEIVED_ON' && $disableBorrowing == 0) {
        $pimReceivedOnId = $items->getProperty($imfNameIntern, 'imf_id');
    }
    if ($imfNameIntern === 'RECEIVED_BACK_ON' && $disableBorrowing == 0) {
        $pimReceivedBackOnId = $items->getProperty($imfNameIntern, 'imf_id');
    }
}

foreach ($items->mItemFields as $itemField) {
    $helpId = '';
    $imfNameIntern = $itemField->getValue('imf_name_intern');

    if ($items->getProperty($imfNameIntern, 'imf_mandatory') == 1) {
        $fieldProperty = HtmlForm::FIELD_REQUIRED;
    } else {
        $fieldProperty = HtmlForm::FIELD_DEFAULT;
    }

    if (!$authorizedPreferences && !in_array($itemField->getValue('imf_name_intern'), $keeperEditFields)) {
        $fieldProperty = HtmlForm::FIELD_DISABLED;
    }

    if ($disableBorrowing == 1 && ($imfNameIntern === 'LAST_RECEIVER' || $imfNameIntern === 'RECEIVED_ON' || $imfNameIntern === 'RECEIVED_BACK_ON')) {
        break;
    }

    if (isset($pimInInventoryId, $pimLastReceiverId, $pimReceivedOnId, $pimReceivedBackOnId) && $imfNameIntern === 'IN_INVENTORY') {
        $pPreferences->config['Optionen']['field_date_time_format'] === 'datetime' ? $datetime = 'true' : $datetime = 'false';

        // Add JavaScript to check the LAST_RECEIVER field and set the required attribute for pimReceivedOnId and pimReceivedBackOnId
        $page->addJavascript('
            document.addEventListener("DOMContentLoaded", function() {
                if (document.querySelector("[id=\'imf-' . $pimReceivedOnId . '_time\']")) {
                    var pDateTime = "true";
                } else {
                    var pDateTime = "false";
                }

                var pimInInventoryField = document.querySelector("[id=\'imf-' . $pimInInventoryId . '\']");
                var pimInInventoryGroup = document.getElementById("imf-' . $pimInInventoryId . '_group");
                var pimLastReceiverField = document.querySelector("[id=\'imf-' . $pimLastReceiverId . '\']");
                var pimLastReceiverFieldHidden = document.querySelector("[id=\'imf-' . $pimLastReceiverId . '-hidden\']");
                var pimLastReceiverGroup = document.getElementById("imf-' . $pimLastReceiverId . '_group");
                var pimReceivedOnField = document.querySelector("[id=\'imf-' . $pimReceivedOnId . '\']");

                if (pDateTime === "true") {
                    var pimReceivedOnFieldTime = document.querySelector("[id=\'imf-' . $pimReceivedOnId . '_time\']");
                    var pimReceivedBackOnFieldTime = document.querySelector("[id=\'imf-' . $pimReceivedBackOnId . '_time\']");
                }

                var pimReceivedOnGroup = document.getElementById("imf-' . $pimReceivedOnId . '_group");
                var pimReceivedBackOnField = document.querySelector("[id=\'imf-' . $pimReceivedBackOnId . '\']");
                var pimReceivedBackOnGroup = document.getElementById("imf-' . $pimReceivedBackOnId . '_group");

                function setRequired(field, group, required) {
                    if (required) {
                    field.setAttribute("required", "required");
                    group.classList.add("admidio-form-group-required");
                    } else {
                    field.removeAttribute("required");
                    group.classList.remove("admidio-form-group-required");
                    }
                }

                window.checkPimInInventory = function() {
                    var isInInventoryChecked = pimInInventoryField.checked;
                    var lastReceiverValue = pimLastReceiverFieldHidden.value;
                    var receivedBackOnValue = pimReceivedBackOnField.value;

                    setRequired(pimReceivedOnField, pimReceivedOnGroup, isInInventoryChecked && (lastReceiverValue && lastReceiverValue !== "undefined"));
                    setRequired(pimReceivedBackOnField, pimReceivedBackOnGroup, isInInventoryChecked && (lastReceiverValue && lastReceiverValue !== "undefined"));
                    if (pDateTime === "true") {
                        setRequired(pimReceivedOnFieldTime, pimReceivedOnGroup, isInInventoryChecked && (lastReceiverValue && lastReceiverValue !== "undefined"));
                        setRequired(pimReceivedBackOnFieldTime, pimReceivedBackOnGroup, isInInventoryChecked && (lastReceiverValue && lastReceiverValue !== "undefined"));
                    }

                    setRequired(pimLastReceiverField, pimLastReceiverGroup, !isInInventoryChecked);
                    setRequired(pimReceivedOnField, pimReceivedOnGroup, !isInInventoryChecked);
                    if (pDateTime === "true") {
                        setRequired(pimReceivedOnFieldTime, pimReceivedOnGroup, !isInInventoryChecked);
                    }

                    console.log("Receiver: " + lastReceiverValue);
                    if (!isInInventoryChecked && (lastReceiverValue === "undefined" || !lastReceiverValue)) {
                        pimReceivedOnField.value = "";
                        if (pDateTime === "true") {
                            pimReceivedOnFieldTime.value = "";
                        }
                    }

                    if (receivedBackOnValue !== "") {
                        setRequired(pimLastReceiverField, pimLastReceiverGroup, true);
                        setRequired(pimReceivedOnField, pimReceivedOnGroup, true);
                        if (pDateTime === "true") {
                            setRequired(pimReceivedOnFieldTime, pimReceivedOnGroup, true);
                            setRequired(pimReceivedBackOnFieldTime, pimReceivedBackOnGroup, true);
                        }
                    }

                    var previousPimInInventoryState = isInInventoryChecked;

                    pimInInventoryField.addEventListener("change", function() {
                        if (!pimInInventoryField.checked && previousPimInInventoryState) {
                            pimReceivedBackOnField.value = "";
                            if (pDateTime === "true") {
                                pimReceivedBackOnFieldTime.value = "";
                            }
                        }
                        previousPimInInventoryState = pimInInventoryField.checked;
                        window.checkPimInInventory();
                    });

                    pimLastReceiverFieldHidden.addEventListener("change", window.checkPimInInventory);
                    pimReceivedBackOnField.addEventListener("input", window.checkPimInInventory);
                    pimReceivedOnField.addEventListener("input", validateReceivedOnAndBackOn);
                    if (pDateTime === "true") {
                        pimReceivedOnFieldTime.addEventListener("input", validateReceivedOnAndBackOn);
                        pimReceivedBackOnFieldTime.addEventListener("input", validateReceivedOnAndBackOn);
                    }
                }

                function validateReceivedOnAndBackOn() {
                    if (pDateTime === "true") {
                        var receivedOnDate = new Date(pimReceivedOnField.value + " " + pimReceivedOnFieldTime.value);
                        var receivedBackOnDate = new Date(pimReceivedBackOnField.value + " " + pimReceivedBackOnFieldTime.value);
                    } else {
                        var receivedOnDate = new Date(pimReceivedOnField.value);
                        var receivedBackOnDate = new Date(pimReceivedBackOnField.value);
                    }

                    if (receivedOnDate > receivedBackOnDate) {
                        pimReceivedOnField.setCustomValidity("ReceivedOn date cannot be after ReceivedBack date.");
                    } else {
                        pimReceivedOnField.setCustomValidity("");
                    }
                }

                pimInInventoryField.addEventListener("change", window.checkPimInInventory);
                pimLastReceiverFieldHidden.addEventListener("change", window.checkPimInInventory);

                pimReceivedOnField.addEventListener("input", validateReceivedOnAndBackOn);
                pimReceivedBackOnField.addEventListener("input", window.checkPimInInventory);

                if (pDateTime === "true") {
                    pimReceivedOnFieldTime.addEventListener("input", validateReceivedOnAndBackOn);
                    pimReceivedBackOnFieldTime.addEventListener("input", validateReceivedOnAndBackOn);
                }
                pimReceivedBackOnField.addEventListener("input", validateReceivedOnAndBackOn);
                window.checkPimInInventory();
            });
        ');
    }

    switch ($items->getProperty($imfNameIntern, 'imf_type')) {
        case 'CHECKBOX':
            $form->addCheckbox(
                'imf-' . $items->getProperty($imfNameIntern, 'imf_id'),
                convlanguagePIM($items->getProperty($imfNameIntern, 'imf_name')),
                ($getItemId === 0) ? true : (bool)$items->getValue($imfNameIntern),
                array(
                    'property' => $fieldProperty,
                    'helpTextIdLabel' => $helpId,
                    'icon' => $items->getProperty($imfNameIntern, 'imf_icon', 'database')
                )
            );
            break;

        case 'DROPDOWN':
        case'DATE_INTERVAL':
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
                $sql = getSqlOrganizationsUsersCompletePIM();
                if ($pPreferences->config['Optionen']['current_user_default_keeper'] === 1) {
                    $user = new User($gDb, $gProfileFields);
                    $user->readDataByUuid($gCurrentUser->getValue('usr_uuid'));
                }

                $form->addSelectBoxFromSql(
                    'imf-' . $items->getProperty($imfNameIntern, 'imf_id'),
                    convlanguagePIM($items->getProperty($imfNameIntern, 'imf_name')),
                    $gDb,
                    $sql,
                    array(
                        'property' => $fieldProperty,
                        'helpTextIdLabel' => $helpId,
                        'icon' => $items->getProperty($imfNameIntern, 'imf_icon', 'database'),
                        'defaultValue' => ($pPreferences->config['Optionen']['current_user_default_keeper'] === 1) ? $user->getValue('usr_id') : $items->getValue($imfNameIntern),
                        'multiselect' => false
                    )
                );
            } elseif ($imfNameIntern === 'LAST_RECEIVER') {
                $sql = getSqlOrganizationsUsersCompletePIM();

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

                $form->addInput(
                    'imf-' . $items->getProperty($imfNameIntern, 'imf_id') . '-hidden',
                    '',
                    $items->getValue($imfNameIntern),
                    array(
                        'type' => 'hidden',
                        'property' => HtmlForm::FIELD_DISABLED
                    )
                );

                if ($page instanceof HtmlPage) {
                    $page->addCssFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/select2/css/select2.css');
                    $page->addCssFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/select2-bootstrap-theme/select2-bootstrap4.css');
                    $page->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/select2/js/select2.js');
                    $page->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/select2/js/i18n/' . $gL10n->getLanguageLibs() . '.js');
                }

                $page->addJavascript('
                $(document).ready(function() {
                    var selectId = "#imf-' . $pimLastReceiverId . '";

                    var defaultValue = "' . htmlspecialchars($items->getValue($imfNameIntern)) . '";
                    var defaultText = "' . htmlspecialchars($items->getValue($imfNameIntern)) . '"; // Der Text für den Default-Wert

                    function isSelect2Empty(selectId) {
                        // Hole den aktuellen Wert des Select2-Feldes
                        var lastReceiverValueHidden = document.querySelector("[id=\'imf-' . $pimLastReceiverId . '-hidden\']");
                        var renderedElement = $("#select2-imf-' . $pimLastReceiverId . '-container");
                        if (renderedElement.length) {
                            lastReceiverValueHidden.value = renderedElement.attr("title");
                            console.log("Hidden: " + lastReceiverValueHidden.value);
                            window.checkPimInInventory();
                        }
                    }
                    // Prüfe, ob der Default-Wert in den Optionen enthalten ist
                    if ($(selectId + " option[value=\'" + defaultValue + "\']").length === 0) {
                        // Füge den Default-Wert als neuen Tag hinzu
                        var newOption = new Option(defaultText, defaultValue, true, true);
                        $(selectId).append(newOption).trigger("change");
                    }

                    $("#imf-' . $pimLastReceiverId . '").select2({
                        theme: "bootstrap4",
                        allowClear: true,
                        placeholder: "",
                        language: "' . $gL10n->getLanguageLibs() . '",
                        tags: true
                    });

                    // Überwache Änderungen im Select2-Feld
                    $(selectId).on("change.select2", function() {
                        isSelect2Empty(selectId);
                    });
                });', true);
            } else {
                if ($items->getProperty($imfNameIntern, 'imf_type') === 'DATE') {
                    $fieldType = $pPreferences->config['Optionen']['field_date_time_format'];
                    $maxlength = null;
                } elseif ($items->getProperty($imfNameIntern, 'imf_type') === 'NUMBER') {
                    $fieldType = 'number';
                    $minNumber = $pPreferences->config['Optionen']['allow_negative_numbers'] ? null : '0';
                    $step = '1';
                } elseif ($items->getProperty($imfNameIntern, 'imf_type') === 'DECIMAL') {
                    $fieldType = 'number';
                    $minNumber = $pPreferences->config['Optionen']['allow_negative_numbers'] ? null : '0';
                    $step = $pPreferences->config['Optionen']['decimal_step'];
                }
                $form->addInput(
                    'imf-' . $items->getProperty($imfNameIntern, 'imf_id'),
                    convlanguagePIM($items->getProperty($imfNameIntern, 'imf_name')),
                    $items->getValue($imfNameIntern),
                    array(
                        'type' => $fieldType,
                        'maxLength' => $maxlength ?? null,
                        'minNumber' => $minNumber ?? null,
                        'step' => $step ?? null,
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

$infoItem = new TableAccess($gDb, TBL_INVENTORY_MANAGER_ITEMS, 'imi', (int)$getItemId);

// Show information about item who creates the recordset and changed it
$form->addHtml(admFuncShowCreateChangeInfoById(
    (int)$infoItem->getValue('imi_usr_id_create'),
    $infoItem->getValue('imi_timestamp_create'),
    (int)$infoItem->getValue('imi_usr_id_change'),
    $infoItem->getValue('imi_timestamp_change')
));

$page->addHtml($form->show());
$page->show();
