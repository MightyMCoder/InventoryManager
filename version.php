<?php
/**
 ***********************************************************************************************
 * Version file for the Admidio plugin InventoryManager
 * 
 * @see         https://github.com/MightyMCoder/InventoryManager/ The InventoryManager GitHub project
 * @author      MightyMCoder
 * @copyright   2024 - today MightyMCoder
 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 only
 ***********************************************************************************************
 */

class CPluginInfoPIM {
    protected const PLUGIN_VERSION = '1.1.2';
    protected const PLUGIN_VERSION_BETA = 'n/a';
    protected const PLUGIN_STAND = '15.01.2025';

    /**
     * Current version of plugin InventoryManager
     * @return string
     */    
    public static function getPluginVersion() {
        return self::PLUGIN_VERSION;
    }

    /**
     * Current beta version of plugin InventoryManager
     * @return string
     */    
    public static function getPluginBetaVersion() {
        return self::PLUGIN_VERSION_BETA;
    }

    /**
     * Current stand of plugin InventoryManager
     * @return string
     */
    public static function getPluginStand() {
        return self::PLUGIN_STAND;
    }
}
