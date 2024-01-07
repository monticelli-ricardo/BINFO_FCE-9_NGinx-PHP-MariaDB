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

    // Database connection details
        $database_host = 'DB';
        $database_user = 'webprog';
        $database_password = 'webprog';
        $database_name = 'webprog';

    // Shared volume, source of the CSV directory
        define('CSV_DIRECTORY', '/shared_files');
    
    // Set the date range
        define('START_DATE','01-01-2021');
        define('END_DATE', '06-30-2021');

    // CSV columns name
        $csv_columns = ['FIPS', 'Admin2', 'Province_State', 'Country_Region', 'Last_Update', 'Lat', 'Long_', 'Confirmed', 'Deaths', 'Recovered', 'Active', 'Combined_Key', 'Incident_Rate', 'Case_Fatality_Ratio'];
    
    // Table column names and their respective data types
        $columns = [
            'FIPS' => 'INT',
            'Admin2' => 'VARCHAR(255)',
            'Province_State' => 'VARCHAR(255)',
            'Country_Region' => 'VARCHAR(255)',
            'Last_Update' => 'DATETIME',
            'Lat' => 'FLOAT',
            'Long_' => 'FLOAT',
            'Confirmed' => 'INT',
            'Deaths' => 'INT',
            'Recovered' => 'INT',
            'Active' => 'INT',
            'Combined_Key' => 'VARCHAR(255)',
            'Incident_Rate' => 'FLOAT',
            'Case_Fatality_Ratio' => 'FLOAT'
        ];

    // MariaDB table name constant
        define('TABLE_NAME', 'covid19_table');
        $tableName = TABLE_NAME; // redundant

