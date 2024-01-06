<?php
//-----------------------------------------------------------------------------------------------------------
// Defining Variables
// Log file for debugging
$logFile = '/var/www/html/exercise2/webLogFile.log';

// Database connection details
$database_host = 'DB';
$database_user = 'webprog';
$database_password = 'webprog';
$database_name = 'webprog';

//-----------------------------------------------------------------------------------------------------------
// Defining functions

// Debugging function, Output a timestamped message with a newline
function logMessage($message)
{
    echo "[" . date("Y-m-d H:i:s") . "] " . $message . " <br>";
    global $logFile;
    file_put_contents($logFile, "[" . date("Y-m-d H:i:s") . "] " . $message . "\n", FILE_APPEND);
}

// Function to validate date format
function isValidDateFormat($date)
{
    $parsedDate = date_parse($date);
    return $parsedDate['error_count'] === 0 && checkdate($parsedDate['month'], $parsedDate['day'], $parsedDate['year']);
}

// Function to validate country against a list
function isValidCountry($country, $pdo)
{
    $query = "SELECT COUNT(*) FROM covid_data WHERE Country_Region LIKE ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$country]);
    $count = $stmt->fetchColumn();
    return $count > 0;
}

//-----------------------------------------------------------------------------------------------------------
// Web Application:
// Output the number of confirmed / recovered cases and deaths in a given time period (more than 1 day is possible) for an input-defined country/region

try {
    $pdo = new PDO("mysql:host=$database_host;dbname=$database_name", $database_user, $database_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'];
        $country = $_POST['country'];

        // Validate date formats
        if (!isValidDateFormat($startDate) || !isValidDateFormat($endDate)) {
            // Handle invalid date format error
            logMessage("Invalid date format. Please use MM-DD-YYYY.\n");
            exit;
        }

        // Validate country against the list in the database
        if (!isValidCountry($country, $pdo)) {
            // Handle invalid country error
            logMessage("Invalid country: $country. Please enter a valid country.\n");
            echo "List of valid countries:";
            // Output the list of valid countries from the database
            $validCountries = $pdo->query("SELECT DISTINCT Country_Region FROM covid_data")->fetchAll(PDO::FETCH_COLUMN);
            echo "<ul>";
            foreach ($validCountries as $validCountry) {
                echo "<li>$validCountry</li>";
            }
            echo "</ul>";
            exit;
        }

        // Query the database using prepared statements
        $query = "SELECT SUM(confirmed) AS confirmed, SUM(recovered) AS recovered, SUM(deaths) AS deaths 
                FROM covid_data 
                WHERE Last_Update BETWEEN ? AND ? AND Country_Region LIKE ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$startDate, $endDate, $country]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Extract results
        $totalConfirmed = $result['confirmed'];
        $totalRecovered = $result['recovered'];
        $totalDeaths = $result['deaths'];
    }
} catch (PDOException $e) {
    die('Database Error: ' . $e->getMessage());
} finally {
    $pdo = null;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>COVID-19 Statistics</title>
</head>

<body>
    <h1>COVID-19 Statistics</h1>

    <form method="post" action="">
    <label for="start_date">Start Date:</label>
    <input type="date" name="start_date" pattern="\d{4}-\d{2}-\d{2}" required>
    
    <label for="end_date">End Date:</label>
    <input type="date" name="end_date" pattern="\d{4}-\d{2}-\d{2}" required>
    
    <label for="country">Country/Region:</label>
    <input type="text" name="country" required>
    
    <button type="submit">Submit</button>
    </form>


    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <h2>Results:</h2>
        <p>Total Confirmed Cases: <?php echo $totalConfirmed; ?></p>
        <p>Total Recovered Cases: <?php echo $totalRecovered; ?></p>
        <p>Total Deaths: <?php echo $totalDeaths; ?></p>
    <?php endif; ?>

</body>

</html>
