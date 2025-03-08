<?php
/**
 ***********************************************************************************************
 * Class to manage the configuration table "[admidio-praefix]_plugin_preferences" of Plugin InventoryManager
 *
 * @see         https://github.com/MightyMCoder/InventoryManager/ The InventoryManager GitHub project
 * @author      MightyMCoder
 * @copyright   2024 - today MightyMCoder
 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 only
 *
 *
 * Methods:
 * __construct()                        : constructor
 * init()                               : used to check if the configuration table exists, if not creates it and sets default values
 * createTablesIfNotExist()             : used to create the necessary tables if they do not exist
 * createTableIfNotExist($tableName,
 *        $tableDefinition)             : used to create a table if it does not exist
 * initializeDefaultFieldsByOrgId()     : used to initialize default fields in the inventory manager database
 * createField($name, $internalName, $type, $description,
 *        $sequence, $system, $mandatory,
 *        $valueList = '')              : used to create a field in the inventory manager database
 * initializePreferencesByOrgId()       : used to initialize preferences for the inventory manager
 * write()                              : used to write the configuration data to database
 * read()                               : used to read the configuration data from database
 * readConfigData($pluginShortcut,
 *        &$configArray)                : used to read the configuration data of a plugin from the database
 * checkForUpdate()                     : used to compare version and stand of file "/../version.php" with data from database
 * compareVersion()                     : used to compare plugin version with the current version from the database
 * compareStand()                       : used to compare plugin stand with the current stand from the database
 * checkDefaultFieldsForCurrentOrg()    : used to check if there are default fields for the current organization
 * deleteConfigData($deinstOrgSelect)   : used to delete configuration data in database
 * deleteItemData($deinstOrgSelect)     : used to delete item data in database
 ***********************************************************************************************
 */

