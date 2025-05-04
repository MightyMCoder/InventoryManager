<?php
/**
 ***********************************************************************************************
 * Menu preferences for the Admidio plugin InventoryManager
 *
 * @see         https://github.com/MightyMCoder/InventoryManager/ The InventoryManager GitHub project
 * @author      MightyMCoder
 * @copyright   2024 - today MightyMCoder
 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 only
 * 
 * 
 * Methods:
 * addPreferencePanel($page, $id, $title, $icon, $content) : Add a new preference panel to the page
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../../adm_program/system/common.php');
require_once(__DIR__ . '/../common_function.php');
require_once(__DIR__ . '/../classes/items.php');
require_once(__DIR__ . '/../classes/configtable.php');

// Access only with valid login
require_once(__DIR__ . '/../../../adm_program/system/login_valid.php');

$pPreferences = new CConfigTablePIM();
$pPreferences->read();

// only authorized user are allowed to start this module
if (!isUserAuthorizedForPreferencesPIM()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$headline = $gL10n->get('SYS_SETTINGS');
$gNavigation->addUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage('plg-inventory-manager-preferences', $headline);

$page->addJavascript('
    $("#tabs_nav_preferences").attr("class", "active");
    $("#tabs-preferences").attr("class", "tab-pane active");', true
);

$page->addJavascript('
    $(".form-preferences").submit(function(event) {
        var id = $(this).attr("id");
        var action = $(this).attr("action");
        var formAlert = $("#" + id + " .form-alert");
        formAlert.hide();

        // disable default form submit
        event.preventDefault();

        $.post({
            url: action,
            data: $(this).serialize(),
            success: function(data) {
                if (data === "success") {
                    formAlert.attr("class", "alert alert-success form-alert");
                    formAlert.html("<i class=\"fas fa-check\"></i><strong>'.$gL10n->get('SYS_SAVE_DATA').'</strong>");
                    formAlert.fadeIn("slow");
                    formAlert.animate({opacity: 1.0}, 2500);
                    formAlert.fadeOut("slow");
                } else {
                    formAlert.attr("class", "alert alert-danger form-alert");
                    formAlert.fadeIn();
                    formAlert.html("<i class=\"fas fa-exclamation-circle\"></i>" + data);
                }
            }
        });
    });
    
    $("#link_check_for_update").click(function() {
        var PIMVersionContent = $("#inventory_manager_version");

        PIMVersionContent.html("<i class=\"fas fa-spinner fa-spin\"></i>").show();
        $.get("'.ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_IM. '/preferences/preferences_check_for_update.php", {mode: "2", PIMVersion: "' .$pPreferences->config['Plugininformationen']['version']. '", PIMBetaVersion: "' .$pPreferences->config['Plugininformationen']['beta-version']. '"}, function(htmlVersion) {
            PIMVersionContent.html(htmlVersion);
        });
        return false;
    });', true
);

$page->addHtml('
    <ul id="preferences_tabs" class="nav nav-tabs" role="tablist">
        <li class="nav-item">
            <a id="tabs_nav_preferences" class="nav-link" href="#tabs-preferences" data-toggle="tab" role="tab"></a>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade" id="tabs-preferences" role="tabpanel">
            <div class="accordion" id="accordion_preferences">'
);

$items = new CItems($gDb, $gCurrentOrgId);
$valueList = array();
foreach ($items->mItemFields as $itemField) {

    $imfNameIntern = $itemField->getValue('imf_name_intern');
    $hideborrowing = $pPreferences->config['Optionen']['hide_borrowing'];
    if ($hideborrowing == 1 && ($imfNameIntern === 'LAST_RECEIVER' || $imfNameIntern === 'RECEIVED_ON' || $imfNameIntern === 'RECEIVED_BACK_ON')) { 
		break;
	}
    
    $valueList[$imfNameIntern] = $itemField->getValue('imf_name');
}

// PANEL: GENERAL PREFERENCES
$formGeneralSettings = new HtmlForm('general_preferences_form', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_IM .'/preferences/preferences_function.php', array('form' => 'general_preferences')), $page, array('class' => 'form-preferences'));
$formGeneralSettings->addCheckbox('allow_keeper_edit', $gL10n->get('PLG_INVENTORY_MANAGER_ACCESS_EDIT'), $pPreferences->config['Optionen']['allow_keeper_edit'], array('helpTextIdInline' => 'PLG_INVENTORY_MANAGER_ACCESS_EDIT_DESC'));
$formGeneralSettings->addSelectBox('allowed_keeper_edit_fields', $gL10n->get('PLG_INVENTORY_MANAGER_ACCESS_EDIT_FIELDS'), $valueList, array('defaultValue' => $pPreferences->config['Optionen']['allowed_keeper_edit_fields'], 'helpTextIdInline' => 'PLG_INVENTORY_MANAGER_ACCESS_EDIT_FIELDS_DESC', 'multiselect' => true));
$formGeneralSettings->addCheckbox('current_user_default_keeper', $gL10n->get('PLG_INVENTORY_MANAGER_USE_CURRENT_USER'), $pPreferences->config['Optionen']['current_user_default_keeper'], array('helpTextIdInline' => 'PLG_INVENTORY_MANAGER_USE_CURRENT_USER_DESC'));
$formGeneralSettings->addCheckbox('allow_negative_numbers', $gL10n->get('PLG_INVENTORY_MANAGER_ALLOW_NEGATIVE_NUMBERS'), $pPreferences->config['Optionen']['allow_negative_numbers'], array('helpTextIdInline' => 'PLG_INVENTORY_MANAGER_ALLOW_NEGATIVE_NUMBERS_DESC'));
$formGeneralSettings->addInput('decimal_step', $gL10n->get('PLG_INVENTORY_MANAGER_DECIMAL_STEP'), $pPreferences->config['Optionen']['decimal_step'], array('type' => 'number','minNumber' => 0, 'step' => '0.0000001', 'helpTextIdLabel' => 'PLG_INVENTORY_MANAGER_DECIMAL_STEP_DESC', 'property' => HtmlForm::FIELD_REQUIRED));

if ($hideborrowing == 0) { 
		$formGeneralSettings->addSelectBox('field_date_time_format', $gL10n->get('PLG_INVENTORY_MANAGER_DATETIME_FORMAT'), array($gL10n->get('SYS_DATE'), $gL10n->get('SYS_DATE') .' & ' .$gL10n->get('SYS_TIME')), array('defaultValue' => (($pPreferences->config['Optionen']['field_date_time_format'] === 'datetime') ? 1 : 0), 'showContextDependentFirstEntry' => false));
}

$formGeneralSettings->addCheckbox('hide_borrowing', $gL10n->get('PLG_INVENTORY_MANAGER_HIDE_BORROWING'), $pPreferences->config['Optionen']['hide_borrowing'], array('helpTextIdInline' => 'PLG_INVENTORY_MANAGER_HIDE_BORROWING_DESC'));

$formGeneralSettings->addSubmitButton('btn_save_general_preferences', $gL10n->get('SYS_SAVE'), array('icon' => 'fa-check', 'class' => ' offset-sm-3'));
addPreferencePanel($page, 'field_settings', $gL10n->get('SYS_COMMON'), 'fas fa-cog fa-fw', $formGeneralSettings->show());

// PANEL: ITEMFIELDS
$formItemFields = new HtmlForm('itemfields_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER_IM .'/fields/fields.php'), $page);
$formItemFields->addSubmitButton('btn_edit_itemfields', $gL10n->get('PLG_INVENTORY_MANAGER_ITEMFIELDSMANAGE'), array('icon' => 'fa-edit', 'class' => 'offset-sm-3'));
$formItemFields->addCustomContent('', $gL10n->get('PLG_INVENTORY_MANAGER_ITEMFIELDSMANAGE_DESC'));
addPreferencePanel($page, 'itemfields', $gL10n->get('PLG_INVENTORY_MANAGER_ITEMFIELDSMANAGE'), 'fas fa-edit', $formItemFields->show());

// PANEL: PROFILE ADDIN
$helpTextIdLabelLink = '<a href="https://github.com/MightyMCoder/InventoryManager/wiki/Profile-View-AddIn" target="_blank">GitHub Wiki</a>';
$valueList = array();
foreach ($items->mItemFields as $itemField) {

    $imfNameIntern = $itemField->getValue('imf_name_intern');
    $hideborrowing = $pPreferences->config['Optionen']['hide_borrowing'];
    if ($hideborrowing == 1 && ($imfNameIntern === 'LAST_RECEIVER' || $imfNameIntern === 'RECEIVED_ON' || $imfNameIntern === 'RECEIVED_BACK_ON')) { 
		break;
	}

    if ($itemField->getValue('imf_name_intern') == 'ITEMNAME') {
        continue;
    }
    $valueList[$itemField->getValue('imf_name_intern')] = $itemField->getValue('imf_name');
}

$formProfileAddin = new HtmlForm('profile_addin_form', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_IM .'/preferences/preferences_function.php', array('form' => 'profile_addin_preferences')), $page, array('class' => 'form-preferences'));
$helpTextIdLabel = $gL10n->get('PLG_INVENTORY_MANAGER_PROFILE_ADDIN_DESC2', array($helpTextIdLabelLink));
$formProfileAddin->addSelectBox('profile_addin', $gL10n->get('PLG_INVENTORY_MANAGER_ITEMFIELD'), $valueList, array('defaultValue' => $pPreferences->config['Optionen']['profile_addin'], 'helpTextIdInline' => 'PLG_INVENTORY_MANAGER_PROFILE_ADDIN_DESC', 'multiselect' => true, 'helpTextIdLabel' => $helpTextIdLabel));
$formProfileAddin->addSubmitButton('btn_save_profile_addin_preferences', $gL10n->get('SYS_SAVE'), array('icon' => 'fa-check', 'class' => ' offset-sm-3'));
addPreferencePanel($page, 'profile_addin', $gL10n->get('PLG_INVENTORY_MANAGER_PROFILE_ADDIN'), 'fas fa-users-cog', $formProfileAddin->show());

// PANEL: EXPORT
$formExport = new HtmlForm('export_form', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_IM .'/preferences/preferences_function.php', array('form' => 'export_preferences')), $page, array('class' => 'form-preferences'));
$formExport->addInput('file_name', $gL10n->get('PLG_INVENTORY_MANAGER_FILE_NAME'), $pPreferences->config['Optionen']['file_name'], array('helpTextIdLabel' => 'PLG_INVENTORY_MANAGER_FILE_NAME_DESC', 'property' => HtmlForm::FIELD_REQUIRED));
$formExport->addCheckbox('add_date', $gL10n->get('PLG_INVENTORY_MANAGER_ADD_DATE'), $pPreferences->config['Optionen']['add_date'], array('helpTextIdInline' => 'PLG_INVENTORY_MANAGER_ADD_DATE_DESC'));
$formExport->addSubmitButton('btn_save_export_preferences', $gL10n->get('SYS_SAVE'), array('icon' => 'fa-check', 'class' => ' offset-sm-3'));
addPreferencePanel($page, 'export', $gL10n->get('PLG_INVENTORY_MANAGER_EXPORT'), 'fas fa-file-export', $formExport->show());

// PANEL: IMPORT
$formImport = new HtmlForm('import_form',SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_IM .'/import/import.php'), $page);
$formImport->addSubmitButton('btn_import_data', $gL10n->get('SYS_IMPORT'),array('icon' => 'fa-arrow-circle-right', 'class' => 'offset-sm-3'));
$formImport->addCustomContent('', ''.$gL10n->get('PLG_INVENTORY_MANAGER_IMPORT_DESC'));
addPreferencePanel($page, 'import', $gL10n->get('PLG_INVENTORY_MANAGER_IMPORT'), 'fas fa-file-import', $formImport->show());

// PANEL: DEINSTALLATION
$formDeinstallation = new HtmlForm('deinstallation_form', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_IM .'/preferences/preferences_function.php', array('mode' => 2)), $page);
$formDeinstallation->addSubmitButton('btn_perform_deinstallation', $gL10n->get('PLG_INVENTORY_MANAGER_DEINSTALLATION'), array('icon' => 'fa-trash-alt', 'class' => 'offset-sm-3'));
$formDeinstallation->addCustomContent('', ''.$gL10n->get('PLG_INVENTORY_MANAGER_DEINSTALLATION_DESC'));
addPreferencePanel($page, 'deinstallation', $gL10n->get('PLG_INVENTORY_MANAGER_DEINSTALLATION'), 'fas fa-trash-alt', $formDeinstallation->show());

// PANEL: ACCESS_PREFERENCES
$sql = 'SELECT rol.rol_id, rol.rol_name, cat.cat_name FROM '.TBL_CATEGORIES.' AS cat, '.TBL_ROLES.' AS rol
    WHERE cat.cat_id = rol.rol_cat_id
    AND rol.rol_id != 1 ' . //ignore admin role
    'AND (cat.cat_org_id = '.$gCurrentOrgId.'
        OR cat.cat_org_id IS NULL)
    ORDER BY cat_sequence, rol.rol_name ASC;';

$formAccessPreferences = new HtmlForm('access_preferences_form', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_IM .'/preferences/preferences_function.php', array('form' => 'access_preferences')), $page, array('class' => 'form-preferences'));
$formAccessPreferences->addSelectBoxFromSql('access_preferences', '', $gDb, $sql, array('defaultValue' => $pPreferences->config['access']['preferences'], 'helpTextIdInline' => 'PLG_INVENTORY_MANAGER_ACCESS_PREFERENCES_DESC', 'multiselect' => true));
$formAccessPreferences->addSubmitButton('btn_save_access_preferences', $gL10n->get('SYS_SAVE'), array('icon' => 'fa-check', 'class' => ' offset-sm-3'));
addPreferencePanel($page, 'access_preferences', $gL10n->get('PLG_INVENTORY_MANAGER_ACCESS_PREFERENCES'), 'fas fa-key', $formAccessPreferences->show());

// PANEL: PLUGIN INFORMATIONS
$pluginName = $gL10n->get('PLG_INVENTORY_MANAGER_NAME_OF_PLUGIN');
$linkInventoryManager = '<a href="https://github.com/MightyMCoder/InventoryManager" target="_blank">%s</a>';
$linkKeyManager = '<a href="https://github.com/rmbinder/KeyManager" target="_blank">KeyManager (GitHub)</a>';
$pluginInfo = sprintf($linkInventoryManager, $pluginName);
$pluginBasedInfo = $pluginInfo .' ' . $gL10n->get('PLG_INVENTORY_MANAGER_BASED_ON', array($linkKeyManager));
$updateCheck = '
    <span id="inventory_manager_version">'.$pPreferences->config['Plugininformationen']['version'].'
        <a id="link_check_for_update" href="#link_check_for_update" title="'.$gL10n->get('SYS_CHECK_FOR_UPDATE').'">'.$gL10n->get('SYS_CHECK_FOR_UPDATE').'</a>
    </span>';
$dokumentationLink = '<a href="https://github.com/MightyMCoder/InventoryManager/wiki" target="_blank">'.$gL10n->get('PLG_INVENTORY_MANAGER_DOCUMENTATION_OPEN').'</a>';

$formPluginInformations = new HtmlForm('plugin_informations_preferences_form', null, $page, array('class' => 'form-preferences'));
$formPluginInformations->addStaticControl('plg_name', $gL10n->get('PLG_INVENTORY_MANAGER_PLUGIN_NAME'), $pluginBasedInfo);
$formPluginInformations->addStaticControl('plg_dokumentation', $gL10n->get('PLG_INVENTORY_MANAGER_DOCUMENTATION'), $dokumentationLink, array('helpTextIdLabel' => 'PLG_INVENTORY_MANAGER_DOCUMENTATION_OPEN_DESC'));
$formPluginInformations->addCustomContent($gL10n->get('PLG_INVENTORY_MANAGER_PLUGIN_VERSION'), $updateCheck);
$formPluginInformations->addStaticControl('plg_date', $gL10n->get('PLG_INVENTORY_MANAGER_PLUGIN_DATE'), $pPreferences->config['Plugininformationen']['stand']);
addPreferencePanel($page, 'plugin_informations', $gL10n->get('PLG_INVENTORY_MANAGER_PLUGIN_INFORMATION'), 'fas fa-info-circle', $formPluginInformations->show());

$page->addHtml('
            </div>
        </div>
    </div>'
);

$page->show();

/**
 * Adds a new preference panel to the given page
 *
 * @param object $page              The page object where the preference panel will be added
 * @param string $id                The unique identifier for the preference panel
 * @param string $title             The title of the preference panel
 * @param string $icon              The icon associated with the preference panel
 * @param string $content           The HTML content of the preference panel
 * @return void
 */
function addPreferencePanel($page, $id, $title, $icon, $content) : void
{
    $page->addHtml(getPreferencePanelPIM('preferences', $id, $title, $icon, $content));
}