//-----------------------------------------------------------------------------------------------------------
// Defnining functions

    // Debugging fuction, Output a timestamped message with a newline
    function logMessage($message) {
        echo "[" . date("Y-m-d H:i:s") . "] " . $message . " <br>";
        global $logFile;
        file_put_contents($logFile, "[" . date("Y-m-d H:i:s") . "] " . $message . "\n", FILE_APPEND);
    }
    
    // Function to infer data type based on column name (static)
    function inferDataType($columnName) {
        // Example: infer data type based on column name
        if (strpos($columnName, 'Last_Update') !== false) {
            return 'DATETIME';
        } elseif (strpos($columnName, 'Lat') !== false || strpos($columnName, 'Long_') !== false || strpos($columnName, 'Incident_Rate') !== false || strpos($columnName, 'Case_Fatality_Ratio') !== false) {
            return 'FLOAT';
        } elseif (strpos($columnName, 'Confirmed') !== false || strpos($columnName, 'Deaths') !== false || strpos($columnName, 'Recovered') !== false || strpos($columnName, 'Active') !== false) {
            return 'INT';
        } else {
            return 'VARCHAR(255)';
        }
    }

    // Function to create a single table for all CSV files
    function createMasterTable($tableName, $csv_columns, $pdo)
    {
        // Query to check if the table exists
        $queryTableExists = "SHOW TABLES LIKE ?";
        $stmtTableExists = $pdo->prepare($queryTableExists);
        $stmtTableExists->bindParam(1, $tableName, PDO::PARAM_STR);
        $stmtTableExists->execute();
        $tableExists = $stmtTableExists->rowCount() > 0;

        if (!$tableExists) {
            // Table does not exist, create it
            $query = "CREATE TABLE IF NOT EXISTS $tableName (id INT PRIMARY KEY AUTO_INCREMENT, ";

            // Add columns to the query with inferred data types
            foreach ($csv_columns as $column) {
                $dataType = inferDataType($column);  // Function to infer data type
                $query .= "`$column` $dataType, ";  // Use backticks for column names
            }

            $query = rtrim($query, ", ") . ")";
            
            // Execute the query to create the table
            $stmtCreateTable = $pdo->prepare($query);

            // Debugging: Output the SQL statement
            logMessage("SQL Statement: $query \n");

            try {
                if ($stmtCreateTable->execute()) {
                    logMessage("Table $tableName created successfully.\n");
                    // Query to show columns from the existing table
                    $queryShowColumns = "SHOW COLUMNS FROM $tableName";
                    
                    // Execute the query to get columns
                    $stmtShowColumns = $pdo->prepare($queryShowColumns);
                    $stmtShowColumns->execute();

                    // Fetch the result and log each column
                    $columnsResult = $stmtShowColumns->fetchAll(PDO::FETCH_ASSOC);
                    logMessage("Columns for $tableName:\n");

                    foreach ($columnsResult as $column) {
                        $columnName = $column['Field'];
                        logMessage(" - $columnName\n");
                    }
                } else {
                    $errorInfo = $stmtCreateTable->errorInfo();
                    logMessage("Error creating table $tableName: " . $errorInfo[2] . "\n");
                }
            } catch (PDOException $e) {
                logMessage("Error creating table $tableName: " . $e->getMessage() . "\n");
            }
        } else {
            // Table exists, log information about existing columns
            logMessage("Table $tableName already exists.\n");

            // Query to show columns from the existing table
            $queryShowColumns = "SHOW COLUMNS FROM $tableName";
            
            // Execute the query to get columns
            $stmtShowColumns = $pdo->prepare($queryShowColumns);
            $stmtShowColumns->execute();

            // Fetch the result and log each column
            $columnsResult = $stmtShowColumns->fetchAll(PDO::FETCH_ASSOC);
            logMessage("Columns for $tableName:\n");

            foreach ($columnsResult as $column) {
                $columnName = $column['Field'];
                logMessage(" - $columnName\n");
            }
        }
    }

    // function to handle the PDO parameter type based on your data types
    function getParamType($dataType) {
        switch ($dataType) {
            case 'INT':
                return PDO::PARAM_INT;
            case 'FLOAT':
                return PDO::PARAM_STR; // Assuming your FLOAT values are represented as strings
            case 'VARCHAR(255)':
                return PDO::PARAM_STR;
            case 'DATETIME':
                return PDO::PARAM_STR; // Assuming your DATETIME values are represented as strings
            default:
                return PDO::PARAM_STR; // Default to PARAM_STR if the data type is unknown
        }
    }

    // Bind the column keys to the row values read
    function bindValues($stmt, $columns, $header, $row) {
        foreach ($columns as $columnName => $dataType) {

            // Skip binding the 'id' column
            if ($columnName === 'id') {
                continue;
            }
            $index = array_search($columnName, $header);
            $value = isset($row[$index]) ? $row[$index] : null;
            $stmt->bindValue(':' . $columnName, $value, getParamType($dataType));
        }
    }

    // Function to execute batch insert manually
    function executeBatchInsert($pdo, $stmt, $insertValues) {
        foreach ($insertValues as $values) {
            $stmt->execute($values);
        }
    }
    // loop through the days within that range and insert batches into DB.
    function processCSVFiles($startDate, $endDate, $path, $table_columns, $tableName, $pdo) {
        $currentDate = DateTime::createFromFormat('m-d-Y', $startDate)->getTimestamp();
        $endDate = DateTime::createFromFormat('m-d-Y', $endDate)->getTimestamp();

        // Loop through each day
        while ($currentDate <= $endDate) {
            $csvFileName = date("m-d-Y", $currentDate) . ".csv"; // Adjust the date format here
            $csvFilePath = $path . "/" . $csvFileName;

            // Process CSV file and insert data
            if (file_exists($csvFilePath)) {
                // Begin a transaction
                $pdo->beginTransaction();

                // Read the CSV file
                $csvData = array_map('str_getcsv', file($csvFilePath));

                // Get the header from the CSV file
                $header = array_shift($csvData);

                // Filter columns based on the header, excluding 'id'
                $validColumns = array_diff($header, ['id']);

                // Prepare SQL statement
                $columnsString = implode(', ', array_keys($table_columns));
                $valuesString = implode(', ', array_fill(0, count($table_columns), '?'));

                $sql = "INSERT INTO $tableName ($columnsString) VALUES ($valuesString)";
                $stmt = $pdo->prepare($sql);

                $rowNumber = 1; // Initialize row number counter
                $batchSize = 1000; // Choose an appropriate batch size
                $insertValues = [];

                foreach ($csvData as $row) {
                    // Replace empty values in the row with null
                    foreach ($row as &$value) {
                        $value = ($value === '') ? null : $value;
                        unset($value); // Unset the reference to avoid potential memory issues
                    }

                    // Bind values to the prepared SQL statement
                    bindValues($stmt, $table_columns, $header, $row);

                    // Add values to the batch array
                    $insertValues[] = array_values($row);

                    // Execute the statement in batches
                    if ($rowNumber % $batchSize === 0) {
                        executeBatchInsert($pdo, $stmt, $insertValues);
                        $insertValues = []; // Reset the batch array
                    }

                    // Increment the row number counter
                    $rowNumber++;
                }

                // Execute any remaining batch
                if (!empty($insertValues)) {
                    executeBatchInsert($pdo, $stmt, $insertValues);
                }

                // Commit the transaction after all inserts for this file
                $pdo->commit();
            } else {
                logMessage("File [$csvFileName] not found.\n");
            }

            // Update the variable pointing to each CSV file
            $currentDate = strtotime("+1 day", $currentDate);
        }
    }

//-----------------------------------------------------------------------------------------------------------
// Record the start time
$startTime = microtime(true);

// Insertion logic
try {
    // Connect to the database
    $pdo = new PDO("mysql:host=$database_host;dbname=$database_name", $database_user, $database_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create the master table if needed
    createMasterTable($tableName, $csv_columns, $pdo);

   // Process and insert
   processCSVFiles(START_DATE, END_DATE, CSV_DIRECTORY, $columns, $tableName, $pdo);

} catch (PDOException $e) {
    // Roll back the transaction in case of an exception
    $pdo->rollBack();
    logMessage("Error: " . $e->getMessage());
} catch (Exception $e) {
    // Roll back the transaction in case of an unexpected exception
    $pdo->rollBack();
    logMessage("An unexpected error occurred: " . $e->getMessage());
}


// Close the database connections
$pdo = null;

// Record the end time
$endTime = microtime(true);

// Calculate the execution time
$executionTime = $endTime - $startTime;

// Output the execution time
logMessage("Script execution time: " . number_format($executionTime, 4) . " seconds\n");



?>