class CConfigTablePIM
{
    const TABLE_DEFINITION_MYSQL_INVENTORY_MANAGER_FIELDS = '
            imf_id int(10) unsigned NOT NULL AUTO_INCREMENT,
            imf_org_id int(10) unsigned NOT NULL,
            imf_type varchar(30) NOT NULL,
            imf_name varchar(100) NOT NULL,
            imf_name_intern varchar(110) NOT NULL,
            imf_sequence int(10) unsigned NOT NULL,
            imf_system boolean NOT NULL DEFAULT \'0\',	
            imf_mandatory boolean NOT NULL DEFAULT \'0\',	
            imf_description text NOT NULL DEFAULT \'\',
            imf_value_list text,
            imf_usr_id_create int(10) unsigned DEFAULT NULL,
            imf_timestamp_create timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            imf_usr_id_change int(10) unsigned DEFAULT NULL,
            imf_timestamp_change timestamp NULL DEFAULT NULL,
            PRIMARY KEY (imf_id)
        ';
    const TABLE_DEFINITION_POSTGRESQL_INVENTORY_MANAGER_FIELDS = '
            imf_id SERIAL PRIMARY KEY,
            imf_org_id INTEGER NOT NULL,
            imf_type VARCHAR(30) NOT NULL,
            imf_name VARCHAR(100) NOT NULL,
            imf_name_intern VARCHAR(110) NOT NULL,
            imf_sequence INTEGER NOT NULL,
            imf_system BOOLEAN NOT NULL DEFAULT FALSE,
            imf_mandatory BOOLEAN NOT NULL DEFAULT FALSE,
            imf_description TEXT NOT NULL DEFAULT \'\',
            imf_value_list TEXT,
            imf_usr_id_create INTEGER DEFAULT NULL,
            imf_timestamp_create TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            imf_usr_id_change INTEGER DEFAULT NULL,
            imf_timestamp_change TIMESTAMP DEFAULT NULL
        ';
    const TABLE_DEFINITION_MYSQL_INVENTORY_MANAGER_DATA = '
            imd_id int(10) unsigned NOT NULL AUTO_INCREMENT,
            imd_imf_id int(10) unsigned NOT NULL,
            imd_imi_id int(10) unsigned NOT NULL,
            imd_value varchar(4000) NOT NULL DEFAULT \'\',
            PRIMARY KEY (imd_id)
        ';
    const TABLE_DEFINITION_POSTGRESQL_INVENTORY_MANAGER_DATA = '
            imd_id SERIAL PRIMARY KEY,
            imd_imf_id INTEGER NOT NULL,
            imd_imi_id INTEGER NOT NULL,
            imd_value VARCHAR(4000) NOT NULL DEFAULT \'\'
        ';
    const TABLE_DEFINITION_MYSQL_INVENTORY_MANAGER_ITEMS = '
            imi_id int(10) unsigned NOT NULL AUTO_INCREMENT,
            imi_org_id int(10) unsigned NOT NULL,
            imi_former boolean DEFAULT 0,
            imi_usr_id_create int(10) unsigned DEFAULT NULL,
            imi_timestamp_create timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            imi_usr_id_change int(10) unsigned DEFAULT NULL,
            imi_timestamp_change timestamp NULL DEFAULT NULL,
            PRIMARY KEY (imi_id)
        ';
    const TABLE_DEFINITION_POSTGRESQL_INVENTORY_MANAGER_ITEMS = '
            imi_id SERIAL PRIMARY KEY,
            imi_org_id INTEGER NOT NULL,
            imi_former BOOLEAN DEFAULT FALSE,
            imi_usr_id_create INTEGER DEFAULT NULL,
            imi_timestamp_create TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            imi_usr_id_change INTEGER DEFAULT NULL,
            imi_timestamp_change TIMESTAMP DEFAULT NULL
        ';
    const TABLE_DEFINITION_MYSQL_INVENTORY_MANAGER_LOG = '
            iml_id int(10) unsigned NOT NULL AUTO_INCREMENT,
            iml_imi_id int(10) unsigned NOT NULL,
            iml_imf_id int(10) unsigned NOT NULL,
            iml_value_old varchar(4000) NOT NULL DEFAULT \'\',
            iml_value_new varchar(4000) NOT NULL DEFAULT \'\',
            iml_usr_id_create int(10) unsigned DEFAULT NULL,
            iml_timestamp_create timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            iml_comment varchar(255) NOT NULL DEFAULT \'\',
            PRIMARY KEY (iml_id)
        ';
    const TABLE_DEFINITION_POSTGRESQL_INVENTORY_MANAGER_LOG = '
            iml_id SERIAL PRIMARY KEY,
            iml_imi_id INTEGER NOT NULL,
            iml_imf_id INTEGER NOT NULL,
            iml_value_old VARCHAR(4000) NOT NULL DEFAULT \'\',
            iml_value_new VARCHAR(4000) NOT NULL DEFAULT \'\',
            iml_usr_id_create INTEGER DEFAULT NULL,
            iml_timestamp_create TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            iml_comment VARCHAR(255) NOT NULL DEFAULT \'\'
        ';
    const TABLE_DEFINITION_MYSQL_TABLE_PREFERENCES_NAME = '
            plp_id integer unsigned NOT NULL AUTO_INCREMENT,
            plp_org_id integer unsigned NOT NULL,
            plp_name varchar(255) NOT NULL,
            plp_value text, 
            PRIMARY KEY (plp_id)
        ';
    const TABLE_DEFINITION_POSTGRESQL_TABLE_PREFERENCES_NAME = '
            plp_id SERIAL PRIMARY KEY,
            plp_org_id INTEGER NOT NULL,
            plp_name VARCHAR(255) NOT NULL,
            plp_value TEXT
        ';

    public $config = array();               // array with configuration-data

    private $table_preferences_name;        // db table name *_plugin_preferences

    private const SHORTCUT = 'PIM';         // praefix for (P)lugin(I)nventory(M)anager preferences

    /**
     * CConfigTablePIM constructor
     */
    public function __construct()
    {
        require_once(__DIR__ . '/../version.php');
        require_once(__DIR__ . '/configdata.php');

        $this->table_preferences_name = TABLE_PREFIX . '_plugin_preferences';
    }

    /**
     * checks if the configuration table exists, if necessarry creats it and fills it with default configuration data
     *
     * @return void
     */
    public function init(): void
    {
        $this->createTablesIfNotExist();
        $this->initializeDefaultFieldsByOrgId();
        $this->initializePreferencesByOrgId();
    }

