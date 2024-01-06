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
    
    // CSV columns name
        $csv_columns = ['FIPS', 'Admin2', 'Province_State', 'Country_Region', 'Last_Update', 'Lat', 'Long_', 'Confirmed', 'Deaths', 'Recovered', 'Active', 'Combined_Key', 'Incident_Rate', 'Case_Fatality_Ratio'];
    
    // MariaDB table name constant
        define('TABLE_NAME', 'master_csv_table');

//-----------------------------------------------------------------------------------------------------------
// Defnining functions

    // Debugging fuction, Output a timestamped message with a newline
    function logMessage($message) {
        echo "[" . date("Y-m-d H:i:s") . "] " . $message . " <br>";
        global $logFile;
        file_put_contents($logFile, "[" . date("Y-m-d H:i:s") . "] " . $message . "\n", FILE_APPEND);
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
            foreach ($columns as $column) {
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

//-----------------------------------------------------------------------------------------------------------
// Insertion logic
try {
    // Connect to the database
    $pdo = new PDO("mysql:host=$database_host;dbname=$database_name", $database_user, $database_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Begin a transaction
    $pdo->beginTransaction();

    // Create the master table if needed
    $tableName = TABLE_NAME;
    createMasterTable($tableName, $csv_columns, $pdo);

    // Iterate over the date range required
    $startDate = new DateTime('2021-01-01');
    $endDate = new DateTime('2021-06-30');

    $currentDate = $startDate;

    while ($currentDate <= $endDate) {
        $csvFileName = $currentDate->format('d-m-Y') . '.csv';
        $csvFilePath = CSV_DIRECTORY . '/' . $csvFileName;

        if (file_exists($csvFilePath)) {
            // Read the CSV file
            $csvData = array_map('str_getcsv', file($csvFilePath));

            // Get column names and data types
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

            // Prepare SQL statement
            $columnsString = implode(', ', array_keys($columns));
            $valuesString = implode(', ', array_fill(0, count($columns), '?'));

            $sql = "INSERT INTO $tableName ($columnsString) VALUES ($valuesString)";

            $stmt = $pdo->prepare($sql);

            // Insert data into the database
            foreach ($csvData as $row) {
                // Replace empty values with '0' or null
                foreach ($row as &$value) {
                    $value = ($value === '') ? (is_numeric($value) ? 0 : null) : $value;
                }

                // Bind values to the prepared statement
                foreach ($columns as $columnName => $dataType) {
                    if (!isset($row[$columnName])) {
                        logMessage("Warning: Undefined array key \"$columnName\" in row.\n");
                    }
                    $stmt->bindValue(':' . $columnName, $row[$columnName], PDO::PARAM_STR);
                }               

                // Execute the statement
                if ($stmt->execute()) {
                    logMessage("Data from $csvFileName inserted into your_table_name successfully.\n");
                } else {
                    // Log the detailed error information
                    $errorInfo = $stmt->errorInfo();
                    logMessage("Data from $csvFileName was NOT inserted into your_table_name successfully.\n");
                    logMessage("PDO Error Code: {$errorInfo[0]}\n");
                    logMessage("Driver Error Code: {$errorInfo[1]}\n");
                    logMessage("Driver Error Message: {$errorInfo[2]}\n");
                }
            }

            // Commit the transaction after all inserts for this file
            $pdo->commit();
        } else {
            logMessage("File $csvFileName not found.\n");
        }

        // Move to the next date
        $currentDate->modify('+1 day');
    }
} catch (PDOException $e) {
    // Roll back the transaction in case of an exception
    $pdo->rollBack();
    logMessage("Error: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    // Roll back the transaction in case of an unexpected exception
    $pdo->rollBack();
    logMessage("An unexpected error occurred: " . $e->getMessage() . "\n");
}

// Close the database connections
$pdo = null;


?>