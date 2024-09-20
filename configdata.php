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
 * Mittels dieser Zeichenkombination  DB_TOKEN werden Konfigurationsdaten, die zur Laufzeit als Array verwaltet werden,
 * zu einem String zusammengefasst und in der Admidiodatenbank gespeichert. 
 * Muss die vorgegebene Zeichenkombination (#_#) jedoch ebenfalls, z.B. in der Beschreibung 
 * einer Konfiguration, verwendet werden, so kann das Plugin gespeicherte Konfigurationsdaten 
 * nicht mehr richtig einlesen. In diesem Fall ist die vorgegebene Zeichenkombination abzuaendern (z.B. in !-!)
 * 
 * Achtung: Vor einer Aenderung muss eine Deinstallation durchgefuehrt werden!
 * Bereits gespeicherte Werte in der Datenbank koennen nach einer Aenderung nicht mehr eingelesen werden!
 ***********************************************************************************************
 */

class CConfigDataPIM {
    /**
    * default configuration data for plugin InventoryManager
    */
    const CONFIG_DEFAULT =
    [
        'Optionen' =>
        [
            'interface_pff' => 0,
            'profile_addin' => '',
            'file_name' => 'InventoryManager',
            'add_date' => 0,
        ],
        'Plugininformationen' =>
        [
            'version' => '',
            'stand' => '',
        ],
        'access' =>
        [
            'preferences' => []
        ]
    ];

    /**
    * database token for plugin InventoryManager
    */
    const DB_TOKEN = '#_#';

    /**
    * database token for plugin FormFiller
    */ 
    const DB_TOKEN_FORMFILLER = '#!#';
}