<?php
/**
 ***********************************************************************************************
 * Delete a key or make a key to the former
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * mode       : 1 - Menu and preview of the key who should be deleted or made to the former
 *              2 - Delete a key
 *              3 - Make the key to the former
 * key_id     : ID of the key who should be deleted or made to the former
 * key_former : 0 - Key is activ
 * 			    1 - Key is already made to the former
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/common_function.php');
require_once(__DIR__ . '/classes/keys.php');
require_once(__DIR__ . '/classes/configtable.php');

// Initialize and check the parameters
$getMode      = admFuncVariableIsValid($_GET, 'mode',      'numeric', array('defaultValue' => 1));
$getKeyId     = admFuncVariableIsValid($_GET, 'key_id',    'int');
$getKeyFormer = admFuncVariableIsValid($_GET, 'key_former', 'bool');

$pPreferences = new ConfigTablePIM();
$pPreferences->read();

// only authorized user are allowed to start this module
if (!isUserAuthorizedForPreferences())
{
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$keys = new Keys($gDb, $gCurrentOrgId);
$keys->readKeyData($getKeyId, $gCurrentOrgId);
$user = new User($gDb, $gProfileFields);

switch ($getMode)
{
    case 1:
    		
    	$headline = $gL10n->get('PLG_INVENTORY_MANAGER_KEY_DELETE');
    		
    	// create html page object
    	$page = new HtmlPage('plg-inventory-manager-keys-delete', $headline);
    		
    	// add current url to navigation stack
    	$gNavigation->addUrl(CURRENT_URL, $headline);
    		
    	$page->addHtml('<p class="lead">'.$gL10n->get('PLG_INVENTORY_MANAGER_KEY_DELETE_DESC').'</p>');
    		
    	// show form
    	$form = new HtmlForm('key_delete_form', null, $page);
    	
    	foreach ($keys->mKeyFields as $keyField)
    	{
    		$imfNameIntern = $keyField->getValue('imf_name_intern');
    	
    		$content = $keys->getValue($imfNameIntern, 'database');
    		if ($imfNameIntern === 'RECEIVER' && strlen($content) > 0)
    		{
    			$user->readDataById($content);
    			$content = $user->getValue('LAST_NAME').', '.$user->getValue('FIRST_NAME');
    		}
    		elseif ($keys->getProperty($imfNameIntern, 'imf_type') === 'DATE')
    		{
    			$content = $keys->getHtmlValue($imfNameIntern, $content);
    		}
    		elseif ( $keys->getProperty($imfNameIntern, 'imf_type') === 'DROPDOWN'
    				|| $keys->getProperty($imfNameIntern, 'imf_type') === 'RADIO_BUTTON')
    		{
    			$arrListValues = $keys->getProperty($imfNameIntern, 'imf_value_list', 'text');
    			$content = $arrListValues[$content];
    		}
    		
    		$form->addInput(
    			'imf-'. $keys->getProperty($imfNameIntern, 'imf_id'),
    			convlanguagePIM($keys->getProperty($imfNameIntern, 'imf_name')),
    			$content,
    			array('property' => HtmlForm::FIELD_DISABLED)
    		);
    	 }	
    	
    	$form->addButton('btn_delete', $gL10n->get('SYS_DELETE'), array('icon' => 'fa-trash-alt', 'link' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/keys_delete.php', array('key_id' => $getKeyId, 'mode' => 2)), 'class' => 'btn-primary offset-sm-3'));
    	if (!$getKeyFormer)
    	{
    		$form->addButton('btn_former', $gL10n->get('PLG_INVENTORY_MANAGER_FORMER'), array('icon' => 'fa-eye-slash', 'link' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/keys_delete.php', array('key_id' => $getKeyId, 'mode' => 3)), 'class' => 'btn-primary offset-sm-3'));
    		$form->addCustomContent('', '<br />'.$gL10n->get('PLG_INVENTORY_MANAGER_KEY_MAKE_TO_FORMER'));
    	}
    	
    	// add form to html page and show page
    	$page->addHtml($form->show(false));
    	$page->show();
    	break;
    		
    case 2:
    		
    	$sql = 'DELETE FROM '.TBL_INVENTORY_MANAGER_LOG.'
        	          WHERE iml_imk_id = ? ';
    	$gDb->queryPrepared($sql, array($getKeyId));
    	
    	$sql = 'DELETE FROM '.TBL_INVENTORY_MANAGER_DATA.'
        		      WHERE imd_imk_id = ? ';
    	$gDb->queryPrepared($sql, array($getKeyId));
    
    	$sql = 'DELETE FROM '.TBL_INVENTORY_MANAGER_KEYS.'
        		      WHERE imk_id = ? -- $getKeyId
    			        AND ( imk_org_id = ? -- $gCurrentOrgId
                         OR imk_org_id IS NULL ) ';
    	$gDb->queryPrepared($sql, array($getKeyId, $gCurrentOrgId));
    	
    	// go back to key view
    	$gMessage->setForwardUrl($gNavigation->getPreviousUrl(), 1000);
		$gMessage->show($gL10n->get('PLG_INVENTORY_MANAGER_KEY_DELETED'));

    	break;
    	// => EXIT
    	
    case 3:
    	
    	$sql = 'UPDATE '.TBL_INVENTORY_MANAGER_KEYS.'
                   SET imk_former = 1
                 WHERE imk_id = ? ';
    	$gDb->queryPrepared($sql, array($getKeyId));
    		
    	// go back to key view
    	$gMessage->setForwardUrl($gNavigation->getPreviousUrl(), 1000);
    	$gMessage->show($gL10n->get('PLG_INVENTORY_MANAGER_KEY_MADE_TO_FORMER'));
    	break;
    	// => EXIT
}
    		
    		
    		
    		