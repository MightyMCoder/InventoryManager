<?php
/**
 ***********************************************************************************************
 * Import assistant for user data
 *
 * @see         https://github.com/MightyMCoder/InventoryManager/ The InventoryManager GitHub project
 * @author      MightyMCoder
 * @copyright   2024 - today MightyMCoder
 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../../adm_program/system/common.php');
require_once(__DIR__ . '/../common_function.php');

// Access only with valid login
require_once(__DIR__ . '/../../../adm_program/system/login_valid.php');

// only authorized user are allowed to start this module
if (!isUserAuthorizedForPreferencesPIM()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// check if file_uploads is set to ON in the current server settings...
if (!PhpIniUtils::isFileUploadEnabled()) {
    $gMessage->show($gL10n->get('SYS_SERVER_NO_UPLOAD'));
    // => EXIT
}

$headline = $gL10n->get('PLG_INVENTORY_MANAGER_IMPORT');

// add current url to navigation stack
$gNavigation->addUrl(CURRENT_URL, $headline);

if (isset($_SESSION['import_request'])) {
    // due to incorrect input the user has returned to this form
    // now write the previously entered contents into the object
    $formValues = SecurityUtils::encodeHTML(StringUtils::strStripTags($_SESSION['import_request']));
    unset($_SESSION['import_request']);
}

// Make sure all potential form values have either a value from the previous request or the default
if (!isset($formValues['format'])) {
    $formValues['format'] = '';
}
if (!isset($formValues['import_sheet'])) {
    $formValues['import_sheet'] = '';
}
if (!isset($formValues['import_coding'])) {
    $formValues['import_coding'] = '';
}
if (!isset($formValues['import_separator'])) {
    $formValues['import_separator'] = '';
}
if (!isset($formValues['import_enclosure'])) {
    $formValues['import_enclosure'] = 'AUTO';
}

// create html page object
$page = new HtmlPage('admidio-items-import', $headline);

// show form
$form = new HtmlForm('import_items_form',  ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER . '/import/import_read_file.php', $page, array('enableFileUpload' => true));
$formats = array(
    'AUTO' => $gL10n->get('SYS_AUTO_DETECT'),
    'XLSX' => $gL10n->get('SYS_EXCEL_2007_365'),
    'XLS'  => $gL10n->get('SYS_EXCEL_97_2003'),
    'ODS'  => $gL10n->get('SYS_ODF_SPREADSHEET'),
    'CSV'  => $gL10n->get('SYS_COMMA_SEPARATED_FILE'),
    'HTML' => $gL10n->get('SYS_HTML_TABLE')
);
$form->addSelectBox(
    'format',
    $gL10n->get('SYS_FORMAT'),
    $formats,
    array('showContextDependentFirstEntry' => false, 'property' => HtmlForm::FIELD_REQUIRED, 'defaultValue' => $formValues['format'])
);
$page->addJavascript(
    '
    $("#format").change(function() {
        const format = $(this).children("option:selected").val();
         $(".import-setting").prop("disabled", true).parents("div.form-group").hide();
         $(".import-"+format).prop("disabled", false).parents("div.form-group").show("slow");
    });
    $("#format").trigger("change");',
    true
);

$form->addFileUpload(
    'userfile',
    $gL10n->get('SYS_CHOOSE_FILE'),
    array('property' => HtmlForm::FIELD_REQUIRED, 'allowedMimeTypes' => array('text/comma-separated-values',
            'text/csv',
            'text/html',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'application/vnd.oasis.opendocument.spreadsheet'
        )
    )
);

// Add format-specific settings (if specific format is selected)
// o) Worksheet: AUTO, XLSX, XLS, ODS, HTML (not CSV)
// o) Encoding (Default/Detect/UTF-8/ISO-8859-1/CP1252): CSV, HTML
// o) Delimiter (Detect/Comma/Tab/Semicolon): CSV
$form->addInput('import_sheet', $gL10n->get('SYS_WORKSHEET_NAMEINDEX'), '', array('class' => 'import-setting import-XLSX import-XLS import-ODS import-HTML import-AUTO'));

$selectBoxEntries = array(
    '' => $gL10n->get('SYS_DEFAULT_ENCODING_UTF8'),
    'GUESS' => $gL10n->get('SYS_ENCODING_GUESS'),
    'UTF-8' => $gL10n->get('SYS_UTF8'),
    'UTF-16BE' => $gL10n->get('SYS_UTF16BE'),
    'UTF-16LE' => $gL10n->get('SYS_UTF16LE'),
    'UTF-32BE' => $gL10n->get('SYS_UTF32BE'),
    'UTF-32LE' => $gL10n->get('SYS_UTF32LE'),
    'CP1252' => $gL10n->get('SYS_CP1252'),
    'ISO-8859-1' => $gL10n->get('SYS_ISO_8859_1')
);
$form->addSelectBox(
    'import_coding',
    $gL10n->get('SYS_CODING'),
    $selectBoxEntries,
    array('showContextDependentFirstEntry' => false, 'defaultValue' => $formValues['import_coding'], 'class' => 'import-setting import-CSV import-HTML')
);

$selectBoxEntries = array(
    '' => $gL10n->get('SYS_AUTO_DETECT'),
    ',' => $gL10n->get('SYS_COMMA'),
    ';' => $gL10n->get('SYS_SEMICOLON'),
    '\t' => $gL10n->get('SYS_TAB'),
    '|' => $gL10n->get('SYS_PIPE')
);
$form->addSelectBox(
    'import_separator',
    $gL10n->get('SYS_SEPARATOR_FOR_CSV_FILE'),
    $selectBoxEntries,
    array('showContextDependentFirstEntry' => false, 'defaultValue' => $formValues['import_separator'], 'class' => 'import-setting import-CSV')
);

$selectBoxEntries = array(
    'AUTO' => $gL10n->get('SYS_AUTO_DETECT'),
    '' => $gL10n->get('SYS_NO_QUOTATION'),
    '"' => $gL10n->get('SYS_DQUOTE'),
    '\'' => $gL10n->get('SYS_QUOTE')
);
$form->addSelectBox(
    'import_enclosure',
    $gL10n->get('SYS_FIELD_ENCLOSURE'),
    $selectBoxEntries,
    array('showContextDependentFirstEntry' => false, 'defaultValue' => $formValues['import_enclosure'], 'class' => 'import-setting import-CSV')
);

$form->addSubmitButton(
    'btn_forward',
    $gL10n->get('SYS_ASSIGN_FIELDS'),
    array('icon' => 'fa-arrow-circle-right', 'class' => ' offset-sm-3')
);

// add form to html page and show page
$page->addHtml($form->show());
$page->show();