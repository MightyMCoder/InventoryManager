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
 * key_id     : ID of the key who should be printed
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/classes/keys.php');
require_once(__DIR__ . '/common_function.php');
require_once(__DIR__ . '/classes/configtable.php');

// Initialize and check the parameters
$getKeyId = admFuncVariableIsValid($_GET, 'key_id',  'int');

$pimArray = array();

$pPreferences = new ConfigTablePIM();
$pPreferences->read();
$pPreferences->readPff();

if (substr_count($gNavigation->getUrl(), 'keys_export_to_pff') === 1)
{
	admRedirect(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER. '/inventory_manager.php');
	// => EXIT
}

$headline = $gL10n->get('PLG_INVENTORY_MANAGER_KEY_PRINT');
$gNavigation->addUrl(CURRENT_URL, $headline);

if (!array_key_exists($pPreferences->config['Optionen']['interface_pff'], $pPreferences->configpff['Formular']['desc']))
{
    $gMessage->show($gL10n->get('PLG_INVENTORY_MANAGER_PFF_CONFIG_NOT_FOUND'));
}
else 
{
    $pimArray['form_id'] = $pPreferences->config['Optionen']['interface_pff'];
}

$keys = new Keys($gDb, $gCurrentOrgId);
$keys->readKeyData($getKeyId, $gCurrentOrgId);
    	
foreach($keys->mKeyFields as $keyField)
{    		
	$imfNameIntern = $keyField->getValue('imf_name_intern');
    	
	$content = $keys->getValue($imfNameIntern, 'database');
    	
    if ($keys->getProperty($imfNameIntern, 'imf_type') === 'DATE')
    {
    	$content = $keys->getHtmlValue($imfNameIntern, $content);
    }
    elseif ( $keys->getProperty($imfNameIntern, 'imf_type') === 'DROPDOWN'
    	  || $keys->getProperty($imfNameIntern, 'imf_type') === 'RADIO_BUTTON')
    {
    	$arrListValues = $keys->getProperty($imfNameIntern, 'imf_value_list', 'text');
    	$content = $arrListValues[$content];
    }
    elseif ($keys->getProperty($imfNameIntern, 'imf_type') === 'CHECKBOX')
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
    		
    		