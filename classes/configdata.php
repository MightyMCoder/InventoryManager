<?php
/**
 ***********************************************************************************************
 * Config data class for the Admidio plugin InventoryManager
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 * 
 * Note:
 * 
 * Using this character combination DB_TOKEN, configuration data managed as an array at runtime
 * is concatenated into a string and stored in the Admidio database. 
 * However, if the predefined character combination (#_#) is also used, for example, in the description 
 * of a configuration, the plugin will no longer be able to read the stored configuration data correctly. 
 * In this case, the predefined character combination must be changed (e.g., to !-!).
 * 
 * Warning: An uninstallation must be performed before making any changes!
 * Already stored values in the database cannot be read after a change!
 ***********************************************************************************************
 */

class CConfigDataPIM
{
    /**
     * Default configuration data for plugin InventoryManager
     */
    const CONFIG_DEFAULT = [
        'Optionen' => [
            'interface_pff' => 0,
            'profile_addin' => '',
            'file_name' => 'InventoryManager',
            'add_date' => 0,
        ],
        'Plugininformationen' => [
            'version' => '',
            'beta-version' => '',
            'stand' => '',
        ],
        'access' => [
            'preferences' => []
        ]
    ];

    /**
     * Database token for plugin InventoryManager
     */
    const DB_TOKEN = '#_#';

    /**
     * Database token for plugin FormFiller
     */
    const DB_TOKEN_FORMFILLER = '#!#';
}
