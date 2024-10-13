<?php
/**
 ***********************************************************************************************
 * Version file for the Admidio plugin InventoryManager
 * 
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

class CPluginInfoPIM {
    protected const PLUGIN_VERSION = '1.0.0';
    protected const PLUGIN_STAND = '13.10.2024';

    /**
     * Current version of plugin InventoryManager
     * @return string
     */    
    public static function getPluginVersion() {
        return self::PLUGIN_VERSION;
    }

    /**
     * Current stand of plugin InventoryManager
     * @return string
     */
    public static function getPluginStand() {
        return self::PLUGIN_STAND;
    }
}