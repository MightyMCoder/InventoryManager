<?php
/**
 ***********************************************************************************************
 * Script to check for updates of the InventoryManager plugin
 *
 * @see         https://github.com/MightyMCoder/InventoryManager/ The InventoryManager GitHub project
 * @author      MightyMCoder
 * @copyright   2024 - today MightyMCoder
 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 only
 *
 * Parameters:
 *
 * mode             : 1 - (Default) check availability of updates
 *                    2 - Show results of update check
 * PIMVersion       : The current version of the InventoryManager plugin
 * PIMBetaVersion   : The current beta version of the InventoryManager plugin
 * 
 * methods:
 * getLatestReleaseVersion($owner, $repo)           : Get the latest release version of the InventoryManager plugin
 * getLatestBetaReleaseVersion($owner, $repo)       : Get the latest beta release version of the InventoryManager plugin
 * checkVersion(string $currentVersion, string $checkStableVersion, string $checkBetaVersion, string $betaRelease,
 *                                string $betaFlag) : Check the current version of the InventoryManager plugin and compare
 *                                                    it with the latest stable and beta release versions
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../../adm_program/system/common.php');
require_once(__DIR__ . '/../common_function.php');

// Initialize and check the parameters
$getMode = admFuncVariableIsValid($_GET, 'mode', 'int', array('defaultValue' => 1, 'directOutput' => true));
$PIMVersion = admFuncVariableIsValid($_GET, 'PIMVersion', 'string', array('defaultValue' => "n/a", 'directOutput' => true));
$PIMBetaVersion = admFuncVariableIsValid($_GET, 'PIMBetaVersion', 'string', array('defaultValue' => "n/a", 'directOutput' => true));

// only authorized user are allowed to start this module
if (!isUserAuthorizedForPreferences()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

/**
 * This function checks the GitHub repository for the latest release version
 * of the InventoryManager plugin. It fetches the release information using
 * GitHub's API and returns the version number of the latest release.
 *
 * @param string $owner The owner of the repository.
 * @param string $repo The name of the repository.
 * @return array An array containing the version number and URL of the latest release.
 */
function getLatestReleaseVersion($owner, $repo) {
    $url = "https://api.github.com/repos/$owner/$repo/releases/latest";

    $options = [
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: PHP'  // GitHub benötigt diesen Header
            ]
        ]
    ];

    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);

    if ($response === FALSE) {
        return ['version' => 'n/a', 'url' => ''];
    }

    $data = json_decode($response, true);
    return isset($data['tag_name']) ? ['version' => ltrim($data['tag_name'], 'v'), 'url' => $data['html_url']] : ['version' => 'n/a', 'url' => ''];
}

/**
 * This function checks the GitHub repository for the latest beta release version
 * of the InventoryManager plugin. It fetches the release information using
 * GitHub's API and returns the version number of the latest beta release.
 *
 * @param string $owner The owner of the repository.
 * @param string $repo The name of the repository.
 * @return array An array containing the version number, release name and URL of the latest beta release.
 */
function getLatestBetaReleaseVersion($owner, $repo) {
    $url = "https://api.github.com/repos/$owner/$repo/releases";

    $options = [
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: PHP'  // Github requires this header
            ]
        ]
    ];

    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);

    if ($response === FALSE) {
        return ['version' => 'n/a', 'release' => '', 'url' => ''];
    }

    $data = json_decode($response, true);
    foreach ($data as $release) {
        if ($release['prerelease']) {
            return ['version' => ltrim($data['tag_name'], 'v'), 'release' => $release['name'], 'url' => $data['html_url']];
        }
    }

    return ['version' => 'n/a', 'release' => '', 'url' => ''];
}

/**
 * This function checks the current version of the InventoryManager plugin
 * and compares it with the latest stable and beta release versions.
 * It returns an integer value indicating the update state.
 *
 * @param string $currentVersion The current version of the plugin.
 * @param string $checkStableVersion The latest stable release version.
 * @param string $checkBetaVersion The latest beta release version.
 * @param string $betaRelease The name of the latest beta release.
 * @param string $betaFlag The current beta version of the plugin.
 * @return int An integer value indicating the update state.
 */
