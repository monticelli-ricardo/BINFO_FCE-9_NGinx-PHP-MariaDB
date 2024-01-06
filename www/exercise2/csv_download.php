<?php
//-----------------------------------------------------------------------------------------------------------
// Initial Script configuration
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('memory_limit', '1G'); 

//-----------------------------------------------------------------------------------------------------------
// Define constants
define('CSV_DIRECTORY', '/shared_files');
define('TEMP_DIRECTORY', '/var/www/html/exercise2/temp');
$logFile = '/var/www/html/exercise2/logFile.log';     // Log file for debugging

// Set the date range
define('START_DATE','2021-01-01');
define('END_DATE', '2021-06-30');


// Debugging: Output a timestamped message with a newline
function logMessage($message) {
    echo "[" . date("Y-m-d H:i:s") . "] " . $message . " <br>";
    global $logFile;
    file_put_contents($logFile, "[" . date("Y-m-d H:i:s") . "] " . $message . "\n", FILE_APPEND);
}

// Function to download a file from GitHub
function downloadFile($url, $destination)
{
    // Debugging: Output a timestamped message
    logMessage("Downloading file: $url\n");

    // Download the master file
    $fileContent = file_get_contents($url);

    if ($fileContent !== false) {
        file_put_contents($destination, $fileContent);
        // Debugging: Output a timestamped message
        logMessage("File downloaded successfully: $destination\n");
    } else {
        // Debugging: Output a timestamped message
        logMessage("Error downloading file: $url\n");
    }
}

// Function to delete a directory content
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

// Function to delete a directory content
    function deleteDirectory($dir) {
        if (!file_exists($dir)) {
            return true;
        }

        if (!is_dir($dir)) {
            return unlink($dir);
        }

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }

        return rmdir($dir);
    }



//-----------------------------------------------------------------------------------------------------------
// Record the start time
$startTime = microtime(true);

// Optional Step: Clean up the space before we start
    // Empty the available directories if they exist
    if(file_exists(TEMP_DIRECTORY)){
        if (deleteDirectoryContents(TEMP_DIRECTORY) && deleteDirectoryContents(CSV_DIRECTORY)) {
            logMessage("Directory:[ " . TEMP_DIRECTORY . " ] contents deleted successfully.\n");
            logMessage("Directory:[ " . CSV_DIRECTORY . " ] contents deleted successfully.\n");
            logMessage("Clean up complete.\n");
        } else {
            logMessage("Failed to delete the directory [ " . TEMP_DIRECTORY . " ] contents. This directory does not exist.\n");
        }
    } else {
        if (deleteDirectoryContents(CSV_DIRECTORY)) {
            logMessage("Directory:[ " . CSV_DIRECTORY . " ] contents deleted successfully.\n");
            logMessage("Clean up complete.\n");
        } else {
            logMessage("Failed to delete the directory contents.\n The working directory  [ " . CSV_DIRECTORY . " ] does not exist.\n");
                mkdir(CSV_DIRECTORY, 0755, true);
                // Debugging: Output a timestamped message
                logMessage("Working directory created: [" . CSV_DIRECTORY . " ]\n");
            
        }
    }


// Step 1: Create the temporary directory if it doesn't exist
    if (!file_exists(TEMP_DIRECTORY)) {
        mkdir(TEMP_DIRECTORY, 0755, true);
        // Debugging: Output a timestamped message
        logMessage("Temporary directory created: " . TEMP_DIRECTORY. "\n");
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
        logMessage("Master repository extracted successfully.\n");
    } else {
        // Debugging: Output a timestamped message
        logMessage("Failed to extract master repository.\n");
    }

// Step 4: Copy CSV files to a separate folder
    $sourcePath = TEMP_DIRECTORY . '/COVID-19-master/csse_covid_19_data/csse_covid_19_daily_reports/';
    $destinationPath = CSV_DIRECTORY;

    // Create the destination directory if it doesn't exist
        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }

    // Copy CSV files and the Master file
        $csvFiles = glob($sourcePath . '*.csv');
        // Iterate through each file and copy only if it falls within the specified date range
        foreach ($csvFiles as $csvFile) {
            $filename = basename($csvFile);
            $datePart = substr($filename, 0, 10);
            $fileDate = strtotime($datePart);

            if ($fileDate >= START_DATE && $fileDate <= END_DATE) {
                // Copy the file to the destination path
                $destinationFile = $destinationPath . '/' . $filename;
                if(copy($csvFile, $destinationFile)){
                    logMessage("Copied Master file: [$csvFile] into the working directory. \n");
                    // unlink (delete) the original file after copying
                    unlink($csvFile);
                } else {
                    logMessage("Error: Required CSV file: [$csvFile] Not copied.\n");
                }

            }
        }
        if(copy($zipFile, ($destinationPath . '/' . basename($zipFile)))){
            logMessage("Copied Master file: [$zipFile] into the working directory. \n");
            unlink($zipFile); // unlink (delete) the original file after copying
        } else{
            logMessage("Error: Master file: [$zipFile] Not copied.\n");
        }
        

// Step 5: Clean up 
    // Ensure to empty the temp directory and remove it
    if (deleteDirectoryContents(TEMP_DIRECTORY)) {
        logMessage("Directory:[ " . TEMP_DIRECTORY . " ] contents deleted successfully.\n");
        
        // Now, you can remove the directory itself
        if (deleteDirectory(TEMP_DIRECTORY)) {
            logMessage("Directory:[ " . TEMP_DIRECTORY . " ] removed successfully.\n");
            logMessage("Clean up complete.\n");
        } else {
            logMessage("Failed to remove the temporal directory.\n");
        }
    } else {
        logMessage("Failed to delete the temporal directory contents.\n");
    }

// Record the end time
$endTime = microtime(true);

// Calculate the execution time
$executionTime = $endTime - $startTime;

// Output the execution time
logMessage("Script execution time: " . number_format($executionTime, 4) . " seconds\n");


//-----------------------------------------------------------------------------------------------------------
?>
