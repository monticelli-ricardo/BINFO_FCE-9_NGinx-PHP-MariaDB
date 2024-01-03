<?php
//-----------------------------------------------------------------------------------------------------------
// Initial Script configuration
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('memory_limit', '1G'); 

//-----------------------------------------------------------------------------------------------------------
// Define constants
define('CSV_DIRECTORY', '/var/www/html/exercise2/csse_covid_19_daily_reports');
define('TEMP_DIRECTORY', '/var/www/html/exercise2/temp');

// Debugging: Output a timestamped message with a newline
function logMessage($message) {
    echo "[" . date("Y-m-d H:i:s") . "] " . $message . " <br>";
}

// Function to download a file from GitHub
function downloadFile($url, $destination)
{
    // Debugging: Output a timestamped message
    logMessage(" Downloading file: $url");

    // Download the file
    $fileContent = file_get_contents($url);

    if ($fileContent !== false) {
        file_put_contents($destination, $fileContent);
        // Debugging: Output a timestamped message
        logMessage(" File downloaded successfully: $destination");
    } else {
        // Debugging: Output a timestamped message
        logMessage(" Error downloading file: $url\n");
    }
}

// Function to deletea a directory content
function deleteDirectoryContents($dir) {
    if (!file_exists($dir) || !is_dir($dir)) {
        return false;
    }

    $files = array_diff(scandir($dir), array('.', '..'));

    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;

        if (is_dir($path)) {
            deleteDirectoryContents($path);
        } else {
            unlink($path);
        }
    }

    return true;
}

//-----------------------------------------------------------------------------------------------------------
// Optional Step: Clean up the space
    // Empty the directory
    if (deleteDirectoryContents(TEMP_DIRECTORY) && deleteDirectoryContents(CSV_DIRECTORY)) {
        logMessage("Directory:[ " . TEMP_DIRECTORY . " ] contents deleted successfully.\n");
        logMessage("Directory:[ " . CSV_DIRECTORY . " ] contents deleted successfully.\n");
        logMessage("Clean up complete.\n");
    } else {
        logMessage("Failed to delete the directory contents.\n");
    }

// Step 1: Create the temporary directory if it doesn't exist
    if (!file_exists(TEMP_DIRECTORY)) {
        mkdir(TEMP_DIRECTORY, 0755, true);
        // Debugging: Output a timestamped message
        logMessage(" Temporary directory created: " . TEMP_DIRECTORY);
    }

// Step 2: Download the master repository as a ZIP file
    $repoUrl = 'https://github.com/CSSEGISandData/COVID-19/archive/master.zip';
    $zipFile = TEMP_DIRECTORY . '/master.zip';
    downloadFile($repoUrl, $zipFile);

// Step 3: Unzip the master file
    $zip = new ZipArchive;
    if ($zip->open($zipFile) === TRUE) {
        $zip->extractTo(TEMP_DIRECTORY);
        $zip->close();
        // Debugging: Output a timestamped message
        logMessage(" Master repository extracted successfully.");
    } else {
        // Debugging: Output a timestamped message
        logMessage(" Failed to extract master repository.");
    }

// Step 4: Copy CSV files to a separate folder
    $sourcePath = TEMP_DIRECTORY . '/COVID-19-master/csse_covid_19_data/csse_covid_19_daily_reports/';
    $destinationPath = CSV_DIRECTORY;

// Create the destination directory if it doesn't exist
    if (!file_exists($destinationPath)) {
        mkdir($destinationPath, 0755, true);
    }

// Copy CSV files
    $csvFiles = glob($sourcePath . '*.csv');
    foreach ($csvFiles as $csvFile) {
        $destinationFile = $destinationPath . '/' . basename($csvFile);
        copy($csvFile, $destinationFile);
        // Debugging: Output a timestamped message
        logMessage("Copied CSV file: $destinationFile \n");
    }

// Step 5: Clean up the temp files except the Master zip file
    // Empty the directory
    if (deleteDirectoryContents(TEMP_DIRECTORY)) {
        logMessage("Directory:[ " . TEMP_DIRECTORY . " ] contents deleted successfully.\n");
        
        // Now, you can remove the directory itself
        if (rmdir(TEMP_DIRECTORY)) {
            logMessage("Directory:[ " . TEMP_DIRECTORY . " ] removed successfully.\n");
            logMessage("Clean up complete.\n");
        } else {
            logMessage("Failed to remove the temporal directory.\n");
        }
    } else {
        logMessage("Failed to delete the temporal directory contents.\n");
    }


//-----------------------------------------------------------------------------------------------------------
?>
