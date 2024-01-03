<?php
//-----------------------------------------------------------------------------------------------------------
// Libraries
    include('simple_html_dom.php'); // Include the Simple HTML DOM Parser
//-----------------------------------------------------------------------------------------------------------  
/// Initial Script confuguration
    ini_set('memory_limit', '1G');  // Increasing the memory limit to avoid any exhaustion
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

    // CSV directory
        define('CSV_DIRECTORY', '/shared_files');

    // Table name constant
        define('TABLE_NAME', 'master_csv_table');

    // list of CSV file that we are going to download from the GitHub source
        $csvFiles = [];



//-----------------------------------------------------------------------------------------------------------
// Defnining functions

// Debugging fuction, Output a timestamped message with a newline
    function logMessage($message) {
        echo "[" . date("Y-m-d H:i:s") . "] " . $message . " <br>";
        global $logFile;
        file_put_contents($logFile, "[" . date("Y-m-d H:i:s") . "] " . $message . "\n", FILE_APPEND);
    }

// Function to create a single table for all CSV files
    function createMasterTable($tableName, $columns, $pdo)
    {
        $query = "CREATE TABLE IF NOT EXISTS $tableName (id INT PRIMARY KEY AUTO_INCREMENT, ";

        // Add columns to the query with inferred data types
        foreach ($columns as $column) {
            $dataType = inferDataType($column);  // Function to infer data type
            $query .= "`$column` $dataType, ";  // Use backticks for column names
        }

        $query = rtrim($query, ", ") . ")";
        
        // Execute the query
        $stmt = $pdo->prepare($query);

        // Debugging: Output the SQL statement
        logMessage("SQL Statement: $query \n");

        try {
            $stmt->execute();
            logMessage("Table $tableName created successfully.\n");
        } catch (PDOException $e) {
            logMessage("Error creating table 'covid_data': " . $e->getMessage() . "\n");
        }
    }

// Function to infer data type based on column name and example:
    // * "FIPS":	45001
    // * "Admin2":	Abbeville
    // * "Province_State":	South Carolina
    // Country_Region	US
    // * "Last Update": 	5/21/2020 2:32
    // * "Lat"	34.22333378
    // * "Long_":	-82.46170658
    // * "Confirmed":	36
    // * "Deaths":	0
    // * "Recovered":	0
    // * "Active":	36
    // * "Combined_Key":	Abbeville, South Carolina, US
    // * "Incident_Rate":	533.5829749
    // * "Case_Fatality_Ratio (%)":	3.779216715

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

