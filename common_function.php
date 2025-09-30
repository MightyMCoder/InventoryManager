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
 *
 * Methods:
 * defineConstantsPIM()                                         : Define necessary constants if not already defined
 * isUserAuthorizedForPIM($scriptName)                          : Check if the user is authorized to access the plugin
 * isUserAuthorizedForPreferencesPIM()                          : Check if the user is authorized to access the Preferences module
 * isUserAuthorizedForAddinPIM()                                : Check if the user is authorized to see the
 *                                                                    Inventory Manager Addin on the profile page
 * isKeeperAuthorizedToEdit($keeper)                            : Check if the keeper is authorized to edit specific item data
 * getMenuIdByScriptNamePIM($scriptName)                        : Get menu ID by script name
 * convlanguagePIM($field_name)                                 : Translate field name according to naming conventions
 * getNewNameInternPIM($name, $index)                           : Generate a new internal name
 * genNewSequencePIM()                                          : Generate a new sequence number
 * umlautePIM($tmptext)                                         : Replace umlauts in the text
 * getPreferencePanelPIM($group, $id, $title, $icon, $body)     : Generate HTML for a preference panel
 * getSqlOrganizationsUsersCompletePIM()                        : Get all users with their id, name, and address
 * getSqlOrganizationsUsersShortPIM()                           : Get all users with their id and name
 * formatSpreadsheet($spreadsheet, $data, $containsHeadline)    : Format the spreadsheet
 ***********************************************************************************************
 */

// PhpSpreadsheet namespaces
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// compatibility for Admidio 5.0 ->
if (file_exists(__DIR__ . '/../../system/common.php')) {
    require_once(__DIR__ . '/../../system/common.php');
} else {
    require_once(__DIR__ . '/../../adm_program/system/common.php');
}
if (file_exists(__DIR__ . '/../../system/bootstrap/constants.php')) {
    require_once(__DIR__ . '/../../system/bootstrap/constants.php');
} else {
    require_once(__DIR__ . '/../../adm_program/system/bootstrap/constants.php');
}

// classes for Admidio 5.0
if (!version_compare(ADMIDIO_VERSION, '5.0', '<')) {
    class_alias(Admidio\Components\Entity\Component::class, Component::class);
    class_alias(Admidio\Roles\Entity\RolesRights::class, RolesRights::class);
    class_alias(Admidio\Menu\ValueObject\MenuNode::class, MenuNode::class);
}
// <- compatibility for Admidio 5.0

// Define necessary constants if not already defined
defineConstantsPIM();

/**
 * Define necessary constants if not already defined
 *
 * @return void
 */
function defineConstantsPIM(): void
{
    if (!defined('PLUGIN_FOLDER_IM')) {
        define('PLUGIN_FOLDER_IM', '/' . basename(__DIR__));
    }
    if (!defined('TBL_INVENTORY_MANAGER_FIELDS')) {
        define('TBL_INVENTORY_MANAGER_FIELDS', TABLE_PREFIX . '_inventory_manager_fields');
    }
    if (!defined('TBL_INVENTORY_MANAGER_DATA')) {
        define('TBL_INVENTORY_MANAGER_DATA', TABLE_PREFIX . '_inventory_manager_data');
    }
    if (!defined('TBL_INVENTORY_MANAGER_ITEMS')) {
        define('TBL_INVENTORY_MANAGER_ITEMS', TABLE_PREFIX . '_inventory_manager_items');
    }
    if (!defined('TBL_INVENTORY_MANAGER_LOG')) {
        define('TBL_INVENTORY_MANAGER_LOG', TABLE_PREFIX . '_inventory_manager_log');
    }
    if (!defined('TBL_PLUGIN_PREFERENCES')) {
        define('TBL_PLUGIN_PREFERENCES', TABLE_PREFIX . '_plugin_preferences');
    }
}

/**
 * Method checks if a table exists in the current database.
 * @param string $tableName
 * @return bool
 * @throws Exception
 */
function tableExistsPIM(string $tableName): bool
{
    global $gDb;

    $tableExists = false;

    $sql = 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?';
    $statement = $gDb->queryPrepared($sql, array(DB_NAME, $tableName));
    if ($statement->fetchColumn() > 0) {
        $tableExists = true;
    }

    return $tableExists;

}

/**
 * Method checks if a column exists in a table in the current database.
 * @param string $tableName
 * @param string $columnName
 * @return bool
 * @throws Exception
 */