    /**
     * Creates the necessary tables if they do not exist
     *
     * @return void
     */
    private function createTablesIfNotExist(): void
    {
        global $gDbType;

        switch ($gDbType) {
            case 'pgsql':
                $this->createTableIfNotExist(TBL_INVENTORY_MANAGER_FIELDS, self::TABLE_DEFINITION_POSTGRESQL_INVENTORY_MANAGER_FIELDS);
                $this->createTableIfNotExist(TBL_INVENTORY_MANAGER_DATA, self::TABLE_DEFINITION_POSTGRESQL_INVENTORY_MANAGER_DATA);
                $this->createTableIfNotExist(TBL_INVENTORY_MANAGER_ITEMS, self::TABLE_DEFINITION_POSTGRESQL_INVENTORY_MANAGER_ITEMS);
                $this->createTableIfNotExist(TBL_INVENTORY_MANAGER_LOG, self::TABLE_DEFINITION_POSTGRESQL_INVENTORY_MANAGER_LOG);
                $this->createTableIfNotExist($this->table_preferences_name, self::TABLE_DEFINITION_POSTGRESQL_TABLE_PREFERENCES_NAME);
                break;
            case 'mysql':
            default:
                $this->createTableIfNotExist(TBL_INVENTORY_MANAGER_FIELDS, self::TABLE_DEFINITION_MYSQL_INVENTORY_MANAGER_FIELDS);
                $this->createTableIfNotExist(TBL_INVENTORY_MANAGER_DATA, self::TABLE_DEFINITION_MYSQL_INVENTORY_MANAGER_DATA);
                $this->createTableIfNotExist(TBL_INVENTORY_MANAGER_ITEMS, self::TABLE_DEFINITION_MYSQL_INVENTORY_MANAGER_ITEMS);
                $this->createTableIfNotExist(TBL_INVENTORY_MANAGER_LOG, self::TABLE_DEFINITION_MYSQL_INVENTORY_MANAGER_LOG);
                $this->createTableIfNotExist($this->table_preferences_name, self::TABLE_DEFINITION_MYSQL_TABLE_PREFERENCES_NAME);
        }
    }

    /**
     * Creates a table if it does not exist
     *
     * @param string $tableName The name of the table
     * @param string $tableDefinition The SQL definition of the table
     * @return void
     */
    private function createTableIfNotExist($tableName, $tableDefinition): void
    {
        global $gDb, $gDbType;

        $sql = 'SELECT table_name FROM information_schema.tables WHERE table_name = \'' . $tableName . '\';';
        $statement = $gDb->query($sql);

        if (!$statement->rowCount()) {
            switch ($gDbType) {
                case 'pgsql':
                    $sql = 'CREATE TABLE ' . $tableName . ' (' . $tableDefinition . ');';
                    break;
                case 'mysql':
                default:
                    $sql = 'CREATE TABLE ' . $tableName . ' (' . $tableDefinition . ') ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;';
            }
            $gDb->query($sql);
        }
    }

