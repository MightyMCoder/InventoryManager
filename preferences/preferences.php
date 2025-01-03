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
 * methods:
 * 
 * addPreferencePanel($page, $id, $title, $icon, $content) : Add a new preference panel to the page
 * 
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../../adm_program/system/common.php');
require_once(__DIR__ . '/../common_function.php');
require_once(__DIR__ . '/../classes/items.php');
require_once(__DIR__ . '/../classes/configtable.php');

// Access only with valid login
require_once(__DIR__ . '/../../../adm_program/system/login_valid.php');

/**
 * Adds a new preference panel to the given page.
 *
 * @param object $page    The page object where the preference panel will be added.
 * @param string $id      The unique identifier for the preference panel.
 * @param string $title   The title of the preference panel.
 * @param string $icon    The icon associated with the preference panel.
 * @param string $content The HTML content of the preference panel.
 * @return void
 */
// Function to add new preference panels
function addPreferencePanel($page, $id, $title, $icon, $content) {
    $page->addHtml(getPreferencePanel('preferences', $id, $title, $icon, $content));
}

$pPreferences = new CConfigTablePIM();
$pPreferences->read();

// only authorized user are allowed to start this module
if (!isUserAuthorizedForPreferences()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// read formfiller configuration if plugin formfiller is installed
if ($pPreferences->isPffInst()) {
    $pPreferences->readPff();
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
        $.get("'.ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER. '/preferences/preferences_check_for_update.php", {mode: "2", PIMVersion: "' .$pPreferences->config['Plugininformationen']['version']. '", PIMBetaVersion: "' .$pPreferences->config['Plugininformationen']['beta-version']. '"}, function(htmlVersion) {
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
        <div class="accordion" id="accordion_preferences">');

// PANEL: ITEMFIELDS
$formItemFields = new HtmlForm('itemfields_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/fields.php'), $page);
$formItemFields->addSubmitButton('btn_save_itemfields', $gL10n->get('PLG_INVENTORY_MANAGER_ITEMFIELDSMANAGE'), array('icon' => 'fa-edit', 'class' => 'offset-sm-3'));
$formItemFields->addCustomContent('', $gL10n->get('PLG_INVENTORY_MANAGER_ITEMFIELDSMANAGE_DESC'));
addPreferencePanel($page, 'itemfields', $gL10n->get('PLG_INVENTORY_MANAGER_ITEMFIELDSMANAGE'), 'fas fa-edit', $formItemFields->show());

// PANEL: INTERFACE_PFF
if ($pPreferences->isPffInst()) {
    $formInterfacePFF = new HtmlForm('interface_pff_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/preferences/preferences_function.php', array('form' => 'interface_pff')), $page, array('class' => 'form-preferences'));
    $formInterfacePFF->addSelectBox('interface_pff', $gL10n->get('PLG_INVENTORY_MANAGER_CONFIGURATION'), $pPreferences->configPff['Formular']['desc'], array('defaultValue' => $pPreferences->config['Optionen']['interface_pff'], 'showContextDependentFirstEntry' => false));
    $formInterfacePFF->addCustomContent('', $gL10n->get('PLG_INVENTORY_MANAGER_INTERFACE_PFF_DESC'));
    $formInterfacePFF->addSubmitButton('btn_save_interface_pff', $gL10n->get('SYS_SAVE'), array('icon' => 'fa-check', 'class' => ' offset-sm-3'));
    addPreferencePanel($page, 'interface_pff', $gL10n->get('PLG_INVENTORY_MANAGER_INTERFACE_PFF'), 'fas fa-file-pdf', $formInterfacePFF->show());
}

// PANEL: PROFILE ADDIN
$formProfileAddin = new HtmlForm('profile_addin_form', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/preferences/preferences_function.php', array('form' => 'profile_addin')), $page, array('class' => 'form-preferences'));
$items = new CItems($gDb, $gCurrentOrgId);
$valueList = array();
foreach ($items->mItemFields as $itemField) {
    if (!in_array($itemField->getValue('imf_name_intern'), array('ITEMNAME', 'KEEPER', 'RECEIVED_ON'), true)) {
        $valueList[$itemField->getValue('imf_name_intern')] = $itemField->getValue('imf_name');
    }
}
$formProfileAddin->addSelectBox('profile_addin', $gL10n->get('PLG_INVENTORY_MANAGER_ITEMFIELD'), $valueList, array('defaultValue' => $pPreferences->config['Optionen']['profile_addin'], 'showContextDependentFirstEntry' => true, 'helpTextIdInline' => 'PLG_INVENTORY_MANAGER_PROFILE_ADDIN_DESC', 'helpTextIdLabel' => 'PLG_INVENTORY_MANAGER_PROFILE_ADDIN_DESC2'));
$formProfileAddin->addSubmitButton('btn_save_configurations', $gL10n->get('SYS_SAVE'), array('icon' => 'fa-check', 'class' => ' offset-sm-3'));
addPreferencePanel($page, 'profile_addin', $gL10n->get('PLG_INVENTORY_MANAGER_PROFILE_ADDIN'), 'fas fa-users-cog', $formProfileAddin->show());

// PANEL: EXPORT
$formExport = new HtmlForm('export_form', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/preferences/preferences_function.php', array('form' => 'export')), $page, array('class' => 'form-preferences'));
$formExport->addInput('file_name', $gL10n->get('PLG_INVENTORY_MANAGER_FILE_NAME'), $pPreferences->config['Optionen']['file_name'], array('helpTextIdLabel' => 'PLG_INVENTORY_MANAGER_FILE_NAME_DESC', 'property' => HtmlForm::FIELD_REQUIRED));
$formExport->addCheckbox('add_date', $gL10n->get('PLG_INVENTORY_MANAGER_ADD_DATE'), $pPreferences->config['Optionen']['add_date'], array('helpTextIdInline' => 'PLG_INVENTORY_MANAGER_ADD_DATE_DESC'));
$formExport->addSubmitButton('btn_save_configurations', $gL10n->get('SYS_SAVE'), array('icon' => 'fa-check', 'class' => ' offset-sm-3'));
addPreferencePanel($page, 'export', $gL10n->get('PLG_INVENTORY_MANAGER_EXPORT'), 'fas fa-file-export', $formExport->show());

// PANEL: IMPORT
$formImport = new HtmlForm('import_form',null, $page, array('class' => 'form-preferences'));
$formImport->addButton('btn_save_configurations', $gL10n->get('SYS_IMPORT'),array('icon' => 'fa-arrow-circle-right', 'class' => 'offset-sm-3', 'link' => '' . ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER . '/import/import.php'));
addPreferencePanel($page, 'import', $gL10n->get('PLG_INVENTORY_MANAGER_IMPORT'), 'fas fa-file-import', $formImport->show());

// PANEL: DEINSTALLATION
$formDeinstallation = new HtmlForm('deinstallation_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/preferences/preferences_function.php', array('mode' => 2)), $page);
$formDeinstallation->addSubmitButton('btn_save_deinstallation', $gL10n->get('PLG_INVENTORY_MANAGER_DEINSTALLATION'), array('icon' => 'fa-trash-alt', 'class' => 'offset-sm-3'));
$formDeinstallation->addCustomContent('', ''.$gL10n->get('PLG_INVENTORY_MANAGER_DEINSTALLATION_DESC'));
addPreferencePanel($page, 'deinstallation', $gL10n->get('PLG_INVENTORY_MANAGER_DEINSTALLATION'), 'fas fa-trash-alt', $formDeinstallation->show());

// PANEL: ACCESS_PREFERENCES
$formAccessPreferences = new HtmlForm('access_preferences_form', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/preferences/preferences_function.php', array('form' => 'access_preferences')), $page, array('class' => 'form-preferences'));
$sql = 'SELECT rol.rol_id, rol.rol_name, cat.cat_name FROM '.TBL_CATEGORIES.' AS cat, '.TBL_ROLES.' AS rol
        WHERE cat.cat_id = rol.rol_cat_id
        AND (cat.cat_org_id = '.$gCurrentOrgId.'
            OR cat.cat_org_id IS NULL)
        ORDER BY cat_sequence, rol.rol_name ASC;';
$formAccessPreferences->addSelectBoxFromSql('access_preferences', '', $gDb, $sql, array('defaultValue' => $pPreferences->config['access']['preferences'], 'helpTextIdInline' => 'PLG_INVENTORY_MANAGER_ACCESS_PREFERENCES_DESC', 'multiselect' => true));
$formAccessPreferences->addSubmitButton('btn_save_configurations', $gL10n->get('SYS_SAVE'), array('icon' => 'fa-check', 'class' => ' offset-sm-3'));
addPreferencePanel($page, 'access_preferences', $gL10n->get('PLG_INVENTORY_MANAGER_ACCESS_PREFERENCES'), 'fas fa-key', $formAccessPreferences->show());

// PANEL: PLUGIN INFORMATIONS
$pluginName = $gL10n->get('PLG_INVENTORY_MANAGER_NAME_OF_PLUGIN');
$link = '<a href="https://github.com/rmbinder/KeyManager">GitHub</a>';
$pluginInfo = sprintf($pluginName, $link);
$updateCheck = '<span id="inventory_manager_version">'.$pPreferences->config['Plugininformationen']['version'].'
                    <a id="link_check_for_update" href="#link_check_for_update" title="'.$gL10n->get('SYS_CHECK_FOR_UPDATE').'">'.$gL10n->get('SYS_CHECK_FOR_UPDATE').'</a>
                </span>';

$formPluginInformations = new HtmlForm('plugin_informations_preferences_form', null, $page, array('class' => 'form-preferences'));
$formPluginInformations->addStaticControl('plg_name', $gL10n->get('PLG_INVENTORY_MANAGER_PLUGIN_NAME'), $pluginInfo);
$formPluginInformations->addCustomContent($gL10n->get('PLG_INVENTORY_MANAGER_PLUGIN_VERSION'), $updateCheck);
$formPluginInformations->addStaticControl('plg_date', $gL10n->get('PLG_INVENTORY_MANAGER_PLUGIN_DATE'), $pPreferences->config['Plugininformationen']['stand']);
addPreferencePanel($page, 'plugin_informations', $gL10n->get('PLG_INVENTORY_MANAGER_PLUGIN_INFORMATION'), 'fas fa-info-circle', $formPluginInformations->show());

$page->addHtml('
        </div>
    </div>
</div>');

$page->show();