function checkVersion(string $currentVersion, string $checkStableVersion, string $checkBetaVersion, string $betaRelease, string $betaFlag): int
{
    // Update state (0 = No update, 1 = New stable version, 2 = New beta version, 3 = New stable + beta version)
    $update = 0;

    // Zunächst auf stabile Version prüfen
    if (version_compare($checkStableVersion, $currentVersion, '>')) {
        $update = 1;
    }

    // Check for beta version now
    $status = version_compare($checkBetaVersion, $currentVersion);
    if ($status === 1 || ($status === 0 && version_compare($betaRelease, $betaFlag, '>'))) {
        if ($update === 1) {
            $update = 3;
        } else {
            $update = 2;
        }
    }

    return $update;
}

// Repository information
$owner = 'MightyMCoder';
$repo = 'InventoryManager';

$stableInfo = getLatestReleaseVersion($owner, $repo);
$betaInfo = getLatestBetaReleaseVersion($owner, $repo);

$stableVersion = $stableInfo['version'];
$stableURL = $stableInfo['url'];

$betaVersion = $betaInfo['version'];
$betaRelease = $betaInfo['release'];
$betaURL = $betaInfo['url'];

// No stable version available (actually impossible)
if ($stableVersion === '') {
    $stableVersion = 'n/a';
}

// No beta version available
if ($betaVersion === '') {
    $betaVersion = 'n/a';
    $betaRelease = '';
}

// check for update
$versionUpdate = checkVersion($PIMVersion, $stableVersion, $betaVersion, $betaRelease, $PIMBetaVersion);


// Only continues in display mode, otherwise the current update state can be
// queried in the $versionUpdate variable.
// $versionUpdate (0 = No update, 1 = New stable version, 2 = New beta version, 3 = New stable + beta version, 99 = No connection)
if ($getMode === 2) {
    // show update result
    if ($versionUpdate === 1) {
        $versionsText = $gL10n->get('SYS_NEW_VERSION_AVAILABLE');
    } elseif ($versionUpdate === 2) {
        $versionsText = $gL10n->get('SYS_NEW_BETA_AVAILABLE');
    } elseif ($versionUpdate === 3) {
        $versionsText = $gL10n->get('SYS_NEW_BOTH_AVAILABLE');
    } elseif ($versionUpdate === 99) {
        $PIMGitHubLink = '<a href="https://github.com/'.$owner.'/'.$repo.'/releases" target="_blank">InventoryManager</a>';
        $versionsText = $gL10n->get('PLG_INVENTORY_MANAGER_CONNECTION_ERROR', array($PIMGitHubLink));
    } else {
        $versionsTextBeta = '';
        if ($PIMBetaVersion !== 'n/a') {
            $versionsTextBeta = 'Beta ';
        }

        $versionsText = $gL10n->get('PLG_INVENTORY_MANAGER_USING_CURRENT_VERSION', array($versionsTextBeta));
    }

    echo '
        <p>' . $gL10n->get('SYS_INSTALLED') . ':&nbsp;' . $PIMVersion . '</p>
        <p>' . $gL10n->get('SYS_AVAILABLE') . ':&nbsp;
            <a class="btn" href="'.$stableURL.'" title="' . $gL10n->get('PLG_INVENTORY_MANAGER_DOWNLOAD_PAGE') . '" target="_blank">'.
                '<i class="fas fa-link"></i>' . $stableVersion . '
            </a>
            <br />
            ' . $gL10n->get('SYS_AVAILABLE_BETA') . ': &nbsp;';

    if ($versionUpdate !== 99 && $betaVersion !== 'n/a') {
        echo '
            <a class="btn" href="'.$betaURL.'" title="' . $gL10n->get('PLG_INVENTORY_MANAGER_DOWNLOAD_PAGE') . '" target="_blank">'.
                '<i class="fas fa-link"></i>' . $betaVersion . ' Beta ' . $betaRelease . '
            </a>';
    } else {
        echo $betaVersion;
    }

    echo '
        </p>
        <strong>' . $versionsText . '</strong>';
}
