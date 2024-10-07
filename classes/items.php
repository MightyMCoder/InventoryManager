<?php
/**
 ***********************************************************************************************
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class CItems
 * @brief Reads the items and the item fields structure out of database and give access to it
 *
 */
class CItems
{
	public $mItemFields = array();   ///< Array with all item fields objects
	public $mItemData   = array();   ///< Array with all item data objects
	public $items       = array();   ///< Array with all item objects

    protected $mItemId;                  ///< ItemId of the current item of this object
    protected $mDb;                     ///< An object of the class Database for communication with the database
    protected $noValueCheck;            ///< if true, than no value will be checked if method setValue is called
    protected $newItem;                  ///< Merker, ob ein neuer Datensatz oder vorhandener Datensatz bearbeitet wird
    protected $showFormerItems;          ///< if true, than former items will be showed
    public $columnsValueChanged;        ///< flag if a value of one field had changed

    protected $itemFieldsSort = array();
   
    /**
     * constructor that will initialize variables and read the item field structure
     * @param \Database $database       Database object (should be @b $gDb)
     * @param int       $organizationId The id of the organization for which the item field structure should be read
     */
    public function __construct(&$database, $organizationId)
    {
        $this->mDb =& $database;
        $this->readItemFields($organizationId);
        $this->mItemId = 0;
        $this->noValueCheck = false;
        $this->columnsValueChanged = false;
        $this->newItem = false;
        $this->showFormerItems = true;
    }

    /**
     * Set the database object for communication with the database of this class.
     * @param \Database $database An object of the class Database. This should be the global $gDb object.
     */
    public function setDatabase(&$database)
    {
        $this->mDb =& $database;
    }

    /**
     * Called on serialization of this object. The database object could not
     * be serialized and should be ignored.
     * @return string[] Returns all class variables that should be serialized.
     */
    public function __sleep()
    {
        return array_diff(array_keys(get_object_vars($this)), array('mDb'));
    }
    
    
    /**
     * item data of all item fields will be initialized
     * the fields array will not be renewed
     */
    public function clearItemData()
    {
    	$this->mItemData = array();
    	$this->mItemId = 0;
    	$this->columnsValueChanged = false;
    }
    

    /**
     * returns for a fieldname intern (imf_name_intern) the value of the column from table adm_inventory_manager_fields
     * @param string $fieldNameIntern Expects the @b imf_name_intern of table @b adm_inventory_manager_fields
     * @param string $column          The column name of @b adm_inventory_manager_fields for which you want the value
     * @param string $format          Optional the format (is necessary for timestamps)
     * @return mixed
     */
    public function getProperty($fieldNameIntern, $column, $format = '')
    {
    	$value = '';
        if (array_key_exists($fieldNameIntern, $this->mItemFields))
        {
        	$value = $this->mItemFields[$fieldNameIntern]->getValue($column, $format);
        	
        		if ($column == 'imf_value_list')
        	 	{
        	 		if ($this->mItemFields[$fieldNameIntern]->getValue('imf_type') === 'DROPDOWN' || $this->mItemFields[$fieldNameIntern]->getValue('imf_type') === 'RADIO_BUTTON')
        	 		{
        	 			$value = $this->getListValue($fieldNameIntern, $value, $format);
        	 		}
        	 }
        }
        else 
        {
        	// if id-field not exists then return zero
        	if (strpos($column, '_id') > 0)
        	{
        		$value = 0;
        	}
        }
        return $value;
    }

