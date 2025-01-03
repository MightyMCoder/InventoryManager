<?php
/**
 ***********************************************************************************************
 * Class to manage the configuration table "[admidio-praefix]_plugin_preferences" of Plugin InventoryManager
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 * 
 * methods:
 * 
 * isPffInst()							: used to check if plugin FormFiller is installed; if yes returns true otherwise false
 * pffDir()								: used to get the installation directory of the Plugin FormFiller; returns false if it doesn't exists or if it exists multiple times
 * init()								: used to check if the configuration table exists, if not creates it and sets default values
 * write()								: used to write the configuration data to database
 * read()						        : used to read the configuration data from database
 * checkForUpdate()						: used to compare version and stand of file "/../version.php" with data from database
 * deleteConfigData($deinstOrgSelect)	: used to delete configuration data in database
 * deleteItemData($deinstOrgSelect)		: used to delete item data in database
 * 
 ***********************************************************************************************
 */

class CConfigTablePIM
{
	public $config = array();        		// array with configuration-data
	public $configPff = array();     		// array with configuration-data of (P)lugin (f)orm (f)iller

	private $table_name;					// db table name *_plugin_preferences
	private $isPffInst; 					// (is) (P)lugin (f)orm (f)iller (Inst)alled
	private $pffDir;						// (p)lugin (f)orm (f)iller (Dir)ectory 

	private const SHORTCUT = 'PIM';			// praefix for (P)lugin(I)nventory(M)anager preferences

    /**
     * CConfigTablePIM constructor
     */
	public function __construct()
	{
		require_once(__DIR__ . '/../version.php');
		require_once(__DIR__ . '/configdata.php');
		
		$this->table_name = TABLE_PREFIX .'_plugin_preferences';

		$this->findPff();
		$this->checkPffInst();
	}
	
	/**
	 * Checks if the plugin FormFiller is installed
	 * @return void
	 */
	private function checkPffInst()
	{
		// check if configuration table for plugin FormFiller exists
		$sql = 'SHOW TABLES LIKE \''.$this->table_name.'\';';
		$statement = $GLOBALS['gDb']->queryPrepared($sql);
		
		if ($statement->rowCount() !== 0)  
		{
			$sql = 'SELECT COUNT(*) AS COUNT FROM '.$this->table_name.' WHERE plp_name = ? AND (plp_org_id = ? OR plp_org_id IS NULL);';
			$statement = $GLOBALS['gDb']->queryPrepared($sql, array('PFF__Plugininformationen__version', $GLOBALS['gCurrentOrgId']));
			
			$this->isPffInst = ((int) $statement->fetchColumn() === 1 && $this->pffDir !== false);
		}
		else
		{
			$this->isPffInst = false;
		}
	}
	
	/**
	 * If the plugin FormFiller is installed
	 * then this method will return true otherwise false
	 * @return bool Returns @b true if plugin FormFiller is installed
	 */
	public function isPffInst()
	{
		return $this->isPffInst;
	}
	
	/**
	 * Checks if a FormFiller directory exists
	 * @return void
	 */
	private function findPff()
	{
		$location = ADMIDIO_PATH . FOLDER_PLUGINS;
		$searchedFile = 'formfiller.php';
		$formFillerfiles = array();
		$tempFiles = array();
		
		$all = opendir($location);
		while ($found = readdir($all))
		{
			if (is_dir($location.'/'.$found) and $found<> ".." and $found<> ".")
			{
				$tempFiles= glob($location.'/'.$found.'/'. $searchedFile);
				if (count($tempFiles) > 0)
				{
					$formFillerfiles[] = $found;              // only directory is needed
				}
			}
		}
		closedir($all);
		unset($all);
		
		if (count($formFillerfiles) != 1)
		{
			$this->pffDir = false;
		}
		else
		{
			$this->pffDir = $formFillerfiles[0];
		}
	}
	
	/**
	 * Returns the Plugin FormFiller directory
	 * @return bool/string Returns the FormFiller directory otherwise false 
	 */
	public function pffDir()
	{
		return $this->pffDir;
	}
	
    /**
     * checks if the configuration table exists, if necessarry creats it and fills it with default configuration data
     * @return void
     */
	public function init()
	{
		$this->createTablesIfNotExist();
		$this->initializeDefaultFields();
		$this->initializePreferences();
	}