// Function to handle data type conversion and empty values
    function convertValue($value, $dataType) {
        // Handle empty values
        if ($value === '') {
            return null;
        }

        // Perform data type conversion based on the specified type
        switch ($dataType) {
            case 'DATETIME':
                // Implement your DATETIME conversion logic here (if needed)
                return $value;
            case 'FLOAT':
                return is_numeric($value) ? (float)$value : null;
            case 'INT':
                return is_numeric($value) ? (int)$value : null;
            default:
                return $value;
        }
    }

    // Function to check if a column exists in the database table
        function columnExistsInTable($columnName, $pdo)
        {
            $tableName = TABLE_NAME;

            // Use a query to check if the column exists in the information_schema
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM information_schema.columns
                WHERE table_name = :tableName
                AND column_name = :columnName
            ");

            $stmt->bindParam(':tableName', $tableName, PDO::PARAM_STR);
            $stmt->bindParam(':columnName', $columnName, PDO::PARAM_STR);

            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result['count'] > 0;
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

// Function to move the files between Docker compose volumes
    function moveFiles($sourceDirectory, $destinationDirectory) {
        try{
            // Create the destination directory if it doesn't exist
            if (!file_exists($destinationDirectory)) {
                mkdir($destinationDirectory, 0777, true);
            } else { 
                // lets make sure it is empty and clean
                deleteDirectoryContents($destinationDirectory);
                
                // Get all CSV files in the source directory
                $csvFiles = glob($sourceDirectory . '/*.csv');
            
                // Move each CSV file to the destination directory
                foreach ($csvFiles as $csvFile) {
                    $destinationFile = $destinationDirectory . '/' . basename($csvFile);
                    
                    // Use shell command to move the file
                    $command = "mv $csvFile $destinationFile";
                    exec($command);
            
                    // Debugging: Output a timestamped message
                    logMessage(" File moved: basename($csvFile) to $destinationDirectory\n");
                }
            }
        } catch (Exception $e) {
            // Handle exceptions
            logMessage("Error moving CSV files: " . $e->getMessage() . "\n");
        }
    }

////-----------------------------------------------------------------------------------------------------------
// Step 1: Validate the CSV files that we are going to process  

    // Directory source of the local CSV files
        $csvDirectory = CSV_DIRECTORY;
        logMessage("COVID-19 reports CSV source directory: $csvDirectory\n");
    // Cache for storing CSV data
        $cachedData = [];
    
    // Update the list of flies, Get all CSV files downloaded in the local directory
        $csvFiles = glob($csvDirectory . '/*.csv');

    // Debugging: Output the count of CSV files available locally
        logMessage(" Found " . count($csvFiles) . " CSV files:\n");

    // // Valide each CSV file for further processing
    //     foreach ($csvFiles as $csvFile) {
    //         if (file_exists($csvFile)) {
    //             // Debugging: Output a timestamped message if the file exists
    //             logMessage(" File exists: $csvFile \n");

    //             // Try to read the file content
    //             $fileContent = file_get_contents($csvFile);

    //             if ($fileContent !== false) {
    //                 // Debugging: Output the first 100 characters of the file content
    //                 logMessage(" File content: " . substr($fileContent, 0, 100) . "\n");
    //             } else {
    //                 // Debugging: Output an error message if unable to read the file
    //                 logMessage("Error reading the file: $csvFile \n");
    //             }
    //         } else {
    //             // Debugging: Output an error message if the file does not exist
    //             logMessage(" File does not exist: $csvFile \n");
    //         }
    //      }

    // Populate the cachedData array with CSV file content
        $cachedData = [];
        foreach ($csvFiles as $csvFile) {
            $fileName = basename($csvFile);
            $fileContent = file_get_contents($csvFile);

            // Check if file content retrieval is successful
            if ($fileContent !== false) {
                // Add file content to the cachedData array
                $cachedData[$fileName] = $fileContent;
                // Debugging: Output a timestamped message
                logMessage(" File content loaded for $fileName.\n");
            } else {
                // Debugging: Output an error message if unable to read the file
                logMessage("Error reading the file: $csvFile \n");
            }
        }

////-----------------------------------------------------------------------------------------------------------
// Step 2: Check the connection to the database to ensure that we can proceed with the next steps
    try {
        // Connect to the database
        $pdo = new PDO("mysql:host=$database_host;dbname=$database_name", $database_user, $database_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Test the database connection
        $stmt = $pdo->query('SELECT "Database connection test successful.\n"');
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }

// Grant all privileges to the webprog user on the DB we are creating
    try {
        // Connect to the database as the root user
        $root_pdo = new PDO("mysql:host=$database_host;dbname=mysql", 'root', 'secret');
        $root_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Grant all privileges to the webprog user
        $grantQuery = "GRANT ALL PRIVILEGES ON mas.* TO '$database_user'@'%'";
        $root_pdo->exec($grantQuery);

        // Flush privileges
        $flushQuery = "FLUSH PRIVILEGES";
        $root_pdo->exec($flushQuery);

        // Debugging: Output a timestamped message
        logMessage(" Privileges granted to user '$database_user'. \n");
    } catch (PDOException $e) {
        // Handle connection errors
        die("Connection failed: " . $e->getMessage());
    } catch (Exception $e) {
        // Handle other exceptions
        logMessage("Error: " . $e->getMessage() . "\n");
    }
////-----------------------------------------------------------------------------------------------------------
// Step 3: Inject the CSV data in a MariaDB table
        try {
                $pdo->beginTransaction();
            // Disable Foreign Key Checks and Unique Constraints
                $pdo->exec('SET @@session.unique_checks = 0');
                $pdo->exec('SET @@session.foreign_key_checks = 0');

            // Start measuring execution time
                $startTime = microtime(true);

            // Read the first CSV file to get column names
                $firstCsvFile = reset($csvFiles);
                $handle = fopen($firstCsvFile, 'r');
                $columns = fgetcsv($handle);
                fclose($handle);

            // Create the master table
                createMasterTable(TABLE_NAME, $columns, $pdo);

            // Assuming $cachedData is an associative array where keys are file names and values are file contents
            // Loop through each CSV file
            foreach ($cachedData as $fileName => $fileContent) {
                // Get the column names from the first line of the file
                $handle = fopen("data://text/plain;base64," . base64_encode($fileContent), 'r');
                $csvColumns = fgetcsv($handle);
                fclose($handle);

                // Generate the LOAD DATA INFILE query with dynamic columns
                $query = "LOAD DATA INFILE '$fileName' INTO TABLE " . TABLE_NAME .
                        " FIELDS TERMINATED BY ',' ENCLOSED BY '\"' LINES TERMINATED BY '\n' IGNORE 1 ROWS" .
                        " (";

                // Prepare an array to store columns to insert
                $columnsToInsert = [];

                // Loop through CSV columns
                foreach ($csvColumns as $csvColumn) {
                    // Check if the column exists in the database table
                    if (columnExistsInTable($csvColumn, $pdo)) {
                        $columnsToInsert[] = $csvColumn;
                    } else {
                        logMessage("Column '$csvColumn' does not exist in the database table. Skipping.\n");
                    }
                }

                // Add the columns to the query
                $query .= implode(', ', $columnsToInsert) . ")";

                // Prepare and execute the query
                $stmt = $pdo->prepare($query);
                if($stmt->execute()){
                    $pdo->commit();
                    logMessage("Data from $fileName inserted into " . TABLE_NAME . " successfully.\n");
                } else {
                    $pdo->rollBack();
                    logMessage("Data from $fileName was NOT inserted into " . TABLE_NAME . " successfully.\n");
                }

                
            }

            

            // Re-enable Foreign Key Checks and Unique Constraints
                $pdo->exec('SET @@session.unique_checks = 1');
                $pdo->exec('SET @@session.foreign_key_checks = 1');

                //logMessage("Bulk insertion completed successfully.\n");

        } catch (PDOException $e) {
            die("Error: " . $e->getMessage());
        }

// Close the database connections
    $root_pdo = null;
    $pdo = null;


?>