    /**
     * returns for a fieldname intern (imf_name_intern) the value of the column from table adm_inventory_manager_fields
     * @param string $fieldNameIntern Expects the @b imf_name_intern of table @b adm_inventory_manager_fields
     * @param string $column          The column name of @b adm_inventory_manager_fields for which you want the value
     * @param string $format          Optional the format (is necessary for timestamps)
     * @return mixed
     */
    protected function getListValue($fieldNameIntern, $value , $format)
    {
    	$arrListValuesWithItems = array(); // array with list values and items that represents the internal value
    				 
    	// first replace windows new line with unix new line and then create an array
    	$valueFormated = str_replace("\r\n", "\n", $value);
    	$arrListValues = explode("\n", $valueFormated);
    				 
    	foreach ($arrListValues as $item => &$listValue)
    	{
    		if ($this->mItemFields[$fieldNameIntern]->getValue('imf_type') === 'RADIO_BUTTON')
    		{
    			// if value is imagefile or imageurl then show image
    			if (strpos(strToLower($listValue), '.png') > 0 || strpos(strToLower($listValue), '.jpg') > 0)
    			{
                    // if value is imagefile or imageurl then show image
                    if (Image::isFontAwesomeIcon($listValue)
                    || StringUtils::strContains($listValue, '.png', false) || StringUtils::strContains($listValue, '.jpg', false)) // TODO: simplify check for images
                    {
                        // if there is imagefile and text separated by | then explode them
                        if (StringUtils::strContains($listValue, '|'))
                        {
                            list($listValueImage, $listValueText) = explode('|', $listValue);
                        }
                        else
                        {
                            $listValueImage = $listValue;
                            $listValueText  = $this->getValue('usf_name');
                        }

                        // if text is a translation-id then translate it
                        $listValueText = Language::translateIfTranslationStrId($listValueText);

                        if ($format === 'text')
                        {
                            // if no image is wanted then return the text part or only the position of the entry
                            if (StringUtils::strContains($listValue, '|'))
                            {
                                $listValue = $listValueText;
                            }
                            else
                            {
                                $listValue = $item + 1;
                            }
                        }
                        else
                        {
                            $listValue = Image::getIconHtml($listValueImage, $listValueText);
                        }
                    }
                }    
    		}
    					 
            // if text is a translation-id then translate it
            $listValue = Language::translateIfTranslationStrId($listValue);

            // save values in new array that starts with item = 1
            $arrListValuesWithItems[++$item] = $listValue;
    	}
    	unset($listValue);
    	return $arrListValuesWithItems;
   
    }
    
    /**
     * returns for field id (imf_id) the value of the column from table adm_inventory_manager_fields
     * @param int    $fieldId Expects the @b imf_id of table @b adm_inventory_manager_fields
     * @param string $column  The column name of @b adm_inventory_manager_fields for which you want the value
     * @param string $format  Optional the format (is necessary for timestamps)
     * @return string
     */
    public function getPropertyById($fieldId, $column, $format = '')
    {
        foreach ($this->mItemFields as $field)
        {
            if ((int) $field->getValue('imf_id') === (int) $fieldId)
            {
                return $field->getValue($column, $format);
            }
        }

        return '';
    }

