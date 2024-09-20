<?php
/**
 ***********************************************************************************************
 * Class to manage the configuration table "[admidio-praefix]_plugin_preferences" of Plugin InventoryManager
 *
 * @copyright The Admidio Team
 * @see http://www.admidio.org/
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

	protected $table_name;					// db table name *_plugin_preferences
	protected $isPffInst; 					// (is) (P)lugin (f)orm (f)iller (Inst)alled
	protected $pffDir;						// (p)lugin (f)orm (f)iller (Dir)ectory 

	protected const SHORTCUT = 'PIM';		// praefix for (P)lugin(I)nventory(M)anager preferences

    /**
     * CConfigTablePIM constructor
     */
	public function __construct()
	{
		require_once(__DIR__ . '/../version.php');
		require_once(__DIR__ . '/../configdata.php');
		
		$this->table_name = TABLE_PREFIX .'_plugin_preferences';

		$this->findPff();
		$this->checkPffInst();
	}
	
	/**
	 * Checks if the plugin FormFiller is installed
	 * @return void
	 */
	protected function checkPffInst()
	{
	    // check if configuration table for plugin FormFiller exists
	    $sql = 'SHOW TABLES LIKE \''.$this->table_name.'\' ';
	    $statement = $GLOBALS['gDb']->queryPrepared($sql);
	    
	    if ($statement->rowCount() !== 0)  
	    {
	        $sql = 'SELECT COUNT(*) AS COUNT
            		       FROM '.$this->table_name.'
            		      WHERE plp_name = ?
            		        AND ( plp_org_id = ?
            	    	     OR plp_org_id IS NULL ) ';
	        $statement = $GLOBALS['gDb']->queryPrepared($sql, array('PFF__Plugininformationen__version', $GLOBALS['gCurrentOrgId']));
	        
	        if((int) $statement->fetchColumn() === 1  && $this->pffDir !== false)
	        {
	            $this->isPffInst = true;
	        }
	        else
	        {
	            $this->isPffInst = false;
	        }
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
	protected function findPff()
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
		#region create fields-table if not exists
		$sql = 'SHOW TABLES LIKE \''.TBL_INVENTORY_MANAGER_FIELDS.'\' ';
		$statement = $GLOBALS['gDb']->query($sql);
		
		if (!$statement->rowCount())
		{
			$sql='CREATE TABLE '.TBL_INVENTORY_MANAGER_FIELDS.'
				(imf_id int(10) unsigned NOT NULL AUTO_INCREMENT,
	  			imf_org_id int(10) unsigned NOT NULL,
	  			imf_type varchar(30)  NOT NULL,
	  			imf_name  varchar(100)   NOT NULL,
				imf_name_intern  varchar(110)   NOT NULL,
				imf_sequence int(10) unsigned NOT NULL,
				imf_system boolean  NOT NULL DEFAULT \'0\',	
				imf_mandatory boolean  NOT NULL DEFAULT \'0\',	
	  			imf_description text NOT NULL DEFAULT \'\',
				imf_value_list text,
	  			imf_usr_id_create int(10) unsigned DEFAULT NULL,
	  			imf_timestamp_create timestamp NULL DEFAULT CURRENT_TIMESTAMP,
	  			imf_usr_id_change int(10) unsigned DEFAULT NULL,
	  			imf_timestamp_change timestamp NULL DEFAULT NULL,
	  			PRIMARY KEY (imf_id) ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;';
			    $GLOBALS['gDb']->query($sql);
		}
		#endregion	

		#region create data-table if not exists
		$sql = 'SHOW TABLES LIKE \''.TBL_INVENTORY_MANAGER_DATA.'\' ';
		$statement = $GLOBALS['gDb']->query($sql);
		
		if (!$statement->rowCount())
		{
			$sql='CREATE TABLE '.TBL_INVENTORY_MANAGER_DATA.'
				(imd_id int(10) unsigned NOT NULL AUTO_INCREMENT,
	  			 imd_imf_id int(10) unsigned  NOT NULL,
				 imd_imi_id int(10) unsigned  NOT NULL,
	  			 imd_value varchar(4000),
	  			 PRIMARY KEY (imd_id) ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;';
			     $GLOBALS['gDb']->query($sql);
		}
		#endregion
		
		#region create items-table if not exists		
		$sql = 'SHOW TABLES LIKE \''.TBL_INVENTORY_MANAGER_ITEMS.'\' ';
		$statement = $GLOBALS['gDb']->query($sql);

		if (!$statement->rowCount())
		{
			$sql='CREATE TABLE '.TBL_INVENTORY_MANAGER_ITEMS.'
				(imi_id int(10) unsigned NOT NULL AUTO_INCREMENT,
	  			imi_org_id int(10) unsigned NOT NULL,
				imi_former boolean DEFAULT 0,	
				imi_usr_id_create int(10) unsigned DEFAULT NULL,
	  			imi_timestamp_create timestamp NULL DEFAULT CURRENT_TIMESTAMP,
	  			imi_usr_id_change int(10) unsigned DEFAULT NULL,
	  			imi_timestamp_change timestamp NULL DEFAULT NULL,
	  			PRIMARY KEY (imi_id) ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;';
			    $GLOBALS['gDb']->query($sql);
		}
		#endregion

		#region create log-table if not exists
		$sql = 'SHOW TABLES LIKE \''.TBL_INVENTORY_MANAGER_LOG.'\' ';
		$statement = $GLOBALS['gDb']->query($sql);
		
		if (!$statement->rowCount())
		{
			$sql='CREATE TABLE '.TBL_INVENTORY_MANAGER_LOG.'
				(iml_id int(10) unsigned NOT NULL AUTO_INCREMENT,
				iml_imi_id int(10) unsigned NOT NULL,	
				iml_imf_id int(10) unsigned NOT NULL,	
				iml_value_old varchar(4000),	
				iml_value_new varchar(4000),	
				iml_usr_id_create int(10) unsigned DEFAULT NULL,
	  			iml_timestamp_create timestamp NULL DEFAULT CURRENT_TIMESTAMP,	
	  			iml_comment varchar(255) NULL,	
	  			PRIMARY KEY (iml_id) ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;';
			    $GLOBALS['gDb']->query($sql);
		}
		#endregion
		
		$sql = 'SELECT *
            	  FROM '.TBL_INVENTORY_MANAGER_FIELDS.'
            	 WHERE imf_name_intern = \'ITEMNAME\'
            	   AND imf_org_id = \''.$GLOBALS['gCurrentOrgId'].'\' ';
		$statement = $GLOBALS['gDb']->query($sql);
		
		if ($statement->rowCount() == 0)                 
		{
			$itemField = new TableAccess($GLOBALS['gDb'], TBL_INVENTORY_MANAGER_FIELDS, 'imf');
			$itemField->setValue('imf_org_id', (int) $GLOBALS['gCurrentOrgId']);
			$itemField->setValue('imf_sequence', 1);
			$itemField->setValue('imf_system', 1);
			$itemField->setValue('imf_mandatory', 1);
			$itemField->setValue('imf_name', 'PIM_ITEMNAME');
			$itemField->setValue('imf_name_intern', 'ITEMNAME');
			$itemField->setValue('imf_type', 'TEXT');
			$itemField->setValue('imf_description', 'Der Name des Gegenstandes');
			$itemField->save();

			$itemField = new TableAccess($GLOBALS['gDb'], TBL_INVENTORY_MANAGER_FIELDS, 'imf');
			$itemField->setValue('imf_org_id', (int) $GLOBALS['gCurrentOrgId']);
			$itemField->setValue('imf_sequence', 1);
			$itemField->setValue('imf_system', 0);
			$itemField->setValue('imf_mandatory', 1);
			$itemField->setValue('imf_name', 'PIM_CATEGORY');
			$itemField->setValue('imf_name_intern', 'CATEGORY');
			$itemField->setValue('imf_type', 'DROPDOWN');
			$itemField->setValue('imf_value_list', 'Allgemein');
			$itemField->setValue('imf_description', 'Die Kategorie des Gegenstandes');
			$itemField->save();
		
			$itemField = new TableAccess($GLOBALS['gDb'], TBL_INVENTORY_MANAGER_FIELDS, 'imf');
			$itemField->setValue('imf_org_id', (int) $GLOBALS['gCurrentOrgId']);
			$itemField->setValue('imf_sequence', 2);
			$itemField->setValue('imf_system', 0);
			$itemField->setValue('imf_mandatory', 0);
			$itemField->setValue('imf_name', 'PIM_RECEIVER');
			$itemField->setValue('imf_name_intern', 'RECEIVER');
			$itemField->setValue('imf_type', 'TEXT');
			$itemField->setValue('imf_description', 'Der Empfänger des Gegenstandes');
			$itemField->save();
		
			$itemField = new TableAccess($GLOBALS['gDb'], TBL_INVENTORY_MANAGER_FIELDS, 'imf');
			$itemField->setValue('imf_org_id', (int) $GLOBALS['gCurrentOrgId']);
			$itemField->setValue('imf_sequence', 3);
			$itemField->setValue('imf_system', 0);
			$itemField->setValue('imf_mandatory', 0);
			$itemField->setValue('imf_name', 'PIM_RECEIVED_ON');
			$itemField->setValue('imf_name_intern', 'RECEIVED_ON');
			$itemField->setValue('imf_type', 'DATE');
			$itemField->setValue('imf_description', 'Das Empfangsdatum des Gegenstandes');
			$itemField->save();
		}

		$config_ist = array();
		
		#region create preferences-table if not exists
		$sql = 'SHOW TABLES LIKE \''.$this->table_name.'\' ';
   	 	$statement = $GLOBALS['gDb']->queryPrepared($sql);
    
    	if (!$statement->rowCount())
    	{
        	$sql = 'CREATE TABLE '.$this->table_name.' (
            	plp_id 		integer     unsigned not null AUTO_INCREMENT,
            	plp_org_id 	integer   	unsigned not null,
    			plp_name 	varchar(255) not null,
            	plp_value  	text, 
            	primary key (plp_id) )
            	engine = InnoDB
         		auto_increment = 1
          		default character set = utf8
         		collate = utf8_unicode_ci';
    		    $GLOBALS['gDb']->queryPrepared($sql);
    	} 
		#endregion
		
		#region fill preferences-table		
		$this->read();
		
		$this->config['Plugininformationen']['version'] = CPluginInfoPIM::getPluginVersion();
		$this->config['Plugininformationen']['stand'] = CPluginInfoPIM::getPluginStand();
	
		// create temporary current configuration array
		$configCurrent = $this->config;

		// loop through default config sections
		foreach (CConfigDataPIM::CONFIG_DEFAULT as $section => $sectiondata)
    	{
        	foreach ($sectiondata as $item => $value)
        	{
        		// section exists?
        		if (isset($configCurrent[$section][$item]))
        		{
        			// yes, delete it
        			unset($configCurrent[$section][$item]);
        		}
        		else
        		{
        			// no, create it
        			$this->config[$section][$item] = $value;
        		}
        	}
        	// cleanup empty sections
        	if ((isset($configCurrent[$section]) && count($configCurrent[$section]) == 0))
        	{
        		unset($configCurrent[$section]);
        	}
    	}
    
		// loop through current config sections and delete unused sections
		foreach ($configCurrent as $section => $sectiondata)
    	{
    		foreach ($sectiondata as $item => $value)
        	{
        		$plp_name = self::SHORTCUT.'__'.$section.'__'.$item;
				$sql = 'DELETE FROM '.$this->table_name.'
        				      WHERE plp_name = ? 
        				        AND plp_org_id = ? ';
				$GLOBALS['gDb']->queryPrepared($sql, array($plp_name, $GLOBALS['gCurrentOrgId']));
                
				unset($this->config[$section][$item]);
        	}
			// cleanup empty sections
        	if (count($this->config[$section]) == 0)
        	{
        		unset($this->config[$section]);
        	}
    	}

    	// write new configuration to table
  		$this->write();
		#endregion
	}

    /**
     * write the configuration data of plugin InventoryManager to database
     * @return void
     */
	public function write()
	{
    	foreach ($this->config as $section => $sectiondata)
    	{
        	foreach ($sectiondata as $item => $value)
        	{
            	if (is_array($value))
            	{
                	// data is enclosed in double brackets to mark this record as an array in database
            		$value = '(('.implode(CConfigDataPIM::DB_TOKEN, $value).'))';
            	} 
            
  				$plp_name = self::SHORTCUT.'__'.$section.'__'.$item;
          
            	$sql = ' SELECT plp_id 
            			   FROM '.$this->table_name.' 
            			  WHERE plp_name = ? 
            			    AND ( plp_org_id = ?
                 		     OR plp_org_id IS NULL ) ';
            	$statement = $GLOBALS['gDb']->queryPrepared($sql, array($plp_name, $GLOBALS['gCurrentOrgId']));
            	$row = $statement->fetchObject();

            	// check if record exists
            	// yes, update ot
            	if (isset($row->plp_id) AND strlen($row->plp_id) > 0)
            	{
                	$sql = 'UPDATE '.$this->table_name.' 
                			   SET plp_value = ?
                			 WHERE plp_id = ? ';   
                    $GLOBALS['gDb']->queryPrepared($sql, array($value, $row->plp_id));           
            	}
            	// no, insert it
            	else
            	{
  					$sql = 'INSERT INTO '.$this->table_name.' (plp_org_id, plp_name, plp_value) 
  							VALUES (? , ? , ?)  -- $GLOBALS[\'gCurrentOrgId\'], self::SHORTCUT.\'__\'.$section.\'__\'.$item, $value '; 
            		$GLOBALS['gDb']->queryPrepared($sql, array($GLOBALS['gCurrentOrgId'], self::SHORTCUT.'__'.$section.'__'.$item, $value));
            	}   
        	} 
    	}
	}

    /**
     * read the configuration data of plugin InventoryManager from database
     * @return void
     */
	public function read()
	{
		$sql = 'SELECT plp_id, plp_name, plp_value
             	  FROM '.$this->table_name.'
             	 WHERE plp_name LIKE ?
             	   AND ( plp_org_id = ?
                    OR plp_org_id IS NULL ) ';
		$statement = $GLOBALS['gDb']->queryPrepared($sql, array(self::SHORTCUT.'__%', $GLOBALS['gCurrentOrgId'])); 
	
		while ($row = $statement->fetch())
		{
			$array = explode('__',$row['plp_name']);
		
			// if plp_value is enclosed in ((  )) -> array
			if ((substr($row['plp_value'], 0, 2) == '((' ) && (substr($row['plp_value'], -2) == '))' ))
        	{                                                                          
        		$row['plp_value'] = substr($row['plp_value'], 2, -2);
        		$this->config[$array[1]] [$array[2]] = explode(CConfigDataPIM::DB_TOKEN, $row['plp_value']); 
        	}
        	else 
			{
            	$this->config[$array[1]] [$array[2]] = $row['plp_value'];
        	}
		}
	}

	/**
	 * read the configuration data of plugin FormFiller (PFF) from database
	 * @return void
	 */
	public function readPff()
	{
		$sql = ' SELECT plp_id, plp_name, plp_value
             	   FROM '.$this->table_name.'
             	  WHERE plp_name LIKE ?
             	    AND ( plp_org_id = ?
                 	 OR plp_org_id IS NULL ) ';
		$statement = $GLOBALS['gDb']->queryPrepared($sql, array('PFF__%', $GLOBALS['gCurrentOrgId'])); 
	
		while ($row = $statement->fetch())
		{
			$array = explode('__',$row['plp_name']);
	
			// if plp_value is enclosed in ((  )) -> array
			if ((substr($row['plp_value'],0,2) == '((' ) && (substr($row['plp_value'],-2) == '))' ))
			{
				$row['plp_value'] = substr($row['plp_value'], 2, -2);
				$this->configPff[$array[1]] [$array[2]] = explode(CConfigDataPIM::DB_TOKEN,$row['plp_value']);
	
				// if array data is again enclosed in ((  )) -> split again
				for ($i = 0; $i < count($this->configPff[$array[1]] [$array[2]]); $i++)
				{
					if ((substr($this->configPff[$array[1]] [$array[2]][$i],0,2) == '((' ) && (substr($this->configPff[$array[1]] [$array[2]][$i],-2) == '))' ))
					{
						$temp = substr($this->configPff[$array[1]] [$array[2]][$i], 2, -2);
						$this->configPff[$array[1]] [$array[2]][$i] = array();
						$this->configPff[$array[1]] [$array[2]][$i] = explode(CConfigDataPIM::DB_TOKEN_FORMFILLER, $temp);
					}
				}
			}
			else
			{
				$this->configPff[$array[1]] [$array[2]] = $row['plp_value'];
			}
		}
	}
	
    /**
     * compare plugin version and stand with current version and stand from database
     * @return bool
     */
	public function checkForUpdate()
	{
	 	$needsUpdate = false;
 	
	 	// check if table *_plugin_preferences exists
		$sql = 'SHOW TABLES LIKE \''.$this->table_name.'\' ';
   	 	$tableExistStatement = $GLOBALS['gDb']->queryPrepared($sql);
    
    	if ($tableExistStatement->rowCount())
    	{
			#region compare version
			$plp_name = self::SHORTCUT.'__Plugininformationen__version';
          
    		$sql = 'SELECT plp_value 
            		  FROM '.$this->table_name.' 
            		 WHERE plp_name = ? 
            		   AND ( plp_org_id = ?
            	    	OR plp_org_id IS NULL ) ';
    		$statement = $GLOBALS['gDb']->queryPrepared($sql, array($plp_name, $GLOBALS['gCurrentOrgId']));
    		$row = $statement->fetchObject();

			// compare versions
    		if (!isset($row->plp_value) || strlen($row->plp_value) === 0 || $row->plp_value<>CPluginInfoPIM::getPluginVersion())
    		{
    			$needsUpdate = true;    
    		}
			#endregion

			#region compare stand	
    		$plp_name = self::SHORTCUT.'__Plugininformationen__stand';
          
    		$sql = 'SELECT plp_value 
            		  FROM '.$this->table_name.' 
            		 WHERE plp_name = ?
            		   AND ( plp_org_id = ?
                 		OR plp_org_id IS NULL ) ';
            $statement = $GLOBALS['gDb']->queryPrepared($sql, array($plp_name, $GLOBALS['gCurrentOrgId']));
    		$row = $statement->fetchObject();

    		// compare stands
    		if (!isset($row->plp_value) || strlen($row->plp_value) === 0 || $row->plp_value<>CPluginInfoPIM::getPluginStand())
    		{
    			$needsUpdate = true;    
    		}
			#endregion
    	}
    	else 
    	{
			// also update needed because it is not installed yet
    		$needsUpdate = true; 
    	}
    	return $needsUpdate;
	}
	
    /**
     * delete configuration data from database
     * @param   int     $deinstOrgSelect  0 = only delete data from current org, 1 = delete data from every org
     * @return  string  $result             result message
     */
	public function deleteConfigData($deinstOrgSelect)
	{
    	$result      = '';		
    	$sqlWhereCondition = '';
		$result_data = false;
		$result_db   = false;
		
		if ($deinstOrgSelect == 0)
		{
			$sqlWhereCondition = 'AND plp_org_id =  \''.$GLOBALS['gCurrentOrgId'].'\' ';	
		}

		$sql = 'DELETE FROM '.$this->table_name.'
        			  WHERE plp_name LIKE ?
                      '. $sqlWhereCondition ;
		$result_data = $GLOBALS['gDb']->queryPrepared($sql, array(self::SHORTCUT.'__%'));	
		$result .= ($result_data ? $GLOBALS['gL10n']->get('PLG_INVENTORY_MANAGER_DEINST_DATA_DELETED_IN', array($this->table_name)) : $GLOBALS['gL10n']->get('PLG_INVENTORY_MANAGER_DEINST_DATA_DELETED_IN_ERROR', array($this->table_name)));
		
		// if there only was config data from current org the table should be empty and could be deleted
		$sql = 'SELECT * FROM '.$this->table_name.' ';
		$statement = $GLOBALS['gDb']->queryPrepared($sql);

    	if ($statement->rowCount() == 0)
    	{
        	$sql = 'DROP TABLE '.$this->table_name.' ';
        	$result_db = $GLOBALS['gDb']->queryPrepared($sql);
        	$result .= ($result_db ? $GLOBALS['gL10n']->get('PLG_INVENTORY_MANAGER_DEINST_TABLE_DELETED', array($this->table_name )) : $GLOBALS['gL10n']->get('PLG_INVENTORY_MANAGER_DEINST_TABLE_DELETE_ERROR', array($this->table_name)));
        }
        else
        {
        	$result .= $GLOBALS['gL10n']->get('PLG_INVENTORY_MANAGER_DEINST_CONFIGTABLE_DELETE_NOTPOSSIBLE', array($this->table_name )) ;
        }
		
		return $result;
	}
	
	/**
	 * delete the item data from database
     * @param   int     $deinstOrgSelect  0 = only delete data from current org, 1 = delete data from every org
     * @return  string  $result             result message
	 */
	public function deleteItemData($deinstOrgSelect)
	{
		$result = ''; 

		if($deinstOrgSelect == 0)
		{
			/*$sql = 'DELETE FROM '.TBL_INVENTORY_MANAGER_DATA.'
                          WHERE imd_imi_id IN 
              	        (SELECT imi_id 
					       FROM ?
                	      WHERE imi_org_id = ? )';
	
			$result_data = $GLOBALS['gDb']->queryPrepared($sql, array(TBL_INVENTORY_MANAGER_ITEMS, $GLOBALS['gCurrentOrgId']));	*/
		    //queryPrepared doesn´t work Why? Since when?
		    // This code works:
		    $sql = 'DELETE FROM '.TBL_INVENTORY_MANAGER_DATA.'
                          WHERE imd_imi_id IN
              	        (SELECT imi_id
					       FROM '.TBL_INVENTORY_MANAGER_ITEMS.'
                	      WHERE imi_org_id = \''.$GLOBALS['gCurrentOrgId'].'\' )';
		    $result_data = $GLOBALS['gDb']->query($sql);
		    
			$result .= ($result_data ? $GLOBALS['gL10n']->get('PLG_INVENTORY_MANAGER_DEINST_DATA_DELETED_IN', array(TABLE_PREFIX . '_inventory_manager_data' )) : $GLOBALS['gL10n']->get('PLG_INVENTORY_MANAGER_DEINST_DATA_DELETED_IN_ERROR', array(TABLE_PREFIX . '_inventory_manager_data' )));
		
			/*$sql = 'DELETE FROM '.TBL_INVENTORY_MANAGER_LOG.'
                          WHERE iml_imi_id IN 
				        (SELECT imi_id 
					       FROM ?
                          WHERE imi_org_id = ? )';

			$result_log = $GLOBALS['gDb']->queryPrepared($sql, array(TBL_INVENTORY_MANAGER_ITEMS, $GLOBALS['gCurrentOrgId']));	*/
			$sql = 'DELETE FROM '.TBL_INVENTORY_MANAGER_LOG.'
                          WHERE iml_imi_id IN
				        (SELECT imi_id
					       FROM '.TBL_INVENTORY_MANAGER_ITEMS.'
                          WHERE imi_org_id = \''.$GLOBALS['gCurrentOrgId'].'\' )';
			
			$result_log = $GLOBALS['gDb']->query($sql);
			
			$result .= ($result_log ? $GLOBALS['gL10n']->get('PLG_INVENTORY_MANAGER_DEINST_DATA_DELETED_IN', array(TABLE_PREFIX . '_inventory_manager_log' )) : $GLOBALS['gL10n']->get('PLG_INVENTORY_MANAGER_DEINST_DATA_DELETED_IN_ERROR', array(TABLE_PREFIX . '_inventory_manager_log')));
		
			$sql = 'DELETE FROM '.TBL_INVENTORY_MANAGER_ITEMS.'
	        	          WHERE imi_org_id = ? ';

			$result_items = $GLOBALS['gDb']->queryPrepared($sql, array($GLOBALS['gCurrentOrgId']));
			$result .= ($result_items ? $GLOBALS['gL10n']->get('PLG_INVENTORY_MANAGER_DEINST_DATA_DELETED_IN', array(TABLE_PREFIX . '_inventory_manager_items' )) : $GLOBALS['gL10n']->get('PLG_INVENTORY_MANAGER_DEINST_DATA_DELETED_IN_ERROR', array(TABLE_PREFIX . '_inventory_manager_items')));
		
			$sql = 'DELETE FROM '.TBL_INVENTORY_MANAGER_FIELDS.'
                          WHERE imf_org_id = ? ';
			
			$result_fields = $GLOBALS['gDb']->queryPrepared($sql, array($GLOBALS['gCurrentOrgId']));
			$result .= ($result_fields ? $GLOBALS['gL10n']->get('PLG_INVENTORY_MANAGER_DEINST_DATA_DELETED_IN', array(TABLE_PREFIX . '_inventory_manager_fields' )) : $GLOBALS['gL10n']->get('PLG_INVENTORY_MANAGER_DEINST_DATA_DELETED_IN_ERROR', array(TABLE_PREFIX . '_inventory_manager_fields')));
		}
		
		//drop tables items, data, log and fields 
		$table_array = array(
				TBL_INVENTORY_MANAGER_FIELDS,
				TBL_INVENTORY_MANAGER_DATA,
				TBL_INVENTORY_MANAGER_ITEMS,
				TBL_INVENTORY_MANAGER_LOG );
	
		foreach ($table_array as $table_name)
		{
			$result_db   = false;
			
			// wenn in der Tabelle keine Eintraege mehr sind, dann kann sie geloescht werden
			// oder wenn 'Daten in allen Orgs loeschen' gewaehlt wurde
			$sql = 'SELECT * FROM '.$table_name.' ';
			$statement = $GLOBALS['gDb']->queryPrepared($sql);
				
			if ($statement->rowCount() == 0 || $deinstOrgSelect == 1)
			{
				$sql = 'DROP TABLE '.$table_name.' ';
				$result_db = $GLOBALS['gDb']->queryPrepared($sql);
				$result .= ($result_db ? $GLOBALS['gL10n']->get('PLG_INVENTORY_MANAGER_DEINST_TABLE_DELETED', array($table_name )) : $GLOBALS['gL10n']->get('PLG_INVENTORY_MANAGER_DEINST_TABLE_DELETE_ERROR', array($table_name)));
			}
			else 
			{
				$result .= $GLOBALS['gL10n']->get('PLG_INVENTORY_MANAGER_DEINST_TABLE_DELETE_NOTPOSSIBLE', array($table_name)) ;
			}
		}
		
		return $result;
	}
}
