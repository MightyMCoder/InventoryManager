<?php
/**
 ***********************************************************************************************
 * Assign columns of import file to profile fields
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
if (!isUserAuthorizedForPreferencesPIM()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

if (count($_SESSION['import_data']) === 0) {
    $gMessage->show($gL10n->get('SYS_FILE_NOT_EXIST'));
    // => EXIT
}

$headline = $gL10n->get('SYS_ASSIGN_FIELDS');

// add current url to navigation stack
$gNavigation->addUrl(CURRENT_URL, $headline);

if (isset($_SESSION['import_csv_request'])) {
    // due to incorrect input the user has returned to this form
    // now write the previously entered contents into the object
    $formValues = SecurityUtils::encodeHTML(StringUtils::strStripTags($_SESSION['import_csv_request']));
    unset($_SESSION['import_csv_request']);
    if (!isset($formValues['first_row'])) {
        $formValues['first_row'] = false;
    }
} else {
    $formValues['first_row'] = true;
}

/**
 * Function creates the html for each assignment of a profile field to a column of the import file.
 * @param array $arrayColumnList The array contains the following elements cat_name, cat_tooltip, id, name, name_intern, tooltip
 * @param array $arrayCsvColumns An array with the names of the columns from the import file.
 * @return string Returns the HTML of a table with all profile fields and possible assigned columns of the import file.
 * @throws Exception
 */
function getColumnAssignmentHtml(array $arrayColumnList, array $arrayCsvColumns): string
{
    $html = '';

    foreach ($arrayColumnList as $field) {
        foreach ($field as $key => $value) {
            $html .= '<tr>
                    <td><label for="'. $key. '" title="'.$value.'">'.$value . 
                    '</label></td>
                <td>';

            $selectEntries = '';
            // list all columns of the file
            $found = false;
            foreach ($arrayCsvColumns as $colKey => $colValue) {
                $colValue = trim(strip_tags(str_replace('"', '', $colValue)));

                $selected = '';
                // Otherwise, detect the entry where the column header
                // matches the Admidio field name or internal field name (case-insensitive)
                if (strtolower($colValue) == strtolower($value)) {
                    $selected .= ' selected="selected"';
                    $found = true;
                }
                $selectEntries .= '<option value="'.$colKey.'"'.$selected.'>'.$colValue.'</option>';
            }
            // add html for select box
            // Insert default (empty) entry and select if no other item is selected
            $html .= '
            <select class="form-control admidio-import-field" size="1" id="'. $key. '" name="'. $key. '">
                <option value=""'.($found ? ' selected="selected"' : '').'></option>
                ' . $selectEntries . '
            </select>

            </td></tr>';
        }
    }
    return $html;
}

// create html page object
$page = new HtmlPage('admidio-items-import-csv', $headline);
$page->addHtml('<p class="lead">'.$gL10n->get('PLG_INVENTORY_MANAGER_IMPORT_ASSIGN_FIELDS').'</p>');

// show form
$form = new HtmlForm('import_assign_fields_form', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_IM . '/import/import_items.php'), $page, array('type' => 'vertical'));
$form->addCheckbox('first_row', $gL10n->get('SYS_FIRST_LINE_COLUMN_NAME'), $formValues['first_row']);
$form->addHtml('<div class="alert alert-warning alert-small" id="admidio-import-unused"><i class="fas fa-exclamation-triangle"></i>'.$gL10n->get('PLG_INVENTORY_MANAGER_IMPORT_UNUSED_HEAD').'<div id="admidio-import-unused-fields">-</div></div>');

$page->addJavascript(
    '
    $(".admidio-import-field").change(function() {
        var available = [];
        $("#import_assign_fields_form .admidio-import-field").first().children("option").each(function() {
            if ($(this).text() != "") {
                available.push($(this).text());
            }
        });
        var used = [];
        $("#import_assign_fields_form .admidio-import-field").children("option:selected").each(function() {
            if ($(this).text() != "") {
                used.push($(this).text());
            }
        });
        var outstr = "";
        $(available).not(used).each(function(index, value) {
            if (value === "Nr.") {
            outstr += "<tr><td>" + value + "</td><td></td></tr>";
            } else {
            outstr += "<tr><td>" + value + "</td><td><a href=\"' . ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_IM . '/fields/fields_edit_new.php?field_name=" + encodeURIComponent(value) + "&redirect_to_import=true\" class=\"btn btn-primary btn-sm\">' . $gL10n->get('PLG_INVENTORY_MANAGER_ITEMFIELD_CREATE') . '</a></td></tr>";
            }
        });
        if (outstr == "") {
            outstr = "-";
        } else {
            outstr = "<table class=\"table table-condensed\"><tbody>" + outstr + "</tbody></table>";
        }
        $("#admidio-import-unused #admidio-import-unused-fields").html(outstr);
    });
    $(".admidio-import-field").trigger("change");',
    true
);


$htmlFieldTable = '
    <table class="table table-condensed import-config import-config-csv">
        <thead>
            <tr>
                <th>'.$gL10n->get('PLG_INVENTORY_MANAGER_ITEMFIELDS').'</th>
                <th>'.$gL10n->get('SYS_FILE_COLUMN').'</th>
            </tr>
        </thead>';

$arrayCsvColumns = $_SESSION['import_data'][0];
// Remove only null values
$arrayCsvColumns = array_filter($arrayCsvColumns, function ($value) {
    return !is_null($value);
});

$categoryId = null;
$arrayImportableFields = array();

$items = new CItems($gDb, $gCurrentOrgId);
$row = array();
foreach ($items->mItemFields as $columnKey => $columnValue) {
    $imfName = $columnValue->GetValue('imf_name');
    
    // If the field name starts with 'PIM_', it is a language key
    if (strpos($imfName, 'PIM_') !== false) {
        $row = array($columnValue->GetValue('imf_name_intern') => $gL10n->get($imfName));
    } else {
        $row = array($columnValue->GetValue('imf_name_intern') => $imfName);
    }
    
    $arrayImportableFields[] = $row;
}

$htmlFieldTable .= getColumnAssignmentHtml($arrayImportableFields, $arrayCsvColumns) .'
        </tbody>
    </table>';
$form->addHtml($htmlFieldTable);
$form->addSubmitButton('btn_forward', $gL10n->get('SYS_IMPORT'), array('icon' => 'fa-upload'));

// add form to html page and show page
$page->addHtml($form->show());
$page->show();
