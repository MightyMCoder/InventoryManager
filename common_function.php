<?php

/**
 ***********************************************************************************************
 * Common functions for the Admidio plugin InventoryManager
 *
 * @see         https://github.com/MightyMCoder/InventoryManager/ The InventoryManager GitHub project
 * @author      MightyMCoder
 * @copyright   2024 - today MightyMCoder
 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 only
 * 
 * methods:
 * 
 * defineConstantsPIM()                          : Define necessary constants if not already defined
 * isUserAuthorizedForPIM($scriptName)           : Check if the user is authorized to access the plugin
 * getMenuIdByScriptNamePIM($scriptName)         : Get menu ID by script name
 * isUserAuthorizedForPreferencesPIM()           : Check if the user is authorized to access the Preferences module
 * function isUserAuthorizedForAddinPIM()		 : Check if the user is authorized to see the Inventory Manager Addin on the profile page
 * convlanguagePIM($field_name)                  : Translate field name according to naming conventions
 * getNewNameInternPIM($name, $index)            : Generate a new internal name
 * genNewSequencePIM()                           : Generate a new sequence number
 * umlautePIM($tmptext)                          : Replace umlauts in the text
 * getPreferencePanelPIM($group, $id, $title, $icon, $body) : Generate HTML for a preference panel
 * 
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../adm_program/system/common.php');

defineConstantsPIM();

/**
 * Define necessary constants if not already defined
 */
function defineConstantsPIM()
{
	global $g_tbl_praefix;

	if (!defined('PLUGIN_FOLDER_IM')) {
		define('PLUGIN_FOLDER_IM', '/' . basename(__DIR__));
	}
	if (!defined('TBL_INVENTORY_MANAGER_FIELDS')) {
		define('TBL_INVENTORY_MANAGER_FIELDS', $g_tbl_praefix . '_inventory_manager_fields');
	}
	if (!defined('TBL_INVENTORY_MANAGER_DATA')) {
		define('TBL_INVENTORY_MANAGER_DATA', $g_tbl_praefix . '_inventory_manager_data');
	}
	if (!defined('TBL_INVENTORY_MANAGER_ITEMS')) {
		define('TBL_INVENTORY_MANAGER_ITEMS', $g_tbl_praefix . '_inventory_manager_items');
	}
	if (!defined('TBL_INVENTORY_MANAGER_LOG')) {
		define('TBL_INVENTORY_MANAGER_LOG', $g_tbl_praefix . '_inventory_manager_log');
	}
}

/**
 * Check if the user is authorized to access the plugin
 * @param string $scriptName 		The script name of the plugin
 * @return bool						true if the user is authorized
 */
function isUserAuthorizedForPIM($scriptName)
{
	global $gMessage, $gL10n, $gDb, $gCurrentUser;
	$gCurrentUser = $GLOBALS['gCurrentUser'];

	$userIsAuthorized = false;
	$menId = getMenuIdByScriptNamePIM($scriptName);

	if ($menId === null) {
		$gMessage->show($gL10n->get('PLG_INVENTORY_MANAGER_MENU_URL_ERROR', [$scriptName]), $gL10n->get('SYS_ERROR'));
	} else {
		$sql = 'SELECT men_id, men_com_id, com_name_intern FROM ' . TBL_MENU . '
			LEFT JOIN ' . TBL_COMPONENTS . ' ON com_id = men_com_id
			WHERE men_id = ? ORDER BY men_men_id_parent DESC, men_order;';
		$menuStatement = $gDb->queryPrepared($sql, array($menId));

		while ($row = $menuStatement->fetch()) {
			if ((int)$row['men_com_id'] === 0 || Component::isVisible($row['com_name_intern'])) {
				$displayMenu = new RolesRights($gDb, 'menu_view', $row['men_id']);
				$rolesDisplayRight = $displayMenu->getRolesIds();

				if (count($rolesDisplayRight) === 0 || $displayMenu->hasRight($gCurrentUser->getRoleMemberships())) {
					$userIsAuthorized = true;
				}
			}
		}
	}
	return $userIsAuthorized;
}

/**
 * Get menu ID by script name
 * @param string $scriptName		The script name of the plugin
 * @return int|null					The menu ID or null if not found
 */
function getMenuIdByScriptNamePIM($scriptName)
{
	global $gDb;

	$sql = 'SELECT men_id FROM ' . TBL_MENU . ' WHERE men_url = ?;';
	$menuStatement = $gDb->queryPrepared($sql, array($scriptName));

	if ($menuStatement->rowCount() === 1) {
		return (int)$menuStatement->fetch()['men_id'];
	}
	return null;
}

/**
 * Check if the user is authorized to access the Preferences module
 * @return bool 					true if the user is authorized
 */
function isUserAuthorizedForPreferencesPIM()
{
	global $pPreferences, $gCurrentUser;
	$gCurrentUser = $GLOBALS['gCurrentUser'];

	if ($gCurrentUser->isAdministrator()) {
		return true;
	}

	foreach ($pPreferences->config['access']['preferences'] as $roleId) {
		if ($gCurrentUser->isMemberOfRole((int)$roleId)) {
			return true;
		}
	}
	return false;
}

