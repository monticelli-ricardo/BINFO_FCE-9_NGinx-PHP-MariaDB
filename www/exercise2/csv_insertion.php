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
    
    // CSV file
        $csvFileName = 'master_covid19_file.csv';
        $csvFilePath = CSV_DIRECTORY . '/' . $csvFileName;
    
    // CSV columns name
        $csv_columns = ['FIPS', 'Admin2', 'Province_State', 'Country_Region', 'Last_Update', 'Lat', 'Long_', 'Confirmed', 'Deaths', 'Recovered', 'Active', 'Combined_Key', 'Incident_Rate', 'Case_Fatality_Ratio'];
    
    // CSV column names and their respective data types
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
        define('TABLE_NAME', 'master_csv_table');
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

    //
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

//-----------------------------------------------------------------------------------------------------------
// Record the start time
$startTime = microtime(true);

// Insertion logic
try {
    // Connect to the database
    $pdo = new PDO("mysql:host=$database_host;dbname=$database_name", $database_user, $database_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Begin a transaction
    $pdo->beginTransaction();

    // Create the master table if needed
    createMasterTable($tableName, $csv_columns, $pdo);

    if (file_exists($csvFilePath)) {
        // Read the CSV file
        $csvData = array_map('str_getcsv', file($csvFilePath));

        // Get the header from the CSV file
        $header = array_shift($csvData);

        // Filter columns based on the header, excluding 'id'
        $validColumns = array_diff($header, ['id']);

        // Prepare SQL statement
        $sql = "INSERT INTO " . TABLE_NAME . " (FIPS, Admin2, Province_State, Country_Region, Last_Update, Lat, Long_, Confirmed, Deaths, Recovered, Active, Combined_Key, Incident_Rate, Case_Fatality_Ratio) VALUES (:FIPS, :Admin2, :Province_State, :Country_Region, :Last_Update, :Lat, :Long_, :Confirmed, :Deaths, :Recovered, :Active, :Combined_Key, :Incident_Rate, :Case_Fatality_Ratio)";
        $stmt = $pdo->prepare($sql);


        // Insert data into the database
        $rowNumber = 1; // Initialize row number counter

        foreach ($csvData as $row) {
            // Replace empty values in the row with null
            foreach ($row as &$value) {
                $value = ($value === '') ? null : $value;
                unset($value); // Unset the reference to avoid potential memory issues 
            }
        
            // Bind values to the prepared SQL statement
            bindValues($stmt, $columns, $header, $row);

            // Execute the statement
            if ($stmt->execute()) {
                logMessage("Row $rowNumber data from [$csvFileName] inserted into [$tableName] successfully.\n");
            } else {
                // Log the detailed error information
                $errorInfo = $stmt->errorInfo();
                logMessage("Row $rowNumber data from [$csvFileName] was NOT inserted into [$tableName] successfully.\n");
                logMessage("PDO Error Code: {$errorInfo[0]}\n");
                logMessage("Driver Error Code: {$errorInfo[1]}\n");
                logMessage("Driver Error Message: {$errorInfo[2]}\n");
            }
           // Increment the row number counter
           $rowNumber++;
        }

        // Commit the transaction after all inserts for this file
        $pdo->commit();
    } else {
        logMessage("File [$csvFileName] not found.\n");
    }

    // Perform a textual dump of the database webprog
    $sql_dump = "mysqldump -u $database_password -p $database_password $database_name > /shared_files/ex2_dump.sql";
    $stmt = $pdo->prepare($sql_dump);
    // Execute the statement
    if ($stmt->execute()) {
        logMessage("Textual dump complete. Result are here: /shared_files/ex2_dump.sql .\n");
    } else {
        // Log the detailed error information
        $errorInfo = $stmt->errorInfo();
        logMessage("Textual dump failed.\n");
        logMessage("PDO Error Code: {$errorInfo[0]}\n");
        logMessage("Driver Error Code: {$errorInfo[1]}\n");
        logMessage("Driver Error Message: {$errorInfo[2]}\n");
    }

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