function columnExistsPIM(string $tableName, string $columnName): bool
{
    global $gDb;

    $columnExists = false;

    $sql = 'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?';
    $statement = $gDb->queryPrepared($sql, array(DB_NAME, $tableName, $columnName));
    if ($statement->fetchColumn() > 0) {
        $columnExists = true;
    }

    return $columnExists;
}

/**
 * Check if the user is authorized to access the plugin
 *
 * @param string $scriptName The script name of the plugin
 * @return bool true if the user is authorized
 * @throws SmartyException
 * @throws AdmException
 */
function isUserAuthorizedForPIM(string $scriptName): bool
{
    global $gMessage, $gL10n, $gDb, $gCurrentUser;
    $gCurrentUser = $GLOBALS['gCurrentUser'];

    $userIsAuthorized = false;
    $menId = getMenuIdByScriptNamePIM($scriptName);

    if ($menId === null) {
        $gMessage->show($gL10n->get('PLG_INVENTORY_MANAGER_MENU_URL_ERROR', array($scriptName)), $gL10n->get('SYS_ERROR'));
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
 * Check if the user is authorized to access the Preferences module
 *
 * @return bool true if the user is authorized
 */
function isUserAuthorizedForPreferencesPIM(): bool
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
 *
 * @return bool true if the user is authorized
 * @throws AdmException
 */
function isUserAuthorizedForAddinPIM(): bool
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
            if ((int)$node['men_com_id'] === 0 || Component::isVisible($node['com_name_intern'])) {
                if ($node['men_url'] === '/adm_plugins/InventoryManager/inventory_manager.php' && $menuNodes->menuItemIsVisible($node['men_id'])) {
                    return true;
                }
            }
        }
    }
    return false;
}

/**
 * Check if the keeper is authorized to edit spezific item data
 *
 * @param int|null $keeper The user ID of the keeper
 * @return bool true if the keeper is authorized
 */
function isKeeperAuthorizedToEdit(?int $keeper = null): bool
{
    global $pPreferences, $gCurrentUser;
    $gCurrentUser = $GLOBALS['gCurrentUser'];

    if ($pPreferences->config['Optionen']['allow_keeper_edit'] === 1) {
        if (isset($keeper) && $keeper === $gCurrentUser->getValue('usr_id')) {
            return true;
        }
    }

    return false;
}

/**
 * Get menu ID by script name
 *
 * @param string $scriptName The script name of the plugin
 * @return int|null The menu ID or null if not found
 * @throws Exception
 */
function getMenuIdByScriptNamePIM(string $scriptName): ?int
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
 * Translate field name according to naming conventions
 *
 * @param string $field_name field name to translate
 * @return string translated field name
 * @throws Exception
 */
function convlanguagePIM(string $field_name): string
{
    global $gL10n;

    return (substr($field_name, 3, 1) === '_') ? $gL10n->get($field_name) : $field_name;
}

/**
 * Generate a new internal name
 *
 * @param string $name name to generate internal name from
 * @param int $index index to append to the internal name
 * @return string new internal name
 * @throws Exception
 */
function getNewNameInternPIM(string $name, int $index): string
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
 *
 * @return int new sequence number
 * @throws Exception
 */
function genNewSequencePIM(): int
{
    global $gDb, $gCurrentOrgId;

    $sql = 'SELECT max(imf_sequence) as max_sequence FROM ' . TBL_INVENTORY_MANAGER_FIELDS . ' WHERE (imf_org_id = ? OR imf_org_id IS NULL);';
    $statement = $gDb->queryPrepared($sql, array($gCurrentOrgId));
    $row = $statement->fetch();

    return $row['max_sequence'] + 1;
}

/**
 * Replace umlauts in the text
 *
 * @param string $tmpText text to replace umlauts in
 * @return string                    text with replaced umlauts
 */
function umlautePIM(string $tmpText): string
{
    $replacements = array(
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
    );

    return str_replace(array_keys($replacements), array_values($replacements), htmlentities($tmpText));
}

/**
 * Generate HTML for a preference panel
 *
 * @param string $group group the preference panel belongs to
 * @param string $id unique ID of the preference panel
 * @param string $title title of the preference panel
 * @param string $icon icon of the preference panel
 * @param string $body body of the preference panel
 * @return string HTML for the preference panel
 */
function getPreferencePanelPIM(string $group, string $id, string $title, string $icon, string $body): string
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

/**
 * Get all users with their id, name, and address
 *
 * @return string SQL query to get all users with their ID and name
 */