    /**
     * Returns the value of the field in html format with consideration of all layout parameters
     * @param string     $fieldNameIntern Internal item field name of the field that should be html formated
     * @param string|int $value           The value that should be formated must be commited so that layout
     *                                    is also possible for values that aren't stored in database
     * @param int        $value2          An optional parameter that is necessary for some special fields like email to commit the user id
     * @return string Returns an html formated string that considered the profile field settings
     */
    public function getHtmlValue($fieldNameIntern, $value, $value2 = null)
    {
        if (!array_key_exists($fieldNameIntern, $this->mItemFields))
        {
            return $value;
        }

        // if value is empty or null, then do nothing
        if ($value != '')
        {
            // create html for each field type
            $htmlValue = $value;

            $imfType = $this->mItemFields[$fieldNameIntern]->getValue('imf_type');
            switch ($imfType)
            {
                case 'CHECKBOX':
                    if ($value == 1)
                    {
                        $htmlValue = '<i class="fas fa-check-square"></i>';
                    }
                    else
                    {
                        $htmlValue = '<i class="fas fa-square"></i>';
                    }
                    break;
                case 'DATE':
                    if ($value !== '')
                    {
                        // date must be formated
                        $date = \DateTime::createFromFormat('Y-m-d', $value);
                        if ($date instanceof \DateTime)
                        {
                            $htmlValue = $date->format($GLOBALS['gSettingsManager']->getString('system_date'));
                        }
                    }
                    break;
                case 'DROPDOWN':
                case 'RADIO_BUTTON':
                    $arrListValuesWithItems = array(); // array with list values and items that represents the internal value

                    // first replace windows new line with unix new line and then create an array
                    $valueFormated = str_replace("\r\n", "\n", $this->mItemFields[$fieldNameIntern]->getValue('imf_value_list', 'database'));
                    $arrListValues = explode("\n", $valueFormated);

                    foreach ($arrListValues as $index => $listValue)
                    {
                        // if value is imagefile or imageurl then show image
                        if ($imfType === 'RADIO_BUTTON' && (Image::isFontAwesomeIcon($listValue)
                        || StringUtils::strContains($listValue, '.png', false) || StringUtils::strContains($listValue, '.jpg', false))) // TODO: simplify check for images
                        {
                            // if there is imagefile and text separated by | then explode them
                            if (StringUtils::strContains($listValue, '|'))
                            {
                                list($listValueImage, $listValueText) = explode('|', $listValue);
                            }
                            else
                            {
                                $listValueImage = $listValue;
                                $listValueText  = $this->getValue('imf_name');
                            }

                            // if text is a translation-id then translate it
                            $listValueText = Language::translateIfTranslationStrId($listValueText);

                            // get html snippet with image tag
                            $listValue = Image::getIconHtml($listValueImage, $listValueText);
                        }

                        // if text is a translation-id then translate it
                        $listValue = Language::translateIfTranslationStrId($listValue);
                        
                        // save values in new array that starts with item = 1
                        $arrListValuesWithItems[++$index] = $listValue;
                    }

                    $htmlValue = $arrListValuesWithItems[$value];
                    break;
                case 'TEXT_BIG':
                    $htmlValue = nl2br($value);
                    break;
            }

            $value = $htmlValue;
        }
        // special case for type CHECKBOX and no value is there, then show unchecked checkbox
        else
        {
            if ($this->mItemFields[$fieldNameIntern]->getValue('imf_type') === 'CHECKBOX')
            {
                $value = '<i class="fas fa-square"></i>';
            }
        }

        return $value;
    }

    /**
     * Returns the item value for this column @n
     * format = 'd.m.Y' : a date or timestamp field accepts the format of the PHP date() function @n
     * format = 'html'  : returns the value in html-format if this is necessary for that field type @n
     * format = 'database' : returns the value that is stored in database with no format applied
     * @param string $fieldNameIntern Expects the @b imf_name_intern of table @b adm_inventory_manager_fields
     * @param string $format          Returns the field value in a special format @b text, @b html, @b database
     *                                or datetime (detailed description in method description)
     * @return string|int|bool Returns the value for the column.
     */
    public function getValue($fieldNameIntern, $format = '')
    {
        $value = '';

        // exists a item field with that name ?
        // then check if item has a data object for this field and then read value of this object
        if (array_key_exists($fieldNameIntern, $this->mItemFields)
        &&  array_key_exists($this->mItemFields[$fieldNameIntern]->getValue('imf_id'), $this->mItemData))
        {
            $value = $this->mItemData[$this->mItemFields[$fieldNameIntern]->getValue('imf_id')]->getValue('imd_value', $format);

            if ($format === 'database')
            {
                return $value;
            }

            switch ($this->mItemFields[$fieldNameIntern]->getValue('imf_type'))
            {
                case 'DATE':
                    if ($value !== '')
                    {
                        // if date field then the current date format must be used
                        $date = DateTime::createFromFormat('Y-m-d', $value);
                        if ($date === false)
                        {
                            return $value;
                        }

                        // if no format or html is set then show date format from Admidio settings
                        if ($format === '' || $format === 'html')
                        {
                            $value = $date->format($GLOBALS['gSettingsManager']->getString('system_date'));
                        }
                        else
                        {
                            $value = $date->format($format);
                        }
                    }
                    break;
                case 'DROPDOWN':
                case 'RADIO_BUTTON':
                    // the value in db is only the position, now search for the text
                    if ($value > 0 && $format !== 'html')
                    {                          
                      	$valueList = $this->mItemFields[$fieldNameIntern]->getValue('imf_value_list', $format);
                        $arrListValues = $this->getListValue($fieldNameIntern, $valueList, $format);
                    }
                    break;
            }
        }

        // get html output for that field type and value
        if ($format === 'html')
        {
            $value = $this->getHtmlValue($fieldNameIntern, $value);
        }

        return $value;
    }