    /**
     * Initializes default fields in the inventory manager database
     *
     * @return void
     */
    private function initializeDefaultFieldsByOrgId(): void
    {
        global $gDb, $gCurrentOrgId;

        $defaultData = array(
            array('imf_id' => 1, 'imf_name' => 'PIM_ITEMNAME', 'imf_name_intern' => 'ITEMNAME', 'imf_type' => 'TEXT', 'imf_description' => convlanguagePIM('PIM_ITEMNAME_DESCRIPTION'), 'imf_sequence' => 0, 'imf_system' => 1, 'imf_mandatory' => 1),
            array('imf_id' => 2, 'imf_name' => 'PIM_CATEGORY', 'imf_name_intern' => 'CATEGORY', 'imf_type' => 'DROPDOWN', 'imf_description' => convlanguagePIM('PIM_CATEGORY_DESCRIPTION'), 'imf_sequence' => 1, 'imf_system' => 1, 'imf_mandatory' => 1, 'imf_value_list' => 'Allgemein'),
            array('imf_id' => 3, 'imf_name' => 'PIM_KEEPER', 'imf_name_intern' => 'KEEPER', 'imf_type' => 'TEXT', 'imf_description' => convlanguagePIM('PIM_KEEPER_DESCRIPTION'), 'imf_sequence' => 2, 'imf_system' => 1, 'imf_mandatory' => 0),
            array('imf_id' => 4, 'imf_name' => 'PIM_IN_INVENTORY', 'imf_name_intern' => 'IN_INVENTORY', 'imf_type' => 'CHECKBOX', 'imf_description' => convlanguagePIM('PIM_IN_INVENTORY_DESCRIPTION'), 'imf_sequence' => 3, 'imf_system' => 1, 'imf_mandatory' => 0),
            array('imf_id' => 5, 'imf_name' => 'PIM_LAST_RECEIVER', 'imf_name_intern' => 'LAST_RECEIVER', 'imf_type' => 'TEXT', 'imf_description' => convlanguagePIM('PIM_LAST_RECEIVER_DESCRIPTION'), 'imf_sequence' => 4, 'imf_system' => 1, 'imf_mandatory' => 0),
            array('imf_id' => 6, 'imf_name' => 'PIM_RECEIVED_ON', 'imf_name_intern' => 'RECEIVED_ON', 'imf_type' => 'DATE', 'imf_description' => convlanguagePIM('PIM_RECEIVED_ON_DESCRIPTION'), 'imf_sequence' => 5, 'imf_system' => 1, 'imf_mandatory' => 0),
            array('imf_id' => 7, 'imf_name' => 'PIM_RECEIVED_BACK_ON', 'imf_name_intern' => 'RECEIVED_BACK_ON', 'imf_type' => 'DATE', 'imf_description' => convlanguagePIM('PIM_RECEIVED_BACK_ON_DESCRIPTION'), 'imf_sequence' => 6, 'imf_system' => 1, 'imf_mandatory' => 0)
        );

        $sql = 'SELECT imf_id, imf_name, imf_name_intern, imf_type, imf_description, imf_sequence, imf_system, imf_mandatory, imf_value_list FROM ' . TBL_INVENTORY_MANAGER_FIELDS . ' WHERE imf_org_id = \'' . $gCurrentOrgId . '\';';
        $statement = $gDb->query($sql);

        $existingFields = array();
        while ($row = $statement->fetch()) {
            $existingFields[$row['imf_name']] = $row;
        }

        $defaultFieldNames = array_column($defaultData, 'imf_name');

        $pimFields = array();
        $customFields = array();

        // Sort the array by imf_sequence to get the imf_name and imf_sequence for custom fields of the previous field
        $existingFieldsOrdered = $existingFields;
        usort($existingFieldsOrdered, function ($a, $b) {
            return $a['imf_sequence'] <=> $b['imf_sequence'];
        });

        foreach ($existingFields as $fieldName => $fieldData) {
            if (strpos($fieldName, 'PIM_') === 0) {
                $pimFields[$fieldName] = $fieldData;
            } else {
                $customFields[$fieldName] = $fieldData;
                $customFields[$fieldName]['previous_imf_name'] = $existingFieldsOrdered[$fieldData['imf_sequence'] - 1]['imf_name'];
                $customFields[$fieldName]['previous_sequence'] = $existingFieldsOrdered[$fieldData['imf_sequence'] - 1]['imf_sequence'];
            }
        }

        // Adjust pimFields to match the defaultData
        foreach ($defaultData as $defaultField) {
            $defaultFieldName = $defaultField['imf_name'];
            if (!isset($pimFields[$defaultFieldName])) {
                // Field does not exist, add it to pimFields
                $pimFields[$defaultFieldName] = $defaultField;
            } else {
                foreach ($defaultField as $key => $value) {
                    if ($key === 'imf_value_list') {
                        continue;
                    }
                    $pimFields[$defaultFieldName][$key] = $value;
                }
            }
        }

        // Sort the array by imd_id but keep the name as imf_name
        usort($pimFields, function ($a, $b) {
            return $a['imf_id'] <=> $b['imf_id'];
        });

        $pimFields = array_combine(array_column($pimFields, 'imf_name'), $pimFields);

        // Remove PIM fields that are not in the defaultData
        foreach ($pimFields as $fieldName => $fieldData) {
            if (!in_array($fieldName, $defaultFieldNames)) {
                unset($pimFields[$fieldName]);
            }
        }

        // Append customFields to pimFields
        $allFields = $pimFields;

        // Adjust the sequence of pimFields based on customFields
        foreach ($customFields as $customField) {
            if (isset($customField['previous_imf_name']) && isset($allFields[$customField['previous_imf_name']])) {
                $customSequence = $allFields[$customField['previous_imf_name']]['imf_sequence'] + 1;
                $customField['imf_sequence'] = $customSequence;
            } else {
                //old previous item doesnt exist anymore
                $customSequence = $customField['previous_sequence'] - 1;
            }

            foreach ($allFields as &$pimField) {
                if ($pimField['imf_sequence'] >= $customSequence) {
                    $pimField['imf_sequence']++;
                }
            }

            unset($pimField); // Break reference to the last element

            if (isset($customField['imf_name'])) {
                $allFields[$customField['imf_name']] = $customField;
            }
        }
        // Now $allFields contains the combined and updated fields

        // Clear the table and reset the AUTO_INCREMENT
        //$sql = 'TRUNCATE TABLE ' . TBL_INVENTORY_MANAGER_FIELDS;
        $sql = 'DELETE FROM ' . TBL_INVENTORY_MANAGER_FIELDS . ' WHERE imf_org_id = ?;';
        $gDb->queryPrepared($sql, array($gCurrentOrgId));

        // Insert the new array into the database and keep track of new IDs
        $newFieldIds = array();
        foreach ($allFields as $field) {
            $this->createField(
                $field['imf_name'],
                $field['imf_name_intern'],
                $field['imf_type'],
                $field['imf_description'],
                $field['imf_sequence'],
                $field['imf_system'],
                $field['imf_mandatory'],
                isset($field['imf_value_list']) ? $field['imf_value_list'] : ''
            );

            // Get the new ID of the inserted field
            $newFieldId = $gDb->lastInsertId();
            $newFieldIds[$field['imf_name']] = $newFieldId;
        }

        if (count($existingFields) < count($newFieldIds)) {
            // Sort existing fields by imf_id in descending order
            usort($existingFields, function ($a, $b) {
                return $b['imf_id'] <=> $a['imf_id'];
            });

            // Update the imd_imf_id in TBL_INVENTORY_MANAGER_DATA if the ID has changed
            $existingFields = array_combine(array_column($existingFields, 'imf_name'), $existingFields);
        }

        foreach ($existingFields as $oldField) {
            $oldFieldName = $oldField['imf_name'];
            if (isset($newFieldIds[$oldFieldName])) {
                if ($newFieldIds[$oldFieldName] != $oldField['imf_id']) {
                    $sql = 'UPDATE ' . TBL_INVENTORY_MANAGER_DATA . ' SET imd_imf_id = ? WHERE imd_imf_id = ?';
                    $gDb->queryPrepared($sql, array($newFieldIds[$oldFieldName], $oldField['imf_id']));
                }
            } else {
                // Field no longer exists, set the field to empty and show an error message
                $sql = 'UPDATE ' . TBL_INVENTORY_MANAGER_DATA . ' SET imd_imf_id = NULL WHERE imd_imf_id = ?';
                $gDb->queryPrepared($sql, array($oldField['imf_id']));
                $_SESSION['error_messages'][] = 'Error: Field "' . $oldFieldName . '" no longer exists. Please manually check and adjust the database table"' . TBL_INVENTORY_MANAGER_DATA . '"  where "imd_imf_id" equals "NULL" to avoid data loss.';
            }
        }

        // Display error messages in a browser window if there are any
        if (!empty($_SESSION['error_messages'])) {
            // Prepare error messages for safe output in JavaScript
            $jsErrorMessages = json_encode(implode("\n", $_SESSION['error_messages']));

            echo '<script type="text/javascript">';
            echo 'alert(' . $jsErrorMessages . ');';
            echo '</script>';

            unset($_SESSION['error_messages']);
        }
    }

