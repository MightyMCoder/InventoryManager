<?php
/**
 ***********************************************************************************************
 * Various functions for key fields
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * imf_id   : key field id
 * mode     : 1 - create or edit key field
 *            2 - delete key field
 *            4 - change sequence of key field
 * sequence : mode if the key field move up or down, values are TableUserField::MOVE_UP, TableUserField::MOVE_DOWN
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/common_function.php');
require_once(__DIR__ . '/classes/configtable.php');

// Initialize and check the parameters
$getimfId    = admFuncVariableIsValid($_GET, 'imf_id',   'int');
$getMode     = admFuncVariableIsValid($_GET, 'mode',     'int',    array('requireValue' => true));
$getSequence = admFuncVariableIsValid($_GET, 'sequence', 'string', array('validValues' => array(TableUserField::MOVE_UP, TableUserField::MOVE_DOWN)));

$pPreferences = new ConfigTablePIM();
$pPreferences->read();

// only authorized user are allowed to start this module
if (!isUserAuthorizedForPreferences())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

$keyField = new TableAccess($gDb, TBL_INVENTORY_MANAGER_FIELDS, 'imf');

if ($getimfId > 0)
{
	$keyField->readDataById($getimfId);

    // check if key field belongs to actual organization
    if ($keyField->getValue('imf_org_id') > 0
    && (int) $keyField->getValue('imf_org_id') !== (int) $gCurrentOrgId)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
}

if($getMode === 1)
{
    // Feld anlegen oder updaten
    
    $_SESSION['fields_request'] = $_POST;
    
    // pruefen, ob Pflichtfelder gefuellt sind
    // (bei Systemfeldern duerfen diese Felder nicht veraendert werden)
    if ($keyField->getValue('imf_system') == 0 && $_POST['imf_name'] === '')
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_NAME'))));
        // => EXIT
    }
    
    if ($keyField->getValue('imf_system') == 0 && $_POST['imf_type'] === '')
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('ORG_DATATYPE'))));
        // => EXIT
    }
    
    if (($_POST['imf_type'] === 'DROPDOWN' || $_POST['imf_type'] === 'RADIO_BUTTON')
        && $_POST['imf_value_list'] === '')
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('ORG_VALUE_LIST'))));
        // => EXIT
    }
    
    if (isset($_POST['imf_name']) && $keyField->getValue('imf_name') !== $_POST['imf_name'])
    {
        // Schauen, ob das Feld bereits existiert
        $sql = 'SELECT COUNT(*) AS count
                  FROM '.TBL_INVENTORY_MANAGER_FIELDS.'
                 WHERE imf_name   = ? -- $_POST[\'imf_name\']
				   AND ( imf_org_id = ? -- $gCurrentOrgId
                    OR imf_org_id IS NULL )
                   AND imf_id    <> ? -- $getimfId ';
        $statement = $gDb->queryPrepared($sql, array($_POST['imf_name'], $gCurrentOrgId, $getimfId));
        
        if ((int) $statement->fetchColumn() > 0)
        {
            $gMessage->show($gL10n->get('ORG_FIELD_EXIST'));
            // => EXIT
        }
    }
    
    // make html in description secure
    $_POST['imf_description'] = admFuncVariableIsValid($_POST, 'imf_description', 'html');
    
    
    if(!isset($_POST['imf_mandatory']))
    {
        $_POST['imf_mandatory'] = 0;
    }
    
    try
    {
        // POST Variablen in das UserField-Objekt schreiben
        foreach ($_POST as $key => $value)
        {
            if(StringUtils::strStartsWith($key, 'imf_'))
            {
                $keyField->setValue($key, $value);
            }
        }
    }
    catch(AdmException $e)
    {
        $e->showHtml();
    }
    
    $keyField->setValue('imf_org_id', (int) $gCurrentOrgId);
    
    if ($keyField->isNewRecord())
    {
        $keyField->setValue('imf_name_intern', getNewNameIntern($keyField->getValue('imf_name', 'database'), 1));
        $keyField->setValue('imf_sequence', genNewSequence());
    }
    
    // Daten in Datenbank schreiben
    $returnCode = $keyField->save();
    
    if ($returnCode < 0)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
    
    unset($_SESSION['fields_request']);
    
    $gMessage->setForwardUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/fields.php', 1000);
    $gMessage->show($gL10n->get('SYS_SAVE_DATA'));
    // => EXIT
}
elseif ($getMode === 2)
{
    // Feld loeschen
    if ($keyField->delete())
    {
        // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
        echo 'done';
    }
    exit();
}
elseif($getMode === 4)
{
    $imfSequence = (int) $keyField->getValue('imf_sequence');
    
    $sql = 'UPDATE '.TBL_INVENTORY_MANAGER_FIELDS.'
               SET imf_sequence = ? -- $imf_sequence
             WHERE imf_sequence = ? -- $imf_sequence -/+ 1
               AND ( imf_org_id = ? -- $gCurrentOrgId
                OR imf_org_id IS NULL ) ';
    
    // field will get one number lower and therefore move a position up in the list
    if ($getSequence === TableUserField::MOVE_UP)
    {
        $newSequence = $imfSequence - 1;
    }
    // field will get one number higher and therefore move a position down in the list
    elseif ($getSequence === TableUserField::MOVE_DOWN)
    {
        $newSequence = $imfSequence + 1;
    }
    
    // update the existing entry with the sequence of the field that should get the new sequence
    $gDb->queryPrepared($sql, array($imfSequence, $newSequence, $gCurrentOrgId));
    
    $keyField->setValue('imf_sequence', $newSequence);
    $keyField->save();
    
    exit();
}