    /**
     * This method reads or stores the variable for showing former items.
     * The values will be stored in database without any inspections !
     *  @param  bool $newValue    If set, than the new value will be stored in @b showFormerItems.
     *  @return bool Returns the current value of @b showFormerItems
     */
    public function showFormerItems($newValue)
    {
    	if ($newValue === null)
    	{
    		$valid =  $this->showFormerItems;
    	}
    	else
    	{
    		$this->showFormerItems = $newValue;
    		$valid = $newValue;
    	}
    	return $valid;
    }
    
    
    /**
     * If this method is called than all further calls of method @b setValue will not check the values.
     * The values will be stored in database without any inspections !
     */
    public function noValueCheck()
    {
        $this->noValueCheck = true;
    }

    
    /**
     * If the recordset is new and wasn't read from database or was not stored in database
     * then this method will return true otherwise false
     * @return bool Returns @b true if record is not stored in database
     */
    public function isNewItem()
    {
    	return $this->newItem;
    }
    

    /**
     * Reads the item data of all item fields out of database table @b adm_inventory_manager_data
     * and adds an object for each field data to the @b mItemData array.
     * If profile fields structure wasn't read, this will be done before.
     * @param int $itemId         The id of the item for which the item data should be read.
     * @param int $organizationId The id of the organization for which the item fields
     *                            structure should be read if necessary.
     */
    public function readItemData($itemId, $organizationId)
    {    	
    	if (count($this->mItemFields) === 0)
    	{
    		$this->readItemFields($organizationId);
    	}
    
    	$this->mItemData = array();
    	
    	if ($itemId > 0)
    	{
    		// remember the item
    		$this->mItemId = $itemId;
    
    		// read all item data
    		$sql = 'SELECT * FROM '.TBL_INVENTORY_MANAGER_DATA.'
                    INNER JOIN '.TBL_INVENTORY_MANAGER_FIELDS.'
                        ON imf_id = imd_imf_id
                    WHERE imd_imi_id = ?;';
    		$itemDataStatement = $this->mDb->queryPrepared($sql, array($itemId));
    
    		while ($row = $itemDataStatement->fetch())
    		{
    			if (!array_key_exists($row['imd_imf_id'], $this->mItemData))
    			{
    				$this->mItemData[$row['imd_imf_id']] = new TableAccess($this->mDb, TBL_INVENTORY_MANAGER_DATA, 'imd');
    			}
    			$this->mItemData[$row['imd_imf_id']]->setArray($row);
    		}
    	}
    	else 
    	{   	
    		$this->newItem = true;
    	}
    }


    /**
     * save data of every item field
     */
    public function saveItemData()
    { 	
    	$this->mDb->startTransaction();
    
    	foreach ($this->mItemData as $value)
    	{
    		if ($value->hasColumnsValueChanged())
    		{
    			$this->columnsValueChanged = true;
    		}
    		
    		// if value exists and new value is empty then delete entry
    		if ($value->getValue('imd_id') > 0 && $value->getValue('imd_value') === '')
    		{
    			$value->delete();
    		}
    		else
    		{
    			$value->save();
    		}
    	}
    
    	//for updateFingerPrint a change in db must be executet
    	// why !$this->newItem -> updateFingerPrint will be done in getNewItemId
    	if (!$this->newItem && $this->columnsValueChanged)
    	{
    		$updateItem = new TableAccess($this->mDb, TBL_INVENTORY_MANAGER_ITEMS, 'imi', $this->mItemId);
    		$updateItem->setValue('imi_usr_id_change', NULL, false);
    		$updateItem->save();
    	}
    	
    	$this->columnsValueChanged = false;
    	
    	$this->mDb->endTransaction();
    }
    
