<?php
//-----------------------------------------------------------------------------------------------------------
/// Increasing the memory limit
ini_set('memory_limit', '256M');  // Set memory limit to 256 megabytes

//-----------------------------------------------------------------------------------------------------------
// Database connection details
$database_host = 'DB';
$database_user = 'webprog';
$database_password = 'webprog';
$database_name = 'webprog';
//-----------------------------------------------------------------------------------------------------------
// CSV directory
define('CSV_DIRECTORY', '/var/www/html/exercise2/COVID-19-master/csse_covid_19_data/csse_covid_19_daily_reports');

// Table name constant
define('TABLE_NAME', 'master_table');

//-----------------------------------------------------------------------------------------------------------


// Debugging: Output a timestamped message with a newline
function logMessage($message) {
    echo "[" . date("Y-m-d H:i:s") . "] " . $message . " <br>";
   //file_put_contents('logfile.txt', "[" . date("Y-m-d H:i:s") . "] " . $message . "\n", FILE_APPEND);
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
    logMessage( "[" . date("Y-m-d H:i:s") . "] Table $tableName created successfully.<br>");
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

//
// Check the connection to the database
try {
    // Connect to the database
    $pdo = new PDO("mysql:host=$database_host;dbname=$database_name", $database_user, $database_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Test the database connection
    $stmt = $pdo->query('SELECT "Database connection test successful."');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // Debugging: Output a timestamped message
    logMessage( "[" . date("Y-m-d H:i:s") . "] Database connection test successful.<br>");
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
//------------------------------------------------------------------------------------------------------------

// CSV directory
logMessage("[" . date("Y-m-d H:i:s") . "] CSV Directory: " . CSV_DIRECTORY);

// Get all CSV files in the directory
$csvFiles = glob(CSV_DIRECTORY . '/*.csv');

// Debugging: Output the list of CSV files
logMessage("[" . date("Y-m-d H:i:s") . "] Found " . count($csvFiles) . " CSV files:");
foreach ($csvFiles as $file) {
    logMessage($file);
}
//-----------------------------------------------------------------------------------------------------------
// Inject the CSV data intho the DB

// Start measuring execution time
$startTime = microtime(true);
// // Enable query profiling
// $pdo->exec('SET profiling = 1');

// Read the first CSV file to get column names
$firstCsvFile = reset($csvFiles);

$handle = fopen($firstCsvFile, 'r');
$columns = fgetcsv($handle);
fclose($handle);

// Create the master table
createMasterTable("master_table", $columns, $pdo);


// Use PDO to insert data into the master table
$query = "INSERT INTO master_table (" . implode(', ', $columns) . ") VALUES ";
$queryParams = [];

// Read and insert data from all CSV files, skipping the first row (column names)
foreach ($csvFiles as $csvFile) {
    $handle = fopen($csvFile, 'r');
    
    // Skip the first row (column names)
    fgetcsv($handle);

    while (($data = fgetcsv($handle)) !== false) {
        // Apply value conversion for each column
        foreach ($columns as $index => $column) {
            $data[$index] = convertValue($data[$index], inferDataType($column));
        }

        // Add placeholders for the values in the query
        $query .= "(" . rtrim(str_repeat("?, ", count($columns)), ", ") . "), ";
        $queryParams = array_merge($queryParams, $data);
    }
    
    fclose($handle);
}

// Remove the trailing comma and execute the bulk insert
$query = rtrim($query, ", ");
$stmt = $pdo->prepare($query);
$stmt->execute($queryParams);

// Calculate execution time v1
$endTime = microtime(true);
$executionTime = $endTime - $startTime;
// Debugging: Output a timestamped message
logMessage( "[" . date("Y-m-d H:i:s") . "] Data inserted into master_table successfully.<br>");
logMessage( "[" . date("Y-m-d H:i:s") . "] Script executed in $executionTime seconds.");
// // Calculate execution time v2
// $stmt = $pdo->query('SHOW PROFILES');
// while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
//     logMessage("Query ID: {$row['Query_ID']}, Duration: {$row['Duration']} seconds");
// }

// Close the database connection
$pdo = null;

?>