	/**
	 * Creates the necessary tables if they do not exist
	 * @return void
	 */
	private function createTablesIfNotExist()
	{
		$this->createTableIfNotExist(TBL_INVENTORY_MANAGER_FIELDS, '
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
		');

		$this->createTableIfNotExist(TBL_INVENTORY_MANAGER_DATA, '
			imd_id int(10) unsigned NOT NULL AUTO_INCREMENT,
			imd_imf_id int(10) unsigned NOT NULL,
			imd_imi_id int(10) unsigned NOT NULL,
			imd_value varchar(4000),
			PRIMARY KEY (imd_id)
		');

		$this->createTableIfNotExist(TBL_INVENTORY_MANAGER_ITEMS, '
			imi_id int(10) unsigned NOT NULL AUTO_INCREMENT,
			imi_org_id int(10) unsigned NOT NULL,
			imi_former boolean DEFAULT 0,	
			imi_usr_id_create int(10) unsigned DEFAULT NULL,
			imi_timestamp_create timestamp NULL DEFAULT CURRENT_TIMESTAMP,
			imi_usr_id_change int(10) unsigned DEFAULT NULL,
			imi_timestamp_change timestamp NULL DEFAULT NULL,
			PRIMARY KEY (imi_id)
		');

		$this->createTableIfNotExist(TBL_INVENTORY_MANAGER_LOG, '
			iml_id int(10) unsigned NOT NULL AUTO_INCREMENT,
			iml_imi_id int(10) unsigned NOT NULL,	
			iml_imf_id int(10) unsigned NOT NULL,	
			iml_value_old varchar(4000),	
			iml_value_new varchar(4000),	
			iml_usr_id_create int(10) unsigned DEFAULT NULL,
			iml_timestamp_create timestamp NULL DEFAULT CURRENT_TIMESTAMP,	
			iml_comment varchar(255) NULL,	
			PRIMARY KEY (iml_id)
		');

		$this->createTableIfNotExist($this->table_name, '
			plp_id integer unsigned NOT NULL AUTO_INCREMENT,
			plp_org_id integer unsigned NOT NULL,
			plp_name varchar(255) NOT NULL,
			plp_value text, 
			PRIMARY KEY (plp_id)
		');
	}

	/**
	 * Creates a table if it does not exist
	 * @param string $tableName The name of the table
	 * @param string $tableDefinition The SQL definition of the table
	 * @return void
	 */
	private function createTableIfNotExist($tableName, $tableDefinition)
	{
		$sql = 'SHOW TABLES LIKE \'' . $tableName . '\';';
		$statement = $GLOBALS['gDb']->query($sql);

		if (!$statement->rowCount()) {
			$sql = 'CREATE TABLE ' . $tableName . ' (' . $tableDefinition . ') ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;';
			$GLOBALS['gDb']->query($sql);
		}
	}

	/**
	 * Initializes default fields in the inventory manager database
	 * @return void
	 */
	private function initializeDefaultFields()
	{
		$defaultData = array(
			array('imf_id' => 1, 'imf_name' => 'PIM_ITEMNAME', 'imf_name_intern' => 'ITEMNAME', 'imf_type' => 'TEXT', 'imf_description' => convlanguagePIM('PIM_ITEMNAME_DESCRIPTION'), 'imf_sequence' => 0, 'imf_system' => 1, 'imf_mandatory' => 1),
			array('imf_id' => 2, 'imf_name' => 'PIM_CATEGORY', 'imf_name_intern' => 'CATEGORY', 'imf_type' => 'DROPDOWN', 'imf_description' => convlanguagePIM('PIM_CATEGORY_DESCRIPTION'), 'imf_sequence' => 1, 'imf_system' => 1, 'imf_mandatory' => 1, 'imf_value_list' => 'Allgemein'),
			array('imf_id' => 3, 'imf_name' => 'PIM_KEEPER', 'imf_name_intern' => 'KEEPER', 'imf_type' => 'TEXT', 'imf_description' => convlanguagePIM('PIM_KEEPER_DESCRIPTION'), 'imf_sequence' => 2, 'imf_system' => 1, 'imf_mandatory' => 0),
			array('imf_id' => 4, 'imf_name' => 'PIM_IN_INVENTORY', 'imf_name_intern' => 'IN_INVENTORY', 'imf_type' => 'CHECKBOX', 'imf_description' => convlanguagePIM('PIM_IN_INVENTORY_DESCRIPTION'), 'imf_sequence' => 3, 'imf_system' => 1, 'imf_mandatory' => 0),
			array('imf_id' => 5, 'imf_name' => 'PIM_LAST_RECEIVER', 'imf_name_intern' => 'LAST_RECEIVER', 'imf_type' => 'TEXT', 'imf_description' => convlanguagePIM('PIM_LAST_RECEIVER_DESCRIPTION'), 'imf_sequence' => 4, 'imf_system' => 1, 'imf_mandatory' => 0),
			array('imf_id' => 6, 'imf_name' => 'PIM_RECEIVED_ON', 'imf_name_intern' => 'RECEIVED_ON', 'imf_type' => 'DATE', 'imf_description' => convlanguagePIM('PIM_RECEIVED_ON_DESCRIPTION'), 'imf_sequence' => 5, 'imf_system' => 1, 'imf_mandatory' => 0),
			array('imf_id' => 7, 'imf_name' => 'PIM_RECEIVED_BACK_ON', 'imf_name_intern' => 'RECEIVED_BACK_ON', 'imf_type' => 'DATE', 'imf_description' => convlanguagePIM('PIM_RECEIVED_BACK_ON_DESCRIPTION'), 'imf_sequence' => 6, 'imf_system' => 1, 'imf_mandatory' => 0)
		);

		$sql = 'SELECT imf_id, imf_name, imf_name_intern, imf_type, imf_description, imf_sequence, imf_system, imf_mandatory, imf_value_list FROM ' . TBL_INVENTORY_MANAGER_FIELDS . ' WHERE imf_org_id = \'' . $GLOBALS['gCurrentOrgId'] . '\';';
		$statement = $GLOBALS['gDb']->query($sql);

		$existingFields = array();
		while ($row = $statement->fetch()) {
			$existingFields[$row['imf_name']] = $row;
		}

		$defaultFieldNames = array_column($defaultData, 'imf_name');

		$pimFields = array();
		$customFields = array();

		// Sort the array by imf_sequence to get the imf_name and imf_sequence for custom fields of the previous field
		$existingFieldsOrdered = $existingFields;
		usort($existingFieldsOrdered, function($a, $b) {
			return $a['imf_sequence'] <=> $b['imf_sequence'];
		});

		foreach ($existingFields as $fieldName => $fieldData) {			
			if (strpos($fieldName, 'PIM_') === 0) {
				$pimFields[$fieldName] = $fieldData;
			}
			else {
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
			}
			else {
				foreach($defaultField as $key => $value) {
					if ($key === 'imf_value_list') {
						continue;
					}
					$pimFields[$defaultFieldName][$key] = $value;
				}
			}
		}

		// Sort the array by imd_id but keep the name as imf_name
		usort($pimFields, function($a, $b) {
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
			}
			else {
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
		$sql = 'TRUNCATE TABLE ' . TBL_INVENTORY_MANAGER_FIELDS;
		$GLOBALS['gDb']->query($sql);

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
			$newFieldId = $GLOBALS['gDb']->lastInsertId();
			$newFieldIds[$field['imf_name']] = $newFieldId;
		}

		if (count($existingFields) < count($newFieldIds)) {
		// Sort existing fields by imf_id in descending order
		usort($existingFields, function($a, $b) {
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
					$GLOBALS['gDb']->queryPrepared($sql, array($newFieldIds[$oldFieldName], $oldField['imf_id']));
				}
			}
			else {
				// Field no longer exists, set the field to empty and show an error message
				$sql = 'UPDATE ' . TBL_INVENTORY_MANAGER_DATA . ' SET imd_imf_id = NULL WHERE imd_imf_id = ?';
				$GLOBALS['gDb']->queryPrepared($sql, array($oldField['imf_id']));
				$_SESSION['error_messages'][] = 'Error: Field "' . $oldFieldName . '" no longer exists. Please manually check and adjust the database table"' . TBL_INVENTORY_MANAGER_DATA .'"  where "imd_imf_id" equals "NULL" to avoid data loss.';
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
	private function createField($name, $internalName, $type, $description, $sequence, $system, $mandatory, $valueList = '')
	{
		$itemField = new TableAccess($GLOBALS['gDb'], TBL_INVENTORY_MANAGER_FIELDS, 'imf');
		$itemField->setValue('imf_org_id', (int) $GLOBALS['gCurrentOrgId']);
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
	 * @return void
	 */
	private function initializePreferences()
	{
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
				$sql = 'DELETE FROM ' . $this->table_name . ' WHERE plp_name = ? AND plp_org_id = ?;';
				$GLOBALS['gDb']->queryPrepared($sql, array($plp_name, $GLOBALS['gCurrentOrgId']));
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
	 * @return void
	 */
	public function write()
	{
		foreach ($this->config as $section => $sectionData) {
			foreach ($sectionData as $item => $value) {
				if (is_array($value)) {
					// Data is enclosed in double brackets to mark this record as an array in the database
					$value = '((' . implode(CConfigDataPIM::DB_TOKEN, $value) . '))';
				}

				$plpName = self::SHORTCUT . '__' . $section . '__' . $item;

				$sql = 'SELECT plp_id FROM ' . $this->table_name . ' WHERE plp_name = ? AND (plp_org_id = ? OR plp_org_id IS NULL);';
				$statement = $GLOBALS['gDb']->queryPrepared($sql, array($plpName, $GLOBALS['gCurrentOrgId']));
				$row = $statement->fetchObject();

				if (isset($row->plp_id) && strlen($row->plp_id) > 0) {
					// Record exists, update it
					$sql = 'UPDATE ' . $this->table_name . ' SET plp_value = ? WHERE plp_id = ?;';
					$GLOBALS['gDb']->queryPrepared($sql, array($value, $row->plp_id));
				} else {
					// Record does not exist, insert it
					$sql = 'INSERT INTO ' . $this->table_name . ' (plp_org_id, plp_name, plp_value) VALUES (?, ?, ?);';
					$GLOBALS['gDb']->queryPrepared($sql, array($GLOBALS['gCurrentOrgId'], $plpName, $value));
				}
			}
		}
	}

	/**
	 * Reads the configuration data of plugin InventoryManager from the database
	 * @return void
	 */
	public function read()
	{
		$this->readConfigData(self::SHORTCUT, $this->config);
	}

	/**
	 * Reads the configuration data of plugin FormFiller (PFF) from the database
	 * @return void
	 */
	public function readPff()
	{
		$this->readConfigData('PFF', $this->configPff);
	}

	/**
	 * Reads the configuration data of a plugin from the database
	 * @param string $pluginShortcut The shortcut of the plugin
	 * @param array &$configArray The array to store the configuration data
	 * @return void
	 */
	private function readConfigData($pluginShortcut, &$configArray)
	{
		$sql = 'SELECT plp_id, plp_name, plp_value FROM '.$this->table_name.' WHERE plp_name LIKE ? AND (plp_org_id = ? OR plp_org_id IS NULL);';
		$statement = $GLOBALS['gDb']->queryPrepared($sql, array($pluginShortcut.'__%', $GLOBALS['gCurrentOrgId'])); 
	
		while ($row = $statement->fetch())
		{
			$array = explode('__', $row['plp_name']);
		
			// if plp_value is enclosed in ((  )) -> array
			if ((substr($row['plp_value'], 0, 2) == '((' ) && (substr($row['plp_value'], -2) == '))' ))
			{                                                                          
				$row['plp_value'] = substr($row['plp_value'], 2, -2);
				$configArray[$array[1]] [$array[2]] = explode(CConfigDataPIM::DB_TOKEN, $row['plp_value']); 
			}
			else 
			{
				$configArray[$array[1]] [$array[2]] = $row['plp_value'];
			}

			// if array data is again enclosed in ((  )) -> split again
			if ($pluginShortcut === 'PFF' && is_array($configArray[$array[1]] [$array[2]])) {
				for ($i = 0; $i < count($configArray[$array[1]] [$array[2]]); $i++)
				{
					if ((substr($configArray[$array[1]] [$array[2]][$i], 0, 2) == '((' ) && (substr($configArray[$array[1]] [$array[2]][$i], -2) == '))' ))
					{
						$temp = substr($configArray[$array[1]] [$array[2]][$i], 2, -2);
						$configArray[$array[1]] [$array[2]][$i] = explode(CConfigDataPIM::DB_TOKEN_FORMFILLER, $temp);
					}
				}
			}
		}
	}
	
	/**
	 * Compare plugin version and stand with current version and stand from database
	 * @return bool
	 */
	public function checkForUpdate()
	{
		$needsUpdate = false;

		// Check if table *_plugin_preferences exists
		$sql = 'SHOW TABLES LIKE \'' . $this->table_name . '\' ';
		$tableExistStatement = $GLOBALS['gDb']->queryPrepared($sql);

		if ($tableExistStatement->rowCount()) {
			$needsUpdate = $this->compareVersion() || $this->compareStand();
		} else {
			// Update needed because it is not installed yet
			$needsUpdate = true;
		}

		return $needsUpdate;
	}

	/**
	 * Compare plugin version with the current version from the database
	 * @return bool
	 */
	private function compareVersion()
	{
		$plp_name = self::SHORTCUT . '__Plugininformationen__version';

		$sql = 'SELECT plp_value FROM ' . $this->table_name . ' WHERE plp_name = ? AND (plp_org_id = ? OR plp_org_id IS NULL);';
		$statement = $GLOBALS['gDb']->queryPrepared($sql, array($plp_name, $GLOBALS['gCurrentOrgId']));
		$row = $statement->fetchObject();

		// Compare versions
		return !isset($row->plp_value) || strlen($row->plp_value) === 0 || $row->plp_value !== CPluginInfoPIM::getPluginVersion();
	}

	/**
	 * Compare plugin stand with the current stand from the database
	 * @return bool
	 */
	private function compareStand()
	{
		$plp_name = self::SHORTCUT . '__Plugininformationen__stand';

		$sql = 'SELECT plp_value FROM ' . $this->table_name . ' WHERE plp_name = ? AND (plp_org_id = ? OR plp_org_id IS NULL);';
		$statement = $GLOBALS['gDb']->queryPrepared($sql, array($plp_name, $GLOBALS['gCurrentOrgId']));
		$row = $statement->fetchObject();

		// Compare stands
		return !isset($row->plp_value) || strlen($row->plp_value) === 0 || $row->plp_value !== CPluginInfoPIM::getPluginStand();
	}
	
	/**
	 * Delete configuration data from the database
	 * @param int $deinstOrgSelect 0 = only delete data from current org, 1 = delete data from every org
	 * @return string $result Result message
	 */
	public function deleteConfigData($deinstOrgSelect)
	{
		$result = '';
		$sqlWhereCondition = '';

		if ($deinstOrgSelect == 0) {
			$sqlWhereCondition = 'AND plp_org_id = ?';
		}

		$sql = 'DELETE FROM ' . $this->table_name . ' WHERE plp_name LIKE ? ' . $sqlWhereCondition;
		$params = [self::SHORTCUT . '__%'];
		if ($deinstOrgSelect == 0) {
			$params[] = $GLOBALS['gCurrentOrgId'];
		}
		$result_data = $GLOBALS['gDb']->queryPrepared($sql, $params);
		$result .= ($result_data ? $GLOBALS['gL10n']->get('PLG_INVENTORY_MANAGER_DEINST_DATA_DELETED_IN', [$this->table_name]) : $GLOBALS['gL10n']->get('PLG_INVENTORY_MANAGER_DEINST_DATA_DELETED_IN_ERROR', [$this->table_name]));

		// Check if the table is empty and can be deleted
		$sql = 'SELECT * FROM ' . $this->table_name;
		$statement = $GLOBALS['gDb']->queryPrepared($sql);

		if ($statement->rowCount() == 0) {
			$sql = 'DROP TABLE ' . $this->table_name;
			$result_db = $GLOBALS['gDb']->queryPrepared($sql);
			$result .= ($result_db ? $GLOBALS['gL10n']->get('PLG_INVENTORY_MANAGER_DEINST_TABLE_DELETED', [$this->table_name]) : $GLOBALS['gL10n']->get('PLG_INVENTORY_MANAGER_DEINST_TABLE_DELETE_ERROR', [$this->table_name]));
		} else {
			$result .= $GLOBALS['gL10n']->get('PLG_INVENTORY_MANAGER_DEINST_CONFIGTABLE_DELETE_NOTPOSSIBLE', [$this->table_name]);
		}

		return $result;
	}

	/**
	 * Delete the item data from the database
	 * @param int $deinstOrgSelect 0 = only delete data from current org, 1 = delete data from every org
	 * @return string $result Result message
	 */
	public function deleteItemData($deinstOrgSelect)
	{
		$result = '';

		if ($deinstOrgSelect == 0) {
			$sql = 'DELETE FROM ' . TBL_INVENTORY_MANAGER_DATA . ' WHERE imd_imi_id IN (SELECT imi_id FROM ' . TBL_INVENTORY_MANAGER_ITEMS . ' WHERE imi_org_id = ?)';
			$result_data = $GLOBALS['gDb']->queryPrepared($sql, [$GLOBALS['gCurrentOrgId']]);
			$result .= ($result_data ? $GLOBALS['gL10n']->get('PLG_INVENTORY_MANAGER_DEINST_DATA_DELETED_IN', [TABLE_PREFIX . '_inventory_manager_data']) : $GLOBALS['gL10n']->get('PLG_INVENTORY_MANAGER_DEINST_DATA_DELETED_IN_ERROR', [TABLE_PREFIX . '_inventory_manager_data']));

			$sql = 'DELETE FROM ' . TBL_INVENTORY_MANAGER_LOG . ' WHERE iml_imi_id IN (SELECT imi_id FROM ' . TBL_INVENTORY_MANAGER_ITEMS . ' WHERE imi_org_id = ?)';
			$result_log = $GLOBALS['gDb']->queryPrepared($sql, [$GLOBALS['gCurrentOrgId']]);
			$result .= ($result_log ? $GLOBALS['gL10n']->get('PLG_INVENTORY_MANAGER_DEINST_DATA_DELETED_IN', [TABLE_PREFIX . '_inventory_manager_log']) : $GLOBALS['gL10n']->get('PLG_INVENTORY_MANAGER_DEINST_DATA_DELETED_IN_ERROR', [TABLE_PREFIX . '_inventory_manager_log']));

			$sql = 'DELETE FROM ' . TBL_INVENTORY_MANAGER_ITEMS . ' WHERE imi_org_id = ?';
			$result_items = $GLOBALS['gDb']->queryPrepared($sql, [$GLOBALS['gCurrentOrgId']]);
			$result .= ($result_items ? $GLOBALS['gL10n']->get('PLG_INVENTORY_MANAGER_DEINST_DATA_DELETED_IN', [TABLE_PREFIX . '_inventory_manager_items']) : $GLOBALS['gL10n']->get('PLG_INVENTORY_MANAGER_DEINST_DATA_DELETED_IN_ERROR', [TABLE_PREFIX . '_inventory_manager_items']));

			$sql = 'DELETE FROM ' . TBL_INVENTORY_MANAGER_FIELDS . ' WHERE imf_org_id = ?';
			$result_fields = $GLOBALS['gDb']->queryPrepared($sql, [$GLOBALS['gCurrentOrgId']]);
			$result .= ($result_fields ? $GLOBALS['gL10n']->get('PLG_INVENTORY_MANAGER_DEINST_DATA_DELETED_IN', [TABLE_PREFIX . '_inventory_manager_fields']) : $GLOBALS['gL10n']->get('PLG_INVENTORY_MANAGER_DEINST_DATA_DELETED_IN_ERROR', [TABLE_PREFIX . '_inventory_manager_fields']));
		}

		// Drop tables if they are empty or if data should be deleted from every org
		$table_array = [
			TBL_INVENTORY_MANAGER_FIELDS,
			TBL_INVENTORY_MANAGER_DATA,
			TBL_INVENTORY_MANAGER_ITEMS,
			TBL_INVENTORY_MANAGER_LOG
		];

		foreach ($table_array as $table_name) {
			$sql = 'SELECT * FROM ' . $table_name;
			$statement = $GLOBALS['gDb']->queryPrepared($sql);

			if ($statement->rowCount() == 0 || $deinstOrgSelect == 1) {
				$sql = 'DROP TABLE ' . $table_name;
				$result_db = $GLOBALS['gDb']->queryPrepared($sql);
				$result .= ($result_db ? $GLOBALS['gL10n']->get('PLG_INVENTORY_MANAGER_DEINST_TABLE_DELETED', [$table_name]) : $GLOBALS['gL10n']->get('PLG_INVENTORY_MANAGER_DEINST_TABLE_DELETE_ERROR', [$table_name]));
			} else {
				$result .= $GLOBALS['gL10n']->get('PLG_INVENTORY_MANAGER_DEINST_TABLE_DELETE_NOTPOSSIBLE', [$table_name]);
			}
		}

		return $result;
	}
}