/**
 * Check if the user is authorized to see the Inventory Manager Addin on the profile page
 * @return bool 					true if the user is authorized
 */
function isUserAuthorizedForAddinPIM()
{
	global $gDb;
	
	$sql = 'SELECT men_id, men_name, men_name_intern
			FROM ' . TBL_MENU . '
			WHERE men_men_id_parent IS NULL
			ORDER BY men_order';

	$mainNodesStatement = $gDb->queryPrepared($sql);

	while ($mainNodes = $mainNodesStatement->fetch()) {
		$menuNodes = new MenuNode($mainNodes['men_name_intern'], $mainNodes['men_name']);

		$nodeId = $mainNodes['men_id'];
		$sql = 'SELECT men_id, men_com_id, men_name_intern, men_name, men_description, men_url, men_icon, com_name_intern
				FROM ' . TBL_MENU . '
				LEFT JOIN ' . TBL_COMPONENTS . ' ON com_id = men_com_id
				WHERE men_men_id_parent = ?
				ORDER BY men_men_id_parent DESC, men_order';

		$nodesStatement = $gDb->queryPrepared($sql, array($nodeId));

		while ($node = $nodesStatement->fetch(PDO::FETCH_ASSOC)) {
			if ((int) $node['men_com_id'] === 0 || Component::isVisible($node['com_name_intern'])) {
				if ($node['men_url'] === "/adm_plugins/InventoryManager/inventory_manager.php" && $menuNodes->menuItemIsVisible($node['men_id'])) {
					return true;
				}
			}
		}
	}
	return false;
}

/**
 * Translate field name according to naming conventions
 * @param string $field_name		field name to translate
 * @return string 					translated field name
 */
function convlanguagePIM($field_name)
{
	global $gL10n;

	return (substr($field_name, 3, 1) === '_') ? $gL10n->get($field_name) : $field_name;
}

/**
 * Generate a new internal name
 * @param string $name				name to generate internal name from
 * @param int $index				index to append to the internal name
 * @return string 					new internal name
 */
function getNewNameInternPIM($name, $index)
{
	global $gDb;

	$name = umlautePIM($name);
	$newNameIntern = strtoupper(str_replace(' ', '_', $name));

	if ($index > 1) {
		$newNameIntern .= '_' . $index;
	}

	$sql = 'SELECT imf_id FROM ' . TBL_INVENTORY_MANAGER_FIELDS . ' WHERE imf_name_intern = ?;';
	$userFieldsStatement = $gDb->queryPrepared($sql, array($newNameIntern));

	if ($userFieldsStatement->rowCount() > 0) {
		return getNewNameInternPIM($name, ++$index);
	}

	return $newNameIntern;
}

/**
 * Generate a new sequence number
 * @return int 						new sequence number
 */
function genNewSequencePIM()
{
	global $gDb, $gCurrentOrgId;

	$sql = 'SELECT max(imf_sequence) as max_sequence FROM ' . TBL_INVENTORY_MANAGER_FIELDS . ' WHERE (imf_org_id = ? OR imf_org_id IS NULL);';
	$statement = $gDb->queryPrepared($sql, array($gCurrentOrgId));
	$row = $statement->fetch();

	return $row['max_sequence'] + 1;
}

/**
 * Replace umlauts in the text
 * @param string $tmptext			text to replace umlauts in
 * @return string 					text with replaced umlauts
 */
function umlautePIM($tmptext)
{
	$replacements = [
		'&uuml;' => 'ue',
		'&auml;' => 'ae',
		'&ouml;' => 'oe',
		'&szlig;' => 'ss',
		'&Uuml;' => 'Ue',
		'&Auml;' => 'Ae',
		'&Ouml;' => 'Oe',
		'.' => '',
		',' => '',
		'/' => ''
	];

	return str_replace(array_keys($replacements), array_values($replacements), htmlentities($tmptext));
}

/**
 * Generate HTML for a preference panel
 * @param string $group				group the preference panel belongs to
 * @param string $id				unique ID of the preference panel
 * @param string $title				title of the preference panel
 * @param string $icon				icon of the preference panel
 * @param string $body				body of the preference panel
 * @return string 					HTML for the preference panel
 */
function getPreferencePanelPIM($group, $id, $title, $icon, $body)
{
	return '
		<div class="card" id="' . $group . '_panel_' . $id . '">
			<div class="card-header" data-toggle="collapse" data-target="#collapse_' . $id . '">
				<i class="' . $icon . ' fa-fw"></i>' . $title . '
			</div>
			<div id="collapse_' . $id . '" class="collapse" aria-labelledby="headingOne" data-parent="#accordion_preferences">
				<div class="card-body">
					' . $body . '
				</div>
			</div>
		</div>
	';
}
