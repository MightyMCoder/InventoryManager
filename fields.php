<?php
/**
 ***********************************************************************************************
 * Overview and maintenance of key fields
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 ***********************************************************************************************
 */
 
require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/common_function.php');
require_once(__DIR__ . '/classes/configtable.php');
require_once(__DIR__ . '/classes/keys.php');

$pPreferences = new ConfigTablePIM();
$pPreferences->read();

// only authorized user are allowed to start this module
if (!isUserAuthorizedForPreferences())
{
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// set module headline
$headline = $gL10n->get('PLG_INVENTORY_MANAGER_KEYFIELDS');

$gNavigation->addUrl(CURRENT_URL, $headline);

unset($_SESSION['fields_request']);

$keys = new Keys($gDb, $gCurrentOrgId);

// create html page object
$page = new HtmlPage('plg-inventory-manager-fields', $headline);

$page->addJavascript('
    /**
     * @param {string} direction
     * @param {int}    imfID
     */
    function moveCategory(direction, imfID) {
        var actRow = document.getElementById("row_imf_" + imfID);
        var childs = actRow.parentNode.childNodes;
        var prevNode    = null;
        var nextNode    = null;
        var actRowCount = 0;
        var actSequence = 0;
        var secondSequence = 0;
        $(".admidio-icon-link .fas").tooltip("hide");

        // erst einmal aktuelle Sequenz und vorherigen/naechsten Knoten ermitteln
        for (var i = 0; i < childs.length; i++) {
            if (childs[i].tagName === "TR") {
                actRowCount++;
                if (actSequence > 0 && nextNode === null) {
                    nextNode = childs[i];
                }

                if (childs[i].id === "row_imf_" + imfID) {
                    actSequence = actRowCount;
                }

                if (actSequence === 0) {
                    prevNode = childs[i];
                }
            }
        }

        // entsprechende Werte zum Hoch- bzw. Runterverschieben ermitteln
        if (direction === "UP") {
            if (prevNode !== null) {
                actRow.parentNode.insertBefore(actRow, prevNode);
                secondSequence = actSequence - 1;
            }
        } else {
            if (nextNode !== null) {
                actRow.parentNode.insertBefore(nextNode, actRow);
                secondSequence = actSequence + 1;
            }
        }

        if (secondSequence > 0) {
            // Nun erst mal die neue Position von dem gewaehlten Feld aktualisieren
            $.get("' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . '/inventory/fields_function.php', array('mode' => 4)) . '&imf_id=" + imfID + "&sequence=" + direction);
        }
    }
');

$page->addPageFunctionsMenuItem('admMenuItemPreferencesLists', $gL10n->get('PLG_INVENTORY_MANAGER_KEYFIELD_CREATE'), ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/fields_edit_new.php',  'fa-plus-circle');
    
// Create table
$table = new HtmlTable('tbl_profile_fields', $page, true);
$table->setMessageIfNoRowsFound('ORG_NO_FIELD_CREATED');

// create array with all column heading values
$columnHeading = array(
    $gL10n->get('SYS_FIELD'),
    '&nbsp;',
    $gL10n->get('SYS_DESCRIPTION'),
  '<i class="fas fa-asterisk" data-toggle="tooltip" title="'.$gL10n->get('SYS_REQUIRED_INPUT').'"></i>',
    $gL10n->get('ORG_DATATYPE'),
    '&nbsp;'
);
$table->addRowHeadingByArray($columnHeading);

// Intialize variables
$description = '';
$mandatory   = '';
$imfSystem   = '';     

foreach ($keys->mKeyFields as $keyField)
{	
    $imfId = (int) $keyField->getValue('imf_id');
 
    // cut long text strings and provide tooltip
    if($keyField->getValue('imf_description') === '')
    {
        $fieldDescription = '&nbsp;';
    }
    else
    {
        $fieldDescription = $keyField->getValue('imf_description', 'database');

        if(strlen($fieldDescription) > 60)
        {
            // read first 60 chars of text, then search for last space and cut the text there. After that add a "more" link
            $textPrev = substr($fieldDescription, 0, 60);
            $maxPosPrev = strrpos($textPrev, ' ');
            $fieldDescription = substr($textPrev, 0, $maxPosPrev).
                ' <span class="collapse" id="viewdetails'.$imfId.'">'.substr($fieldDescription, $maxPosPrev).'.
                </span> <a class="admidio-icon-link" data-toggle="collapse" data-target="#viewdetails'.$imfId.'"><i class="fas fa-angle-double-right" data-toggle="tooltip" title="'.$gL10n->get('SYS_MORE').'"></i></a>';
        }
    }

    if ($keyField->getValue('imf_mandatory') == 1)
    {
        $mandatory = '<i class="fas fa-asterisk" data-toggle="tooltip" title="'.$gL10n->get('SYS_REQUIRED_INPUT').'"></i>';
    }
    else
    {
        $mandatory = '<i class="fas fa-asterisk admidio-opacity-reduced" data-toggle="tooltip" title="'.$gL10n->get('ORG_FIELD_NOT_MANDATORY').'"></i>';
    }

    $keyFieldText = array(
    					'CHECKBOX'     => $gL10n->get('SYS_CHECKBOX'),
                        'DATE'         => $gL10n->get('SYS_DATE'),
                        'DROPDOWN'     => $gL10n->get('SYS_DROPDOWN_LISTBOX'),
                        'RADIO_BUTTON' => $gL10n->get('SYS_RADIO_BUTTON'),
                        'TEXT'         => $gL10n->get('SYS_TEXT').' (100)',
                        'TEXT_BIG'     => $gL10n->get('SYS_TEXT').' (4000)',
                        'NUMBER'       => $gL10n->get('SYS_NUMBER'),
                        'DECIMAL'      => $gL10n->get('SYS_DECIMAL_NUMBER'));
                        
    $imfSystem = '';  
           
    if ($keyField->getValue('imf_system') == 1)
    {
        $imfSystem .= '<i class="fas fa-trash invisible"></i>';
    }
    else
    {
    	$imfSystem .= '<a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL. FOLDER_PLUGINS . PLUGIN_FOLDER .'/fields_delete.php', array('imf_id' => $imfId)).'">
                <i class="fas fa-trash-alt" data-toggle="tooltip" title="'.$gL10n->get('SYS_DELETE').'"></i></a>';
    }

    // create array with all column values
    $columnValues = array(
        '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL. FOLDER_PLUGINS . PLUGIN_FOLDER .'/fields_edit_new.php', array('imf_id' => $imfId)).'">'. convlanguagePIM($keyField->getValue('imf_name')).'</a> ',
        '<a class="admidio-icon-link" href="javascript:void(0)" onclick="moveCategory(\''.TableUserField::MOVE_UP.'\', '.$imfId.')">
            <i class="fas fa-chevron-circle-up" data-toggle="tooltip" title="' . $gL10n->get('SYS_MOVE_UP', array('SYS_PROFILE_FIELD')) . '"></i></a>
        <a class="admidio-icon-link" href="javascript:void(0)" onclick="moveCategory(\''.TableUserField::MOVE_DOWN.'\', '.$imfId.')">
            <i class="fas fa-chevron-circle-down" data-toggle="tooltip" title="' . $gL10n->get('SYS_MOVE_DOWN', array('SYS_PROFILE_FIELD')) . '"></i></a>',     
        $fieldDescription,
        $mandatory,
    	$keyFieldText[$keyField->getValue('imf_type')],
        $imfSystem
    );
    $table->addRowByArray($columnValues, 'row_imf_'.$imfId);
}

$page->addHtml($table->show());
$page->show();
