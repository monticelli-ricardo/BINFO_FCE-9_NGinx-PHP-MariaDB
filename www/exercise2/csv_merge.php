<?php

//-----------------------------------------------------------------------------------------------------------  
/// Initial Script confuguration
    ini_set('memory_limit', '3G');  // Increasing the memory limit to avoid any exhaustion
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
//-----------------------------------------------------------------------------------------------------------
// Defining Variables
    // Log file for debugging
    $logFile = '/var/www/html/exercise2/logFile.log';

    // Shared volume CSV directory
    define('CSV_DIRECTORY', '/shared_files');

    // Set the date range
    $start_date = strtotime('2021-01-01');
    $end_date = strtotime('2021-06-30');

    // Array to store merged data
    $merged_data = []; 

//-----------------------------------------------------------------------------------------------------------
// Defnining functions

    // Debugging function, Output a timestamped message with a newline
        function logMessage($message) {
            echo "[" . date("Y-m-d H:i:s") . "] " . $message . " <br>";
            global $logFile;
            file_put_contents($logFile, "[" . date("Y-m-d H:i:s") . "] " . $message . "\n", FILE_APPEND);
        }

//-----------------------------------------------------------------------------------------------------------
// Merge CSV logic
try {
       // Loop through each date in the range
       while ($start_date <= $end_date) {
        $current_date = date('d-m-Y', $start_date);
        $file_path = CSV_DIRECTORY . '/' . $current_date . '.csv';

        // Check if the file exists
        if (file_exists($file_path)) {
            // Read CSV file
            $csv_data = array_map('str_getcsv', file($file_path));

            // Check if the header row is empty or has unexpected columns
            if (empty($csv_data) || count($csv_data[0]) !== 14) {
                logMessage('Error in Data: Unexpected columns in file ' . $file_path . "\n");
                logMessage('Skipping: ' . $file_path . "\n" );
                continue; // Skip to the next file
            }

            $header = $csv_data[0]; // Use the first row as the header
            array_shift($csv_data); // Remove header row

            // Iterate through data and merge
            foreach ($csv_data as $row) {

                $merged_row = [];
                foreach ($header as $index => $column) {
                    $value = trim($row[$index]);

                    // // Check for missing values
                    // if ($value === "") {
                    //     logMessage('Error in Data: Missing value in file ' . $file_path . ' at row ' . ($index + 1)); // Add 1 to account for header row
                    // }

                    // Replace "empty" with 0 or null based on data type
                    if ($value === "empty") {
                        switch ($column) {
                            case 'FIPS':
                            case 'Confirmed':
                            case 'Deaths':
                            case 'Recovered':
                            case 'Active':
                                $merged_row[$column] = 0;
                                break;
                            case 'Last_Update':
                                $merged_row[$column] = null;
                                break;
                            default:
                                $merged_row[$column] = null;
                                break;
                        }
                    } else {
                        $merged_row[$column] = $value;
                    }
                }
                $merged_data[] = $merged_row;
            }
        }

        // Move to the next day
        $start_date = strtotime('+1 day', $start_date);
    }

    // Check if there is any data to write
    if (empty($merged_data)) {
        throw new Exception('No valid data available to merge.');
    }

    // Write merged data to master CSV file
    $master_file = CSV_DIRECTORY . '/master_file.csv';
    $fp = fopen($master_file, 'w');
    if (!$fp) {
        throw new Exception('Error creating master file.');
    }

    fputcsv($fp, $header); // Write header row
    foreach ($merged_data as $row) {
        fputcsv($fp, $row);
    }
    fclose($fp);

    logMessage('Merging completed. Master file created: ' . $master_file . "\n");
} catch (Exception $e) {
    logMessage('Error: ' . $e->getMessage());
}
?>
