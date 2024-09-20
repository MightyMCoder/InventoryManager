<?php
/**
 ***********************************************************************************************
 * Prepare print data for plugin FormFiller 
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * item_id     : ID of the item who should be printed
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/classes/items.php');
require_once(__DIR__ . '/common_function.php');
require_once(__DIR__ . '/classes/configtable.php');

// Initialize and check the parameters
$getItemId = admFuncVariableIsValid($_GET, 'item_id',  'int');

$pimArray = array();

$pPreferences = new CConfigTablePIM();
$pPreferences->read();
$pPreferences->readPff();

if (substr_count($gNavigation->getUrl(), 'items_export_to_pff') === 1)
{
	admRedirect(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER. '/inventory_manager.php');
	// => EXIT
}

$headline = $gL10n->get('PLG_INVENTORY_MANAGER_ITEM_PRINT');
$gNavigation->addUrl(CURRENT_URL, $headline);

if (!array_key_exists($pPreferences->config['Optionen']['interface_pff'], $pPreferences->configPff['Formular']['desc']))
{
    $gMessage->show($gL10n->get('PLG_INVENTORY_MANAGER_PFF_CONFIG_NOT_FOUND'));
}
else 
{
    $pimArray['form_id'] = $pPreferences->config['Optionen']['interface_pff'];
}

$items = new CItems($gDb, $gCurrentOrgId);
$items->readItemData($getItemId, $gCurrentOrgId);
    	
foreach($items->mItemFields as $itemField)
{    		
	$imfNameIntern = $itemField->getValue('imf_name_intern');
    	
	$content = $items->getValue($imfNameIntern, 'database');
    	
    if ($items->getProperty($imfNameIntern, 'imf_type') === 'DATE')
    {
    	$content = $items->getHtmlValue($imfNameIntern, $content);
    }
    elseif ( $items->getProperty($imfNameIntern, 'imf_type') === 'DROPDOWN'
    	  || $items->getProperty($imfNameIntern, 'imf_type') === 'RADIO_BUTTON')
    {
    	$arrListValues = $items->getProperty($imfNameIntern, 'imf_value_list', 'text');
    	$content = $arrListValues[$content];
    }
    elseif ($items->getProperty($imfNameIntern, 'imf_type') === 'CHECKBOX')
    {
    	if ($content == 1)
    	{
    		$content = $gL10n->get('SYS_YES');
    	}
    	else
    	{
    		$content = $gL10n->get('SYS_NO');
    	}
    }
    
    $pimArray['imf-'. $imfNameIntern] = $content;
}

admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS .'/'.$pPreferences->pffDir().'/createpdf.php', $pimArray));
    		
    		