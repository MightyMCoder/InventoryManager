<?php

/**
 ***********************************************************************************************
 * Prepare values of import form for further processing
 *
 * @see         https://github.com/MightyMCoder/InventoryManager/ The InventoryManager GitHub project
 * @author      MightyMCoder
 * @copyright   2024 - today MightyMCoder
 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 only
 ***********************************************************************************************
 */

// PhpSpreadsheet namespaces
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Html;
use PhpOffice\PhpSpreadsheet\Reader\Ods;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

require_once(__DIR__ . '/../../../adm_program/system/common.php');
require_once(__DIR__ . '/../common_function.php');

// Access only with valid login
require_once(__DIR__ . '/../../../adm_program/system/login_valid.php');

// Initialize and check the parameters
$postImportFormat = admFuncVariableIsValid(
    $_POST,
    'format',
    'string',
    array('requireValue' => true,
        'validValues' => array('AUTO', 'XLSX', 'XLS', 'ODS', 'CSV', 'HTML'))
);
$postImportCoding = admFuncVariableIsValid(
    $_POST,
    'import_coding',
    'string',
    array('validValues' => array('', 'GUESS', 'UTF-8', 'UTF-16BE', 'UTF-16LE', 'UTF-32BE', 'UTF-32LE', 'CP1252', 'ISO-8859-1'))
);
$postSeparator = admFuncVariableIsValid(
    $_POST,
    'import_separator',
    'string',
    array('validValues' => array('', ',', ';', '\t', '|'))
);
$postEnclosure = admFuncVariableIsValid(
    $_POST,
    'import_enclosure',
    'string',
    array('validValues' => array('', 'AUTO', '"', '\|'))
);

$postWorksheet = admFuncVariableIsValid($_POST, 'import_sheet', 'string');

try {
    // check the CSRF token of the form against the session token
    SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
} catch (AdmException $exception) {
    $exception->showHtml();
    // => EXIT
}

// only authorized user are allowed to start this module
if (!isUserAuthorizedForPreferencesPIM()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$importfile = $_FILES['userfile']['tmp_name'][0];
if (strlen($importfile) === 0) {
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_FILE'))));
    // => EXIT
} elseif ($_FILES['userfile']['error'][0] === UPLOAD_ERR_INI_SIZE) {
    // check the filesize against the server settings
    $gMessage->show($gL10n->get('SYS_FILE_TO_LARGE_SERVER', array(ini_get('upload_max_filesize'))));
    // => EXIT
} elseif (!file_exists($importfile) || !is_uploaded_file($importfile)) {
    // check if a file was really uploaded
    $gMessage->show($gL10n->get('SYS_FILE_NOT_EXIST'));
    // => EXIT
}

switch ($postImportFormat) {
    case 'XLSX':
        $reader = new Xlsx();
        break;

    case 'XLS':
        $reader = new Xls();
        break;

    case 'ODS':
        $reader = new Ods();
        break;

    case 'CSV':
        $reader = new Csv();
        if ($postImportCoding === 'GUESS') {
            $postImportCoding = Csv::guessEncoding($importfile);
        } elseif ($postImportCoding === '') {
            $postImportCoding = 'UTF-8';
        }
        $reader->setInputEncoding($postImportCoding);

        if ($postSeparator != '') {
            $reader->setDelimiter($postSeparator);
        }

        if ($postEnclosure != 'AUTO') {
            $reader->setEnclosure($postEnclosure);
        }
        break;

    case 'HTML':
        $reader = new Html();
        break;

    case 'AUTO':
    default:
        $reader = IOFactory::createReaderForFile($importfile);
        break;
}

// TODO: Better error handling if file cannot be loaded (phpSpreadsheet apparently does not always use exceptions)
if (isset($reader) and !is_null($reader)) {
    try {
        $spreadsheet = $reader->load($importfile);
        // Read specified sheet (passed as argument/param)
        if (is_numeric($postWorksheet)) {
            $sheet = $spreadsheet->getSheet($postWorksheet);
        } elseif (!empty($postWorksheet)) {
            $sheet = $spreadsheet->getSheetByName($postWorksheet);
        } else {
            $sheet = $spreadsheet->getActiveSheet();
        }

        if (empty($sheet)) {
            $gMessage->show($gL10n->get('SYS_IMPORT_SHEET_NOT_EXISTS', array($postWorksheet)));
            // => EXIT
        } else {
            // read data to array without any format
            $_SESSION['import_data'] = $sheet->toArray(null, true, false);
        }
    } catch (\PhpOffice\PhpSpreadsheet\Exception|Exception $e) {
        $gMessage->show($e->getMessage());
        // => EXIT
    }
}

admRedirect(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_IM . '/import/import_column_config.php');
// => EXIT