    /**
     * Creates a field in the inventory manager database
     *
     * @param string $name The name of the field
     * @param string $internalName The internal name of the field
     * @param string $type The type of the field
     * @param string $description The description of the field
     * @param int $sequence The sequence order of the field
     * @param bool $system Whether the field is a system field
     * @param bool $mandatory Whether the field is mandatory
     * @param string $valueList The value list for dropdown fields
     * @return void
     */
    private function createField($name, $internalName, $type, $description, $sequence, $system, $mandatory, $valueList = ''): void
    {
        global $gDb, $gCurrentOrgId;

        $itemField = new TableAccess($gDb, TBL_INVENTORY_MANAGER_FIELDS, 'imf');
        $itemField->setValue('imf_org_id', (int)$gCurrentOrgId);
        $itemField->setValue('imf_sequence', $sequence);
        $itemField->setValue('imf_system', $system);
        $itemField->setValue('imf_mandatory', $mandatory);
        $itemField->setValue('imf_name', $name);
        $itemField->setValue('imf_name_intern', $internalName);
        $itemField->setValue('imf_type', $type);
        $itemField->setValue('imf_description', $description);
        $itemField->setValue('imf_value_list', $valueList);
        $itemField->save();
    }

    /**
     * Initializes preferences for the inventory manager
     *
     * @return void
     */
    private function initializePreferencesByOrgId(): void
    {
        global $gDb, $gCurrentOrgId;

        $this->read();

        $this->config['Plugininformationen']['version'] = CPluginInfoPIM::getPluginVersion();
        $this->config['Plugininformationen']['beta-version'] = CPluginInfoPIM::getPluginBetaVersion();
        $this->config['Plugininformationen']['stand'] = CPluginInfoPIM::getPluginStand();

        $configCurrent = $this->config;

        foreach (CConfigDataPIM::CONFIG_DEFAULT as $section => $sectiondata) {
            foreach ($sectiondata as $item => $value) {
                if (isset($configCurrent[$section][$item])) {
                    unset($configCurrent[$section][$item]);
                } else {
                    $this->config[$section][$item] = $value;
                }
            }
            if ((isset($configCurrent[$section]) && count($configCurrent[$section]) == 0)) {
                unset($configCurrent[$section]);
            }
        }

        foreach ($configCurrent as $section => $sectiondata) {
            foreach ($sectiondata as $item => $value) {
                $plp_name = self::SHORTCUT . '__' . $section . '__' . $item;
                $sql = 'DELETE FROM ' . $this->table_preferences_name . ' WHERE plp_name = ? AND plp_org_id = ?;';
                $gDb->queryPrepared($sql, array($plp_name, $gCurrentOrgId));
                unset($this->config[$section][$item]);
            }
            if (count($this->config[$section]) == 0) {
                unset($this->config[$section]);
            }
        }

        $this->write();
    }