    /**
     * Reads the item fields structure out of database table @b adm_inventory_manager_fields
     * and adds an object for each field structure to the @b mItemFields array.
     * @param int $organizationId The id of the organization for which the item fields
     *                            structure should be read.
     */
    public function readItemFields($organizationId)
    {
    	// first initialize existing data
    	$this->mItemFields = array();
    	$this->clearItemData();
    
    	$sql = 'SELECT * FROM '.TBL_INVENTORY_MANAGER_FIELDS.'
                WHERE imf_org_id IS NULL
                OR imf_org_id = ?;';
    	$statement = $this->mDb->queryPrepared($sql, array($organizationId));

    	while ($row = $statement->fetch())
    	{
    		if (!array_key_exists($row['imf_name_intern'], $this->mItemFields))
    		{
    			$this->mItemFields[$row['imf_name_intern']] = new TableAccess($this->mDb, TBL_INVENTORY_MANAGER_FIELDS, 'imf');
    		}
     		$this->mItemFields[$row['imf_name_intern']]->setArray($row);
     		$this->itemFieldsSort[$row['imf_name_intern']] = $row['imf_sequence'];
    	}
    	
    	array_multisort($this->itemFieldsSort, SORT_ASC, $this->mItemFields);
    }
    
    
    /**
     * Reads the items out of database table @b adm_inventory_manager_items
     * and stores the values to the @b items array.
     * @param int $organizationId The id of the organization for which the items should be read.
     */
    public function readItems($organizationId)
    {   	 
    	// first initialize existing data
    	$this->items = array();

    	$sqlWhereCondition = '';
    	if (!$this->showFormerItems)
    	{
    		$sqlWhereCondition .= 'AND imi_former = 0';
    	}
    	
    	$sql = 'SELECT DISTINCT imi_id, imi_former FROM '.TBL_INVENTORY_MANAGER_ITEMS.'
                INNER JOIN '.TBL_INVENTORY_MANAGER_DATA.'
                    ON imd_imi_id = imi_id
                WHERE imi_org_id IS NULL
                OR imi_org_id = ?
                '.$sqlWhereCondition.';';
    	$statement = $this->mDb->queryPrepared($sql, array($organizationId));

    	while ($row = $statement->fetch())
    	{
    		$this->items[] = array('imi_id' => $row['imi_id'], 'imi_former' => $row['imi_former']);
    	} 
    }

 
    /**
     * Reads the items for an user out of database table @b adm_inventory_manager_items
     * and stores the values to the @b items array.
     * @param int $organizationId The id of the organization for which the items should be read.
     * @param int $userId The id of the user for which the items should be read.
     */
    public function readItemsByUser($organizationId, $userId)
    {
        // first initialize existing data
        $this->items = array();
        
        $sqlWhereCondition = '';
        if (!$this->showFormerItems)
        {
            $sqlWhereCondition .= 'AND imi_former = 0';
        }

        $sql = 'SELECT DISTINCT imi_id, imi_former FROM '.TBL_INVENTORY_MANAGER_DATA.'
                INNER JOIN '.TBL_INVENTORY_MANAGER_FIELDS.'
                    ON imf_id = imd_imf_id
                    AND imf_id = ?
                INNER JOIN '.TBL_INVENTORY_MANAGER_ITEMS.'
                    ON imi_id = imd_imi_id
                WHERE (imi_org_id IS NULL
                    OR imi_org_id = ?)
                AND imd_value = ?
                '.$sqlWhereCondition.';';
        $statement = $this->mDb->queryPrepared($sql, array($this->getProperty('RECEIVER', 'imf_id'), $organizationId, $userId));
        
        while ($row = $statement->fetch())
        {
            $this->items[] = array('imi_id' => $row['imi_id'], 'imi_former' => $row['imi_former']);
        }
    }
    
    
    /**
     * Set a new value for the item field of the table adm_inventory_manager_data.
     * If the user log is activated than the change of the value will be logged in @b adm_inventory_manager_log.
     * The value is only saved in the object. You must call the method @b save to store the new value to the database
     * @param string $columnName The name of the database column whose value should get a new value or the
     *                           internal unique profile field name
     * @param mixed  $newValue   The new value that should be stored in the database field
     * @param bool   $checkValue The value will be checked if it's valid. If set to @b false than the value will
     *                           not be checked.
     * @return bool Returns @b true if the value is stored in the current object and @b false if a check failed
     */
    public function setValue($fieldNameIntern, $newValue, $checkValue = true)
    {
    	$imfId = $this->mItemFields[$fieldNameIntern]->getValue('imf_id');
    	
    	if (!array_key_exists($imfId, $this->mItemData) )
    	{
    		$oldFieldValue = '';
    	}
    	else 
    	{
    		$oldFieldValue = $this->mItemData[$imfId]->getValue('imd_value');
    	}
    	
    	// item data from adm_inventory_manager_fields table
    	$newValue = (string) $newValue;
    
    	// format of date will be local but database has stored Y-m-d format must be changed for compare
    	if($this->mItemFields[$fieldNameIntern]->getValue('imf_type') === 'DATE')
    	{
    	   $date = \DateTime::createFromFormat($GLOBALS['gSettingsManager']->getString('system_date'), $newValue);
    
           if($date !== false)
    	   {
    	       $newValue = $date->format('Y-m-d');
    	   }
    	}
    
    	// only to a update if value has changed
    	if (strcmp($oldFieldValue, $newValue) === 0) // https://secure.php.net/manual/en/function.strcmp.php#108563
    	{
    		return true;
    	}
    
    	$returnCode = false;
    
    	if (!array_key_exists($imfId, $this->mItemData) )
    	{
    		$this->mItemData[$imfId] = new TableAccess($this->mDb, TBL_INVENTORY_MANAGER_DATA, 'imd');
    		$this->mItemData[$imfId]->setValue('imd_imf_id', $imfId);
    		$this->mItemData[$imfId]->setValue('imd_imi_id', $this->mItemId);
    	}
    	
    	$returnCode = $this->mItemData[$imfId]->setValue('imd_value', $newValue);
    			
        if ($returnCode && $GLOBALS['gSettingsManager']->getBool('profile_log_edit_fields'))
    	{
    		$logEntry = new TableAccess($this->mDb, TBL_INVENTORY_MANAGER_LOG, 'iml');
    		$logEntry->setValue('iml_imi_id', $this->mItemId);
    		$logEntry->setValue('iml_imf_id', $imfId);
    		$logEntry->setValue('iml_value_old', $oldFieldValue);
    		$logEntry->setValue('iml_value_new', $newValue);
    		$logEntry->setValue('iml_comment', '');
    		$logEntry->save();
    	}
    
    	return $returnCode;
    }
    
    
    /**
     * Generates a new ItemId. The new value will be stored in @b mItemId. 
     * @return int @b mItemId
     */
    public function getNewItemId()
    {
     	//If an error occured while generating a item, there is a ItemId but no data for that item.
    	//the following routine deletes these unused ItemIds
  		$sql = 'SELECT * FROM '.TBL_INVENTORY_MANAGER_ITEMS.'
                LEFT JOIN '.TBL_INVENTORY_MANAGER_DATA.'
                    ON imd_imi_id = imi_id
                WHERE imd_imi_id is NULL;';
    	$statement = $this->mDb->queryPrepared($sql);
   
    	while ($row = $statement->fetch())
    	{
    		$delItem = new TableAccess($this->mDb, TBL_INVENTORY_MANAGER_ITEMS, 'imi', $row['imi_id']);
    		$delItem->delete();
    	}

    	//generate a new ItemId
    	if ($this->newItem)
    	{
    		$newItem = new TableAccess($this->mDb, TBL_INVENTORY_MANAGER_ITEMS, 'imi');
    		$newItem->setValue('imi_org_id', $GLOBALS['gCurrentOrgId']);
    		$newItem->setValue('imi_former', 0);
    		$newItem->save();
    	
    		$this->mItemId = $newItem->getValue('imi_id');
    		
    		// update item table
    		$this->readItems($GLOBALS['gCurrentOrgId']);
    		
    		return $this->mItemId;
    	}
    }
}
