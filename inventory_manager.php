<?php
/**
 ***********************************************************************************************
 * InventoryManager
 *
 * Version 1.0.3
 *
 * InventoryManager is an Admidio plugin for managing the inventory of an organisation.
 * 
 * Note:
 *  - InventoryManager is based on KeyManager by rmbinder (https://github.com/rmbinder/KeyManager)
 *  - InventoryManager uses the external class XLSXWriter (https://github.com/mk-j/PHP_XLSXWriter)
 * 
 * Author: MightyMCoder
 *
 * Compatible with Admidio version 4.3
 * 
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 * 
 * Parameters:
 * 
 * mode              : Output(html, print, csv-ms, csv-oo, pdf, pdfl, xlsx)
 * filter_string     : general filter string
 * filter_category   : filter for category
 * filter_keeper   : filter for keeper
 * show_all          : 0 - (Default) show active items only
 *                     1 - show all items (also made to the former)
 * export_and_filter : 0 - (Default) No filter and export menu
 *                     1 - Filter and export menu is enabled
 * same_side         : 0 - (Default) side was called by another side
 *                     1 - internal call of the side
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/common_function.php');
require_once(__DIR__ . '/classes/items.php');
require_once(__DIR__ . '/classes/configtable.php');

// Access only with valid login
require_once(__DIR__ . '/../../adm_program/system/login_valid.php');

//$scriptName ist der Name wie er im Menue eingetragen werden muss, also ohne evtl. vorgelagerte Ordner wie z.B. /playground/adm_plugins/inventory_manager...
$scriptName = substr($_SERVER['SCRIPT_NAME'], strpos($_SERVER['SCRIPT_NAME'], FOLDER_PLUGINS));

// only authorized user are allowed to start this module
if (!isUserAuthorized($scriptName)) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$sessionDefaults = array(
    'filter_string' => '',
    'filter_category' => '',
    'filter_keeper' => 0,
    'show_all' => false,
    'export_and_filter' => false
);

foreach ($sessionDefaults as $key => $default) {
    if (!isset($_SESSION['pInventoryManager'][$key])) {
        $_SESSION['pInventoryManager'][$key] = $default;
    }
}

$getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array(
    'defaultValue' => 'html',
    'validValues' => ['csv-ms', 'csv-oo', 'html', 'print', 'pdf', 'pdfl', 'xlsx']
));
$getFilterString = admFuncVariableIsValid($_GET, 'filter_string', 'string');
$getFilterCategory = admFuncVariableIsValid($_GET, 'filter_category', 'string');
$getFilterKeeper = admFuncVariableIsValid($_GET, 'filter_keeper', 'int');
$getShowAll = admFuncVariableIsValid($_GET, 'show_all', 'bool', array('defaultValue' => false));
$getExportAndFilter = admFuncVariableIsValid($_GET, 'export_and_filter', 'bool', array('defaultValue' => false));
$getSameSide = admFuncVariableIsValid($_GET, 'same_side', 'bool', array('defaultValue' => false));

if ($getSameSide) {
    $_SESSION['pInventoryManager'] = array(
        'filter_string' => $getFilterString,
        'filter_category' => $getFilterCategory,
        'filter_keeper' => $getFilterKeeper,
        'show_all' => $getShowAll,
        'export_and_filter' => $getExportAndFilter
    );
}
else {
    $getFilterString = $_SESSION['pInventoryManager']['filter_string'];
    $getFilterCategory = $_SESSION['pInventoryManager']['filter_category'];
    $getFilterKeeper = $_SESSION['pInventoryManager']['filter_keeper'];
    $getShowAll = $_SESSION['pInventoryManager']['show_all'];
    $getExportAndFilter = $_SESSION['pInventoryManager']['export_and_filter'];
}

$pPreferences = new CConfigTablePIM();
$pPreferences->checkForUpdate() ? $pPreferences->init() : $pPreferences->read();

// initialize some special mode parameters
$separator = '';
$valueQuotes = '';
$charset = '';
$classTable = '';
$orientation = '';
$filename = umlautePIM($pPreferences->config['Optionen']['file_name']);
if ($pPreferences->config['Optionen']['add_date']) {
    $filename .= '_' . date('Y-m-d');
}

$modeSettings = array(
    'csv-ms' => array('csv', ';', '"', 'iso-8859-1', null, null),   //mode, seperator, valueQuotes, charset, classTable, orientation
    'csv-oo' => array('csv', ',', '"', 'utf-8', null , null),
    'pdf' => array('pdf', null, null, null, 'table', 'P'),
    'pdfl' => array('pdf', null, null, null, 'table', 'L'),
    'html' => array('html', null, null, null, 'table table-condensed', null),
    'print' => array('print', null, null, null, 'table table-condensed table-striped', null),
    'xlsx' => array('xlsx', null, null, null, null, null)
);

if (isset($modeSettings[$getMode])) {
    [$getMode, $separator, $valueQuotes, $charset, $classTable, $orientation] = $modeSettings[$getMode];
    if ($getMode === 'xlsx') {
        include_once(__DIR__ . '/libs/PHP_XLSXWriter/xlsxwriter.class.php');
    }
}

// Array for valid columns visible for current user.
// Needed for PDF export to set the correct colspan for the layout
// Maybe there are hidden fields.
$arrValidColumns = array();

$csvStr         = '';                   // CSV file as string
$header         = array();              //'xlsx'
$rows           = array();              //'xlsx'
$strikethroughs = array();              //'xlsx'

$items = new CItems($gDb, $gCurrentOrgId);
$items->showFormerItems($getShowAll);
$items->readItems($gCurrentOrgId);

$user = new User($gDb, $gProfileFields);

// define title (html) and headline
$title = $gL10n->get('PLG_INVENTORY_MANAGER_INVENTORY_MANAGER');
$headline = $gL10n->get('PLG_INVENTORY_MANAGER_INVENTORY_MANAGER');

// if html mode and last url was not a list view then save this url to navigation stack
if ($gNavigation->count() === 0 || ($getMode == 'html' && strpos($gNavigation->getUrl(), 'inventory_manager.php') === false))             
{
    $gNavigation->addStartUrl(CURRENT_URL, $headline, 'fa-key');
}

$datatable = false;
$hoverRows = false;

switch ($getMode) {
    case 'csv':
    case 'xlsx':
        break;  // don't show HtmlTable
    case 'print':
        // create html page object without the custom theme files
        $page = new HtmlPage('plg-inventory-manager-main-print');
        $page->setPrintMode();
        $page->setTitle($title);
        $page->setHeadline($headline);
        $table = new HtmlTable('adm_inventory_table', $page, $hoverRows, $datatable, $classTable);
        break;
    case 'pdf':
        $pdf = new TCPDF($orientation, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Admidio');
        $pdf->SetTitle($headline);

        // remove default header/footer
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(false);
        // set header and footer fonts
        $pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

        // set auto page breaks
        $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
        $pdf->SetMargins(10, 20, 10);
        $pdf->setHeaderMargin(10);
        $pdf->setFooterMargin(0);

        // headline for PDF
        $pdf->setHeaderData('', 0, $headline, '');

        // set font
        $pdf->SetFont('times', '', 10);

        // add a page
        $pdf->AddPage();

        // Create table object for display
        $table = new HtmlTable('adm_inventory_table', null, $hoverRows, $datatable, $classTable);
        $table->addAttribute('border', '1');
        break;
    case 'html':
        $datatable = !$getExportAndFilter;
        $hoverRows = true;

        $inputFilterStringLabel = '<i class="fas fa-search" alt="'.$gL10n->get('PLG_INVENTORY_MANAGER_GENERAL').'" title="'.$gL10n->get('PLG_INVENTORY_MANAGER_GENERAL').'"></i>';
        $selectBoxCategoryLabel ='<i class="fas fa-list" alt="'.$gL10n->get('PLG_INVENTORY_MANAGER_CATEGORY').'" title="'.$gL10n->get('PLG_INVENTORY_MANAGER_ICATEGORY').'"></i>';
        $selectBoxKeeperLabel = '<i class="fas fa-user" alt="'.$gL10n->get('PLG_INVENTORY_MANAGER_KEEPER').'" title="'.$gL10n->get('PLG_INVENTORY_MANAGER_KEEPER').'"></i>';
        
        // create html page object
        $page = new HtmlPage('plg-inventory-manager-main-html');
        $page->setTitle($title);
        $page->setHeadline($headline);

        $page->addJavascript('
            $("#filter_category").change(function () {
                self.location.href = "'.SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/inventory_manager.php', array(
                    'mode'              => 'html',
                    'filter_string'     => $getFilterString,
                    'filter_keeper'   => $getFilterKeeper,
                    'export_and_filter' => $getExportAndFilter,
                    'same_side'         => true,
                    'show_all'          => $getShowAll
                )) . '&filter_category=" + $(this).val();
            });
            $("#filter_keeper").change(function () {
                self.location.href = "'.SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/inventory_manager.php', array(
                    'mode'              => 'html',
                    'filter_string'     => $getFilterString,
                    'filter_category'   => $getFilterCategory,
                    'export_and_filter' => $getExportAndFilter,
                    'same_side'         => true,
                    'show_all'          => $getShowAll
                )) . '&filter_keeper=" + $(this).val();
            });
            $("#menu_item_lists_print_view").click(function() {
                window.open("'.SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/inventory_manager.php', array(
                    'filter_string'     => $getFilterString, 
                    'filter_category'   => $getFilterCategory, 
                    'filter_keeper'   => $getFilterKeeper,
                    'export_and_filter' => $getExportAndFilter,                   
                    'show_all'          => $getShowAll,  
                    'mode'              => 'print'
                )) . '", "_blank");
            });
            $("#export_and_filter").change(function() {
                $("#navbar_birthdaylist_form").submit();
            });
            $("#show_all").change(function() {
                $("#navbar_birthdaylist_form").submit();
            });
            $("#filter_string").change(function() {
                $("#navbar_birthdaylist_form").submit();
            });
        ', true);

        if ($getExportAndFilter) {
            // links to print and exports
            $page->addPageFunctionsMenuItem('menu_item_lists_print_view', $gL10n->get('SYS_PRINT_PREVIEW'), 'javascript:void(0);', 'fa-print');
            $page->addPageFunctionsMenuItem('menu_item_lists_export', $gL10n->get('SYS_EXPORT_TO'), '#', 'fa-file-download');
            $page->addPageFunctionsMenuItem('menu_item_lists_xlsx', $gL10n->get('SYS_MICROSOFT_EXCEL').' (XLSX)',
                SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/inventory_manager.php', array(
                    'filter_string'     => $getFilterString,
                    'filter_category'   => $getFilterCategory,
                    'filter_keeper'   => $getFilterKeeper,
                    'export_and_filter' => $getExportAndFilter,
                    'show_all'          => $getShowAll,
                    'mode'              => 'xlsx')),
                'fa-file-excel', 'menu_item_lists_export');
            $page->addPageFunctionsMenuItem('menu_item_lists_csv_ms', $gL10n->get('SYS_MICROSOFT_EXCEL').' (CSV)',
                SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/inventory_manager.php', array(
                    'filter_string'     => $getFilterString,
                    'filter_category'   => $getFilterCategory,
                    'filter_keeper'   => $getFilterKeeper,
                    'export_and_filter' => $getExportAndFilter,
                    'show_all'          => $getShowAll,
                    'mode'              => 'csv-ms')),
                'fa-file-excel', 'menu_item_lists_export');
            $page->addPageFunctionsMenuItem('menu_item_lists_pdf', $gL10n->get('SYS_PDF').' ('.$gL10n->get('SYS_PORTRAIT').')',
                SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/inventory_manager.php', array(
                    'filter_string'     => $getFilterString,
                    'filter_category'   => $getFilterCategory,
                    'filter_keeper'   => $getFilterKeeper,
                    'export_and_filter' => $getExportAndFilter,
                    'show_all'          => $getShowAll,
                    'mode'              => 'pdf')),
                'fa-file-pdf', 'menu_item_lists_export');
            $page->addPageFunctionsMenuItem('menu_item_lists_pdfl', $gL10n->get('SYS_PDF').' ('.$gL10n->get('SYS_LANDSCAPE').')',
                SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/inventory_manager.php', array(
                    'filter_string'     => $getFilterString,
                    'filter_category'   => $getFilterCategory,
                    'filter_keeper'   => $getFilterKeeper,
                    'export_and_filter' => $getExportAndFilter,
                    'show_all'          => $getShowAll,
                    'mode'              => 'pdfl')),
                'fa-file-pdf', 'menu_item_lists_export');
            $page->addPageFunctionsMenuItem('menu_item_lists_csv', $gL10n->get('SYS_CSV').' ('.$gL10n->get('SYS_UTF8').')',
                SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/inventory_manager.php', array(
                    'filter_string'     => $getFilterString,
                    'filter_category'   => $getFilterCategory,
                    'filter_keeper'   => $getFilterKeeper,
                    'export_and_filter' => $getExportAndFilter,
                    'show_all'          => $getShowAll,
                    'mode'              => 'csv-oo')),
                'fa-file-csv', 'menu_item_lists_export');
        }
        
        if (isUserAuthorizedForPreferences()) {
            $page->addPageFunctionsMenuItem('menu_preferences', $gL10n->get('SYS_SETTINGS'), SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/preferences.php'),  'fa-cog');
            $page->addPageFunctionsMenuItem('itemcreate_form_btn', $gL10n->get('PLG_INVENTORY_MANAGER_ITEM_CREATE'), SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/items_edit_new.php', array('item_id' => 0)), 'fas fa-plus-circle');
        } 
        
        // create filter menu with elements for role
        $filterNavbar = new HtmlNavbar('navbar_filter', '', null, 'filter');
        $form = new HtmlForm('navbar_birthdaylist_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/inventory_manager.php', array('headline' => $headline)), $page, array('type' => 'navbar', 'setFocus' => false));
        
        if ($getExportAndFilter) {  
            $form->addInput('filter_string', $inputFilterStringLabel, $getFilterString);
        
            $getItemId = admFuncVariableIsValid($_GET, 'item_id', 'int');
            $items2 = new CItems($gDb, $gCurrentOrgId);
            $items2->readItemData($getItemId, $gCurrentOrgId);
            foreach ($items2->mItemFields as $itemField) {  
                $imfNameIntern = $itemField->getValue('imf_name_intern');
          
                if ($items2->getProperty($imfNameIntern, 'imf_type') === 'DROPDOWN') {
                    $arrListValues = $items2->getProperty($imfNameIntern, 'imf_value_list');
                    $defaultValue  = $items2->getValue($imfNameIntern, 'database');
            
                    $form->addSelectBox(
                        'filter_category',
                        $selectBoxCategoryLabel,
                        $arrListValues,
                        array(
                            'defaultValue'    => $getFilterCategory,
                            'showContextDependentFirstEntry' => true
                        )
                    );
                }
            }
        
            // read all keeper
            $sql = 'SELECT DISTINCT imd_value, CONCAT_WS(\', \', last_name.usd_value, first_name.usd_value) FROM '.TBL_INVENTORY_MANAGER_DATA.'
                    INNER JOIN '.TBL_INVENTORY_MANAGER_FIELDS.'
                        ON imf_id = imd_imf_id
                    LEFT JOIN '. TBL_USER_DATA. ' as last_name
                        ON last_name.usd_usr_id = imd_value
                        AND last_name.usd_usf_id = '. $gProfileFields->getProperty('LAST_NAME', 'usf_id'). '
                    LEFT JOIN '. TBL_USER_DATA. ' as first_name
                        ON first_name.usd_usr_id = imd_value
                        AND first_name.usd_usf_id = '. $gProfileFields->getProperty('FIRST_NAME', 'usf_id'). '
                    WHERE (imf_org_id  = '. $gCurrentOrgId .'
                        OR imf_org_id IS NULL)
                    AND imf_name_intern = \'KEEPER\'
                    ORDER BY CONCAT_WS(\', \', last_name.usd_value, first_name.usd_value) ASC;';
            $form->addSelectBoxFromSql('filter_keeper',$selectBoxKeeperLabel, $gDb, $sql, array('defaultValue' => $getFilterKeeper , 'showContextDependentFirstEntry' => true));
        }
        else {
            $form->addInput('filter_string', '', $getFilterString, array('property' => HtmlForm::FIELD_HIDDEN));
            $form->addInput('filter_category', '', $getFilterCategory, array('property' => HtmlForm::FIELD_HIDDEN));
            $form->addInput('filter_keeper', '', $getFilterKeeper, array('property' => HtmlForm::FIELD_HIDDEN));          
        }
 
        $form->addCheckbox('show_all', $gL10n->get('PLG_INVENTORY_MANAGER_SHOW_ALL_ITEMS'), $getShowAll);                           
        $form->addCheckbox('export_and_filter', $gL10n->get('PLG_INVENTORY_MANAGER_EXPORT_AND_FILTER'), $getExportAndFilter);
        $form->addInput('same_side', '', '1', array('property' => HtmlForm::FIELD_HIDDEN));
        $filterNavbar->addForm($form->show());
        
        $page->addHtml($filterNavbar->show());        

        $table = new HtmlTable('adm_inventory_table', $page, $hoverRows, $datatable, $classTable);
        if ($datatable) {
            // ab Admidio 4.3 verursacht setDatatablesRowsPerPage, wenn $datatable "false" ist, folgenden Fehler:
            // "Fatal error: Uncaught Error: Call to a member function setDatatablesRowsPerPage() on null"
            $table->setDatatablesRowsPerPage($gSettingsManager->getInt('groups_roles_members_per_page'));
        }
        break;
    default:
        $table = new HtmlTable('adm_inventory_table', $page, $hoverRows, $datatable, $classTable);
        break;
}

// initialize array parameters for table and set the first column for the counter
$columnAlign  = ($getMode == 'html') ? array('left') : array('center');
$columnValues = array($gL10n->get('SYS_ABR_NO'));

// headlines for columns
$columnNumber = 1;

foreach ($items->mItemFields as $itemField) {
    $imfNameIntern = $itemField->getValue('imf_name_intern');
    $columnHeader = convlanguagePIM($items->getProperty($imfNameIntern, 'imf_name'));

    switch ($items->getProperty($imfNameIntern, 'imf_type')) {
        case 'CHECKBOX':
        case 'RADIO_BUTTON':
        case 'GENDER':
            $columnAlign[] = 'center';
            break;
        case 'NUMBER':
        case 'DECIMAL':
            $columnAlign[] = 'right';
            break;
        default:
            $columnAlign[] = 'left';
            break;
    }

    if ($getMode == 'csv' && $columnNumber === 1) {
        $csvStr .= $valueQuotes . $gL10n->get('SYS_ABR_NO') . $valueQuotes;
    }

    if ($getMode == 'pdf' && $columnNumber === 1) {
        $arrValidColumns[] = $gL10n->get('SYS_ABR_NO');
    }

    if ($getMode == 'xlsx' && $columnNumber === 1) {
        $header[$gL10n->get('SYS_ABR_NO')] = 'string';
    }

    switch ($getMode) {
        case 'csv':
            $csvStr .= $separator . $valueQuotes . $columnHeader . $valueQuotes;
            break;
        case 'xlsx':
            $header[$columnHeader] = 'string';
            break;
        case 'pdf':
            $arrValidColumns[] = $columnHeader;
            break;
        case 'html':
        case 'print':
            $columnValues[] = $columnHeader;
            break;
    }

    $columnNumber++;
}

if ($getMode == 'html') {
    $columnAlign[]  = 'center';
    $columnValues[] = '&nbsp;';
    if ($datatable) {
        $table->disableDatatablesColumnsSort(array(count($columnValues)));
    }
}

if ($getMode == 'csv') {
    $csvStr .= "\n";
}
elseif ($getMode == 'html' || $getMode == 'print') {
    $table->setColumnAlignByArray($columnAlign);
    $table->addRowHeadingByArray($columnValues);
}
elseif ($getMode == 'pdf') {
    $table->setColumnAlignByArray($columnAlign);
    $table->addTableHeader();
    $table->addRow();
    $table->addAttribute('align', 'center');
    $table->addColumn($headline, array('colspan' => count($arrValidColumns)));
    $table->addRow();
    foreach ($arrValidColumns as $column) {
        $table->addColumn($column, array('style' => 'text-align: ' . $columnAlign[$column] . ';font-size:14;background-color:#C7C7C7;'), 'th');
    }
}

$listRowNumber = 1;

foreach ($items->items as $item) {
    $tmp_csv = '';
    $items->readItemData($item['imi_id'], $gCurrentOrgId);
    $columnValues = array();
    $strikethrough = $item['imi_former'];
    $columnNumber = 1;

    foreach ($items->mItemFields as $itemField) {
        $imfNameIntern = $itemField->getValue('imf_name_intern');

        if ($getExportAndFilter && (
            ($getFilterCategory !== '' && $imfNameIntern == 'CATEGORY' && $getFilterCategory != $items->getValue($imfNameIntern, 'database')) ||
            ($getFilterKeeper !== 0 && $imfNameIntern == 'KEEPER' && $getFilterKeeper != $items->getValue($imfNameIntern))
        )) {
            continue 2;
        }

        if ($columnNumber === 1) {
            $columnValues[] = $listRowNumber;
            $tmp_csv .= $valueQuotes . $listRowNumber . $valueQuotes;
        }

        $content = $items->getValue($imfNameIntern, 'database');

        if ($imfNameIntern == 'KEEPER' && strlen($content) > 0) {
            $user->readDataById($content);
            $content = ($getMode == 'html') ?
                '<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', array('user_uuid' => $user->getValue('usr_uuid'))) . '">' . $user->getValue('LAST_NAME') . ', ' . $user->getValue('FIRST_NAME') . '</a>' :
                $user->getValue('LAST_NAME') . ', ' . $user->getValue('FIRST_NAME');
        }

        if ($imfNameIntern == 'ITEMNAME' && $getMode == 'html') {
            $content = '<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER . '/items_edit_new.php', array('item_id' => $item['imi_id'])) . '">' . $content . '</a>';
        }

        if ($items->getProperty($imfNameIntern, 'imf_type') == 'CHECKBOX') {
            $content = ($content != 1) ? 0 : 1;
            $content = ($getMode == 'csv' || $getMode == 'pdf' || $getMode == 'xlsx') ?
                ($content == 1 ? $gL10n->get('SYS_YES') : $gL10n->get('SYS_NO')) :
                $items->getHtmlValue($imfNameIntern, $content);
        }
        elseif ($items->getProperty($imfNameIntern, 'imf_type') == 'DATE') {
            $content = $items->getHtmlValue($imfNameIntern, $content);
        }
        elseif (in_array($items->getProperty($imfNameIntern, 'imf_type'), array('DROPDOWN', 'RADIO_BUTTON'))) {
            $content = ($getMode == 'csv') ?
                $items->getProperty($imfNameIntern, 'imf_value_list', 'text')[$content] :
                $items->getHtmlValue($imfNameIntern, $content);
        }

        if ($getMode == 'csv') {
            $tmp_csv .= $separator . $valueQuotes . $content . $valueQuotes;
        }
        else {
            $columnValues[] = ($strikethrough && $getMode != 'xlsx') ? '<s>' . $content . '</s>' : $content;
        }

        $columnNumber++;
    }

    if ($getMode == 'html') {
        $tempValue = '';
        if ($pPreferences->isPffInst()) {
            $tempValue .= '<a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . '/' . PLUGIN_FOLDER . '/items_export_to_pff.php', array('item_id' => $item['imi_id'])) . '">
                               <i class="fas fa-print" title="' . $gL10n->get('PLG_INVENTORY_MANAGER_ITEM_PRINT') . '"></i>
                           </a>';
        }
        if (isUserAuthorizedForPreferences()) {
            $tempValue .= '<a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . '/' . PLUGIN_FOLDER . '/items_delete.php', array('item_id' => $item['imi_id'], 'item_former' => $item['imi_former'])) . '">
                               <i class="fas fa-minus-circle" title="' . $gL10n->get('PLG_INVENTORY_MANAGER_ITEM_DELETE') . '"></i>
                           </a>';
        }
        $columnValues[] = $tempValue;
    }

    $showRow = ($getFilterString == '') ? true : false;

    if ($getExportAndFilter && $getFilterString !== '') {
        $showRowException = false;
        $filterArray = explode(',', $getFilterString);
        foreach ($filterArray as $filterString) {
            $filterString = trim($filterString);
            if (substr($filterString, 0, 1) == '-') {
                $filterString = substr($filterString, 1);
                if (stristr(implode('', $columnValues), $filterString) || stristr($tmp_csv, $filterString)) {
                    $showRowException = true;
                }
            }
            if (stristr(implode('', $columnValues), $filterString) || stristr($tmp_csv, $filterString)) {
                $showRow = true;
            }
        }
        if ($showRowException) {
            $showRow = false;
        }
    }

    if ($showRow) {
        switch ($getMode) {
            case 'csv':
                $csvStr .= $tmp_csv . "\n";
                break;
            case 'xlsx':
                $rows[] = $columnValues;
                $strikethroughs[] = $strikethrough;
                break;
            default:
                $table->addRowByArray($columnValues, '', array('nobr' => 'true'));
                break;
        }
    }

    ++$listRowNumber;
}

if (in_array($getMode, array('csv', 'pdf', 'xlsx'))) {
    $filename = FileSystemUtils::getSanitizedPathEntry($filename) . '.' . $getMode;
    if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false) {
        $filename = urlencode($filename);
    }
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private');
    header('Pragma: public');
}

switch ($getMode) {
    case 'csv':
        header('Content-Type: text/comma-separated-values; charset=' . $charset);
        echo ($charset == 'iso-8859-1') ? mb_convert_encoding($csvStr, 'ISO-8859-1', 'UTF-8') : $csvStr;
        break;
    case 'pdf':
        $pdf->writeHTML($table->getHtmlTable(), true, false, true);
        $file = ADMIDIO_PATH . FOLDER_DATA . '/temp/' . $filename;
        $pdf->Output($file, 'F');
        header('Content-Type: application/pdf');
        readfile($file);
        ignore_user_abort(true);
        try {
            FileSystemUtils::deleteFileIfExists($file);
        } catch (\RuntimeException $exception) {
            $gLogger->error('Could not delete file!', array('filePath' => $file));
        }
        break;
    case 'xlsx':
        header('Content-disposition: attachment; filename="' . XLSXWriter::sanitize_filename($filename) . '"');
        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        $writer = new XLSXWriter();
        $writer->setAuthor($gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME'));
        $writer->setTitle($filename);
        $writer->setSubject($gL10n->get('PLG_INVENTORY_MANAGER_ITEMLIST'));
        $writer->setCompany($gCurrentOrganization->getValue('org_longname'));
        $writer->setKeywords(array($gL10n->get('PLG_INVENTORY_MANAGER_NAME_OF_PLUGIN'), $gL10n->get('PLG_INVENTORY_MANAGER_ITEM')));
        $writer->setDescription($gL10n->get('PLG_INVENTORY_MANAGER_CREATED_WITH'));
        $writer->writeSheetHeader('Sheet1', $header);
        for ($i = 0; $i < count($rows); $i++) {
            $writer->writeSheetRow('Sheet1', $rows[$i], $strikethroughs[$i] ? array('font-style' => 'strikethrough') : array());
        }
        $writer->writeToStdOut();
        break;
    case 'html':
        if ($getExportAndFilter) {
            $page->addHtml('<div style="width:100%; height: 500px; overflow:auto; border:20px;">');
            $page->addHtml($table->show(false));
            $page->addHtml('</div><br/>');
        } else {
            $page->addHtml($table->show(false));
        }
        $page->show();
        break;
    case 'print':
        $page->addHtml($table->show(false));
        $page->show();
        break;
}
