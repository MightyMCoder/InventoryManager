<?php
/**
 ***********************************************************************************************
 * Save item data
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * item_id    : >0 -  ID of the item who should be saved
 * 			   0  -  a new item will be added 
 * copy_numer: number of new items
 * copy_field: field for a current number
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/classes/items.php');
require_once(__DIR__ . '/common_function.php');

// Initialize and check the parameters
$getItemId       = admFuncVariableIsValid($_GET, 'item_id',  'int');
$postCopyNumber = admFuncVariableIsValid($_POST, 'copy_number', 'numeric', array('defaultValue' => 1));
$postCopyField  = admFuncVariableIsValid($_POST, 'copy_field',  'int');

$items = new CItems($gDb, $gCurrentOrgId);

$startIdx = 1;
if ($postCopyField > 0)												// a field for a current number was selected	
{
	if (isset($_POST['imf-'. $postCopyField]))
	{
		$startIdx = (int) $_POST['imf-'. $postCopyField] +1;
	}
}
$stopIdx = $startIdx + $postCopyNumber;

for ($i = $startIdx; $i < $stopIdx; ++$i)
{
	$_POST['imf-'. $postCopyField] = $i;

	$items->readItemData($getItemId, $gCurrentOrgId);
	
	if ($getItemId == 0)
	{
		$items->getNewItemId();
	}

	// check all item fields
	foreach ($items->mItemFields as $itemField)
	{
    	$postId = 'imf-'. $itemField->getValue('imf_id');

    	if (isset($_POST[$postId]))
   	 	{
        	if ((strlen($_POST[$postId]) === 0 && $itemField->getValue('imf_mandatory') == 1))
        	{
            	$gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array(convlanguagePIM($itemField->getValue('imf_name')))));
            	// => EXIT
        	}

        	// Wert aus Feld in das Item-Klassenobjekt schreiben
        	$returnCode = $items->setValue($itemField->getValue('imf_name_intern'), $_POST[$postId]);

        	// Fehlermeldung
        	if (!$returnCode)
        	{
        		$gMessage->show($gL10n->get('SYS_DATABASE_ERROR'), $gL10n->get('SYS_ERROR'));
        		// => EXIT
        	}   
    	}
    	else
    	{
        	// Checkboxen uebergeben bei 0 keinen Wert, deshalb diesen hier setzen
        	if ($itemField->getValue('imf_type') === 'CHECKBOX')
        	{
            	$items->setValue($itemField->getValue('imf_name_intern'), '0');
        	}
    	}
	}

	/*------------------------------------------------------------*/
	// Save item data to database
	/*------------------------------------------------------------*/
	$gDb->startTransaction();

	try
	{
		$items->saveItemData();
	}
	catch(AdmException $e)
	{
    	$gMessage->setForwardUrl($gNavigation->getPreviousUrl());
    	$gNavigation->deleteLastUrl();
    	$e->showHtml();
    	// => EXIT
	}
	$gDb->endTransaction();
}

$gNavigation->deleteLastUrl();

// go back to item view
if ($gNavigation->count() > 2)                               // only in item copy 
{
	$gNavigation->deleteLastUrl();
}
	
$gMessage->setForwardUrl($gNavigation->getUrl(), 1000);
$gMessage->show($gL10n->get('SYS_SAVE_DATA'));