function getSqlOrganizationsUsersCompletePIM(): string
{
    global $gProfileFields, $gCurrentOrgId;

    return 'SELECT usr_id, CONCAT(last_name.usd_value, \', \', first_name.usd_value, COALESCE(CONCAT(\', \', postcode.usd_value),\'\'), COALESCE(CONCAT(\' \', city.usd_value),\'\'), COALESCE(CONCAT(\', \', street.usd_value),\'\') ) as name
            FROM ' . TBL_USERS . '
            JOIN ' . TBL_USER_DATA . ' as last_name ON last_name.usd_usr_id = usr_id AND last_name.usd_usf_id = ' . $gProfileFields->getProperty('LAST_NAME', 'usf_id') . '
            JOIN ' . TBL_USER_DATA . ' as first_name ON first_name.usd_usr_id = usr_id AND first_name.usd_usf_id = ' . $gProfileFields->getProperty('FIRST_NAME', 'usf_id') . '
            LEFT JOIN ' . TBL_USER_DATA . ' as postcode ON postcode.usd_usr_id = usr_id AND postcode.usd_usf_id = ' . $gProfileFields->getProperty('POSTCODE', 'usf_id') . '
            LEFT JOIN ' . TBL_USER_DATA . ' as city ON city.usd_usr_id = usr_id AND city.usd_usf_id = ' . $gProfileFields->getProperty('CITY', 'usf_id') . '
            LEFT JOIN ' . TBL_USER_DATA . ' as street ON street.usd_usr_id = usr_id AND street.usd_usf_id = ' . $gProfileFields->getProperty('ADDRESS', 'usf_id') . '
            WHERE usr_valid = true AND EXISTS (SELECT 1 FROM ' . TBL_MEMBERS . ', ' . TBL_ROLES . ', ' . TBL_CATEGORIES . ' WHERE mem_usr_id = usr_id AND mem_rol_id = rol_id AND mem_begin <= \'' . DATE_NOW . '\' AND mem_end > \'' . DATE_NOW . '\' AND rol_valid = true AND rol_cat_id = cat_id AND (cat_org_id = ' . $gCurrentOrgId . ' OR cat_org_id IS NULL)) ORDER BY last_name.usd_value, first_name.usd_value;';
}

/**
 * Get all users with their id and name
 *
 * @return string SQL query to get all users with their ID and name
 */
function getSqlOrganizationsUsersShortPIM(): string
{
    global $gProfileFields, $gCurrentOrgId;

    return 'SELECT usr_id, CONCAT(last_name.usd_value, \', \', first_name.usd_value) as name
            FROM ' . TBL_USERS . '
            JOIN ' . TBL_USER_DATA . ' as last_name ON last_name.usd_usr_id = usr_id AND last_name.usd_usf_id = ' . $gProfileFields->getProperty('LAST_NAME', 'usf_id') . '
            JOIN ' . TBL_USER_DATA . ' as first_name ON first_name.usd_usr_id = usr_id AND first_name.usd_usf_id = ' . $gProfileFields->getProperty('FIRST_NAME', 'usf_id') . '
            WHERE usr_valid = true AND EXISTS (SELECT 1 FROM ' . TBL_MEMBERS . ', ' . TBL_ROLES . ', ' . TBL_CATEGORIES . ' WHERE mem_usr_id = usr_id AND mem_rol_id = rol_id AND mem_begin <= \'' . DATE_NOW . '\' AND mem_end > \'' . DATE_NOW . '\' AND rol_valid = true AND rol_cat_id = cat_id AND (cat_org_id = ' . $gCurrentOrgId . ' OR cat_org_id IS NULL)) ORDER BY last_name.usd_value, first_name.usd_value;';
}

/**
 * Formats the spreadsheet
 *
 * @param Spreadsheet $spreadsheet
 * @param array $data
 * @param bool $containsHeadline
 * @throws \PhpOffice\PhpSpreadsheet\Exception
 */
function formatSpreadsheet(Spreadsheet $spreadsheet, array $data, bool $containsHeadline): void
{
    $alphabet = range('A', 'Z');
    $column = $alphabet[count($data[0]) - 1];

    if ($containsHeadline) {
        $spreadsheet
            ->getActiveSheet()
            ->getStyle('A1:' . $column . '1')
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('ffdddddd');
        $spreadsheet
            ->getActiveSheet()
            ->getStyle('A1:' . $column . '1')
            ->getFont()
            ->setBold(true);
    }

    for ($number = 0; $number < count($data[0]); $number++) {
        $spreadsheet->getActiveSheet()->getColumnDimension($alphabet[$number])->setAutoSize(true);
    }
    $spreadsheet->getDefaultStyle()->getAlignment()->setWrapText(true);
}