    /**
     * Writes the configuration data of plugin InventoryManager to the database
     *
     * @return void
     */
    public function write(): void
    {
        global $gDb, $gCurrentOrgId;

        foreach ($this->config as $section => $sectionData) {
            foreach ($sectionData as $item => $value) {
                if (is_array($value)) {
                    // Data is enclosed in double brackets to mark this record as an array in the database
                    $value = '((' . implode(CConfigDataPIM::DB_TOKEN, $value) . '))';
                }

                $plpName = self::SHORTCUT . '__' . $section . '__' . $item;

                $sql = 'SELECT plp_id FROM ' . $this->table_preferences_name . ' WHERE plp_name = ? AND (plp_org_id = ? OR plp_org_id IS NULL);';
                $statement = $gDb->queryPrepared($sql, array($plpName, $gCurrentOrgId));
                $row = $statement->fetchObject();

                if (isset($row->plp_id) && strlen($row->plp_id) > 0) {
                    // Record exists, update it
                    $sql = 'UPDATE ' . $this->table_preferences_name . ' SET plp_value = ? WHERE plp_id = ?;';
                    $gDb->queryPrepared($sql, array($value, $row->plp_id));
                } else {
                    // Record does not exist, insert it
                    $sql = 'INSERT INTO ' . $this->table_preferences_name . ' (plp_org_id, plp_name, plp_value) VALUES (?, ?, ?);';
                    $gDb->queryPrepared($sql, array($gCurrentOrgId, $plpName, $value));
                }
            }
        }
    }

    /**
     * Reads the configuration data of plugin InventoryManager from the database
     *
     * @return bool
     */
    public function read(): bool
    {
        return $this->readConfigData(self::SHORTCUT, $this->config);
    }

    /**
     * Reads the configuration data of a plugin from the database
     *
     * @param string $pluginShortcut The shortcut of the plugin
     * @param array &$configArray The array to store the configuration data
     * @return bool
     */
    private function readConfigData($pluginShortcut, &$configArray): bool
    {
        global $gDb, $gCurrentOrgId;

        // Check if table *_plugin_preferences exists
        $sql = 'SELECT table_name FROM information_schema.tables WHERE table_name = \'' . $this->table_preferences_name . '\';';
        $tablePreferencesExistStatement = $gDb->queryPrepared($sql);

        if ($tablePreferencesExistStatement->rowCount() === 0) {
            return false;
        }

        $sql = 'SELECT plp_id, plp_name, plp_value FROM ' . $this->table_preferences_name . ' WHERE plp_name LIKE ? AND (plp_org_id = ? OR plp_org_id IS NULL);';
        $statement = $gDb->queryPrepared($sql, array($pluginShortcut . '__%', $gCurrentOrgId));

        while ($row = $statement->fetch()) {
            $array = explode('__', $row['plp_name']);

            // if plp_value is enclosed in ((  )) -> array
            if ((substr($row['plp_value'], 0, 2) == '((') && (substr($row['plp_value'], -2) == '))')) {
                $row['plp_value'] = substr($row['plp_value'], 2, -2);
                $configArray[$array[1]] [$array[2]] = explode(CConfigDataPIM::DB_TOKEN, $row['plp_value']);
            } else {
                if (is_numeric($row['plp_value'])) {
                    if (strpos($row['plp_value'], '.') !== false) {
                        $configArray[$array[1]] [$array[2]] = (float)$row['plp_value'];
                    } else {
                        $configArray[$array[1]] [$array[2]] = (int)$row['plp_value'];
                    }
                } else {
                    $configArray[$array[1]] [$array[2]] = $row['plp_value'];
                }
            }
        }
        return true;
    }

