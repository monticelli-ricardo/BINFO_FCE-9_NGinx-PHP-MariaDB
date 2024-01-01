<?php
//-----------------------------------------------------------------------------------------------------------
/// Initial Script confuguration
    //ini_set('memory_limit', '256M');  // Increasing the memory limit, Set memory limit to 256 megabytes
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
//-----------------------------------------------------------------------------------------------------------
// Defining Variables
// Database connection details
    $database_host = 'DB';
    $database_user = 'webprog';
    $database_password = 'webprog';
    $database_name = 'webprog';

// CSV directory
    define('CSV_DIRECTORY', '/var/www/html/exercise2/csse_covid_19_daily_reports');

// Table name constant
    define('TABLE_NAME', 'master_table');

// list of CSV file that we are going to download from the GitHub source
    $csvFiles = [];

//-----------------------------------------------------------------------------------------------------------
// Defnining functions

// Debugging fuction, Output a timestamped message with a newline
    function logMessage($message) {
        echo "[" . date("Y-m-d H:i:s") . "] " . $message . " <br>";
    //file_put_contents('logfile.txt', "[" . date("Y-m-d H:i:s") . "] " . $message . "\n", FILE_APPEND);
    }

// Function to download a file from GitHub
    function downloadFile($url, $destination)
    {
        // Debugging: Output a timestamped message
        logMessage("[" . date("Y-m-d H:i:s") . "] Downloading file: $url");

        // Download the file
        $fileContent = file_get_contents($url);

        if ($fileContent !== false) {
            file_put_contents($destination, $fileContent);
            // Debugging: Output a timestamped message
            logMessage("[" . date("Y-m-d H:i:s") . "] File downloaded successfully: $destination");
        } else {
            // Debugging: Output a timestamped message
            logMessage("[" . date("Y-m-d H:i:s") . "] Error downloading file: $url");
        }
    }


// Function to create a single table for all CSV files
    function createMasterTable($tableName, $columns, $pdo) {

        $query = "CREATE TABLE IF NOT EXISTS $tableName (id INT PRIMARY KEY AUTO_INCREMENT, ";

        // Add columns to the query with inferred data types
        foreach ($columns as $column) {
            $dataType = inferDataType($column);  // Function to infer data type
            $query .= "$column $dataType, ";
        }

        $query = rtrim($query, ", ") . ")";
        
        // Execute the query
        $stmt = $pdo->prepare($query);

        // Debugging: Output the SQL statement
        logMessage("SQL Statement: $query");

        $stmt->execute();

        // Debugging: Output a timestamped message
        logMessage( " Table $tableName created successfully.<br>");
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
////-----------------------------------------------------------------------------------------------------------
// First step

// Debugging: CSV directory
    logMessage(" CSV Directory: " . CSV_DIRECTORY);

// Download the CSV files from GitHub Source
    $baseUrl = 'https://github.com/CSSEGISandData/COVID-19/raw/master/csse_covid_19_data/csse_covid_19_daily_reports/';
    foreach ($csvFiles as $file) {
        // Check if the file has a CSV extension
        if (pathinfo($file, PATHINFO_EXTENSION) === 'csv') {
            $url = $baseUrl . basename($file);
            $destination = CSV_DIRECTORY . '/' . basename($file);

            // Download the file
            downloadFile($url, $destination);
        }

        // Debugging: Output a timestamped message
        if (file_exists($destination)) {
            logMessage("File downloaded successfully: $destination<br>");
        } else {
            logMessage("Error downloading file: $url<br>");
        }
    }

    // Update the list of flies, Get all CSV files downloaded in the local directory
    $csvFiles = glob(CSV_DIRECTORY . '/*.csv');

    // Debugging: Output the count of CSV files available locally
    logMessage(" Found " . count($csvFiles) . " CSV files:");

// Valide each downloaded file for further processing

    foreach ($csvFiles as $csvFile) {
        if (file_exists($csvFile)) {
            // Debugging: Output a timestamped message if the file exists
            logMessage(" File exists: $csvFile<br>");

            // Try to read the file content
            $fileContent = file_get_contents($csvFile);

            if ($fileContent !== false) {
                // Debugging: Output the first 100 characters of the file content
                logMessage(" File content: " . substr($fileContent, 0, 100) . "<br>");
            } else {
                // Debugging: Output an error message if unable to read the file
                logMessage("Error reading the file: $csvFile<br>");
            }
        } else {
            // Debugging: Output an error message if the file does not exist
            logMessage(" File does not exist: $csvFile<br>");
        }
   }

////-----------------------------------------------------------------------------------------------------------
// Second step

// Check the connection to the database to ensure we can proceed witht the next steps
try {
    // Connect to the database
    $pdo = new PDO("mysql:host=$database_host;dbname=$database_name", $database_user, $database_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Test the database connection
    $stmt = $pdo->query('SELECT "Database connection test successful."');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // Debugging: Output a timestamped message
    logMessage( " Database connection test successful.<br>");
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
        logMessage(" Privileges granted to user '$database_user'.<br>");
    } catch (PDOException $e) {
        // Handle connection errors
        die("Connection failed: " . $e->getMessage());
    }

// Inject the CSV data intho the DB we just created
//     try {
//         // Disable Foreign Key Checks and Unique Constraints
//         $pdo->exec('SET @@session.unique_checks = 0');
//         $pdo->exec('SET @@session.foreign_key_checks = 0');

//         // Start measuring execution time
//         $startTime = microtime(true);
//         // // Enable query profiling
//         // $pdo->exec('SET profiling = 1');

//         // Read the first CSV file to get column names
//         $firstCsvFile = reset($csvFiles);

//         $handle = fopen($firstCsvFile, 'r');
//         $columns = fgetcsv($handle);
//         fclose($handle);

//         // Create the master table
//         createMasterTable("master_table", $columns, $pdo);

//         // Perform bulk insertion or other operations

//             // Read and insert data from all CSV files, skipping the first row (column names)
//             foreach ($csvFiles as $csvFile) {
//                 $handle = fopen($csvFile, 'r');
                
//                 // Skip the first row (column names)
//                 fgetcsv($handle);

//                 $query = "LOAD DATA INFILE '$csvFile' INTO TABLE master_table
//                 FIELDS TERMINATED BY ',' ENCLOSED BY '\"'
//                 LINES TERMINATED BY '\n' IGNORE 1 ROWS";

//                 $stmt = $pdo->prepare($query);
//                 $stmt->execute();
                
//                 fclose($handle);
//             }

//             // Remove the trailing comma and execute the bulk insert
//             $query = rtrim($query, ", ");
//             $stmt = $pdo->prepare($query);
//             $stmt->execute($queryParams);


//         // Re-enable Foreign Key Checks and Unique Constraints
//         $pdo->exec('SET @@session.unique_checks = 1');
//         $pdo->exec('SET @@session.foreign_key_checks = 1');

//         // Calculate execution time v1
//         $endTime = microtime(true);
//         $executionTime = $endTime - $startTime;
//         // Debugging: Output a timestamped message
//         logMessage( " Data inserted into master_table successfully.<br>");
//         logMessage( " Script executed in $executionTime seconds.");
//         // // Calculate execution time v2
//         // $stmt = $pdo->query('SHOW PROFILES');
//         // while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
//         //     logMessage("Query ID: {$row['Query_ID']}, Duration: {$row['Duration']} seconds");
//         // }

//         logMessage( " Bulk insertion completed successfully.");

//     } catch (PDOException $e) {
//         // Handle exceptions, rollback transactions, or take appropriate action
//         echo "Error: " . $e->getMessage();
//     }

// Close the database connection
    $pdo = null;

?>