    /**
     * Compare plugin version and stand with current version and stand from database
     *
     * @return bool
     */
    public function checkForUpdate(): bool
    {
        global $gDb;

        $needsUpdate = false;

        // Check if table *_plugin_preferences exists
        $sql = 'SELECT table_name FROM information_schema.tables WHERE table_name = \'' . $this->table_preferences_name . '\';';
        $tablePreferencesExistStatement = $gDb->queryPrepared($sql);

        $sql = 'SELECT table_name FROM information_schema.tables WHERE table_name = \'' . TBL_INVENTORY_MANAGER_FIELDS . '\';';
        $tableFieldsExistStatement = $gDb->queryPrepared($sql);

        if ($tablePreferencesExistStatement->rowCount() && $tableFieldsExistStatement->rowCount()) {
            $needsUpdate = $this->checkDefaultFieldsForCurrentOrg() || $this->compareVersion() || $this->compareStand();
        } else {
            // Update needed because it is not installed yet
            $needsUpdate = true;
        }

        return $needsUpdate;
    }

    /**
     * Compare plugin version with the current version from the database
     *
     * @return bool
     */
    private function compareVersion(): bool
    {
        global $gDb, $gCurrentOrgId;

        $plp_name = self::SHORTCUT . '__Plugininformationen__version';

        $sql = 'SELECT plp_value FROM ' . $this->table_preferences_name . ' WHERE plp_name = ? AND (plp_org_id = ? OR plp_org_id IS NULL);';
        $statement = $gDb->queryPrepared($sql, array($plp_name, $gCurrentOrgId));
        $row = $statement->fetchObject();

        // Compare versions
        return !isset($row->plp_value) || strlen($row->plp_value) === 0 || $row->plp_value !== CPluginInfoPIM::getPluginVersion();
    }

    /**
     * Compare plugin stand with the current stand from the database
     *
     * @return bool
     */
    private function compareStand(): bool
    {
        global $gDb, $gCurrentOrgId;

        $plp_name = self::SHORTCUT . '__Plugininformationen__stand';

        $sql = 'SELECT plp_value FROM ' . $this->table_preferences_name . ' WHERE plp_name = ? AND (plp_org_id = ? OR plp_org_id IS NULL);';
        $statement = $gDb->queryPrepared($sql, array($plp_name, $gCurrentOrgId));
        $row = $statement->fetchObject();

        // Compare stands
        return !isset($row->plp_value) || strlen($row->plp_value) === 0 || $row->plp_value !== CPluginInfoPIM::getPluginStand();
    }

    /**
     * Checks if there are default fields for the current organization.
     *
     * @return bool                    Returns true if there are no default fields for the current organization, false otherwise.
     */
    private function checkDefaultFieldsForCurrentOrg(): bool
    {
        global $gDb, $gCurrentOrgId;

        $sql = 'SELECT imf_name_intern FROM ' . TBL_INVENTORY_MANAGER_FIELDS . ' WHERE imf_org_id = ?;';
        $statement = $gDb->queryPrepared($sql, array($gCurrentOrgId));
        $row = $statement->fetchObject();

        if (!$row) {
            return true;
        }
        return false;
    }

    /**
     * Delete configuration data from the database
     *
     * @param int $deinstOrgSelect 0 = only delete data from current org,
     *                                    1 = delete data from every org
     * @return string                    Result message
     */
    public function deleteConfigData($deinstOrgSelect): string
    {
        global $gDb, $gCurrentOrgId, $gL10n;

        $result = '';
        $sqlWhereCondition = '';

        if ($deinstOrgSelect == 0) {
            $sqlWhereCondition = 'AND plp_org_id = ?';
        }

        $sql = 'DELETE FROM ' . $this->table_preferences_name . ' WHERE plp_name LIKE ? ' . $sqlWhereCondition;
        $params = [self::SHORTCUT . '__%'];
        if ($deinstOrgSelect == 0) {
            $params[] = $gCurrentOrgId;
        }
        $result_data = $gDb->queryPrepared($sql, $params);
        $result .= ($result_data ? $gL10n->get('PLG_INVENTORY_MANAGER_DEINST_DATA_DELETED_IN', array($this->table_preferences_name)) : $gL10n->get('PLG_INVENTORY_MANAGER_DEINST_DATA_DELETED_IN_ERROR', array($this->table_preferences_name)));

        // Check if the table is empty and can be deleted
        $sql = 'SELECT * FROM ' . $this->table_preferences_name;
        $statement = $gDb->queryPrepared($sql);

        if ($statement->rowCount() == 0) {
            $sql = 'DROP TABLE ' . $this->table_preferences_name;
            $result_db = $gDb->queryPrepared($sql);
            $result .= ($result_db ? $gL10n->get('PLG_INVENTORY_MANAGER_DEINST_TABLE_DELETED', array($this->table_preferences_name)) : $gL10n->get('PLG_INVENTORY_MANAGER_DEINST_TABLE_DELETE_ERROR', array($this->table_preferences_name)));
        } else {
            $result .= $gL10n->get('PLG_INVENTORY_MANAGER_DEINST_CONFIGTABLE_DELETE_NOTPOSSIBLE', array($this->table_preferences_name));
        }

        return $result;
    }

    /**
     * Delete the item data from the database
     *
     * @param int $deinstOrgSelect 0 = only delete data from current org, 1 = delete data from every org
     * @return string $result Result message
     */
    public function deleteItemData($deinstOrgSelect): string
    {
        global $gDb, $gCurrentOrgId, $gL10n;
        $result = '';

        if ($deinstOrgSelect == 0) {
            $sql = 'DELETE FROM ' . TBL_INVENTORY_MANAGER_DATA . ' WHERE imd_imi_id IN (SELECT imi_id FROM ' . TBL_INVENTORY_MANAGER_ITEMS . ' WHERE imi_org_id = ?)';
            $result_data = $gDb->queryPrepared($sql, array($gCurrentOrgId));
            $result .= ($result_data ? $gL10n->get('PLG_INVENTORY_MANAGER_DEINST_DATA_DELETED_IN', array(TABLE_PREFIX . '_inventory_manager_data')) : $gL10n->get('PLG_INVENTORY_MANAGER_DEINST_DATA_DELETED_IN_ERROR', array(TABLE_PREFIX . '_inventory_manager_data')));

            $sql = 'DELETE FROM ' . TBL_INVENTORY_MANAGER_LOG . ' WHERE iml_imi_id IN (SELECT imi_id FROM ' . TBL_INVENTORY_MANAGER_ITEMS . ' WHERE imi_org_id = ?)';
            $result_log = $gDb->queryPrepared($sql, array($gCurrentOrgId));
            $result .= ($result_log ? $gL10n->get('PLG_INVENTORY_MANAGER_DEINST_DATA_DELETED_IN', array(TABLE_PREFIX . '_inventory_manager_log')) : $gL10n->get('PLG_INVENTORY_MANAGER_DEINST_DATA_DELETED_IN_ERROR', array(TABLE_PREFIX . '_inventory_manager_log')));

            $sql = 'DELETE FROM ' . TBL_INVENTORY_MANAGER_ITEMS . ' WHERE imi_org_id = ?';
            $result_items = $gDb->queryPrepared($sql, array($gCurrentOrgId));
            $result .= ($result_items ? $gL10n->get('PLG_INVENTORY_MANAGER_DEINST_DATA_DELETED_IN', array(TABLE_PREFIX . '_inventory_manager_items')) : $gL10n->get('PLG_INVENTORY_MANAGER_DEINST_DATA_DELETED_IN_ERROR', array(TABLE_PREFIX . '_inventory_manager_items')));

            $sql = 'DELETE FROM ' . TBL_INVENTORY_MANAGER_FIELDS . ' WHERE imf_org_id = ?';
            $result_fields = $gDb->queryPrepared($sql, array($gCurrentOrgId));
            $result .= ($result_fields ? $gL10n->get('PLG_INVENTORY_MANAGER_DEINST_DATA_DELETED_IN', array(TABLE_PREFIX . '_inventory_manager_fields')) : $gL10n->get('PLG_INVENTORY_MANAGER_DEINST_DATA_DELETED_IN_ERROR', array(TABLE_PREFIX . '_inventory_manager_fields')));
        }

        // Drop tables if they are empty or if data should be deleted from every org
        $table_array = array(
            TBL_INVENTORY_MANAGER_FIELDS,
            TBL_INVENTORY_MANAGER_DATA,
            TBL_INVENTORY_MANAGER_ITEMS,
            TBL_INVENTORY_MANAGER_LOG
        );

        foreach ($table_array as $table_name) {
            $sql = 'SELECT * FROM ' . $table_name;
            $statement = $gDb->queryPrepared($sql);

            if ($statement->rowCount() == 0 || $deinstOrgSelect == 1) {
                $sql = 'DROP TABLE ' . $table_name;
                $result_db = $gDb->queryPrepared($sql);
                $result .= ($result_db ? $gL10n->get('PLG_INVENTORY_MANAGER_DEINST_TABLE_DELETED', array($table_name)) : $gL10n->get('PLG_INVENTORY_MANAGER_DEINST_TABLE_DELETE_ERROR', array($table_name)));
            } else {
                $result .= $gL10n->get('PLG_INVENTORY_MANAGER_DEINST_TABLE_DELETE_NOTPOSSIBLE', array($table_name));
            }
        }

        return $result;
    }
}
