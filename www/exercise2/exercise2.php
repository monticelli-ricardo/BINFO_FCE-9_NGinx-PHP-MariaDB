<?php
$database_host = 'DB';
$database_user = 'webprog';
$database_password = 'webprog';
$database_name = 'webprog';

$mysqli = new mysqli($database_host, $database_user, $database_password, $database_name);

if ($mysqli->connect_error) {
    die('Connect Error (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}

// Function to validate date format
function isValidDateFormat($date) {
    $parsedDate = date_parse($date);
    return $parsedDate['error_count'] === 0 && checkdate($parsedDate['month'], $parsedDate['day'], $parsedDate['year']);
}

// Function to validate country against a list
function isValidCountry($country, $mysqli) {
    $query = "SELECT COUNT(*) FROM covid_data WHERE country LIKE ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("s", $country);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    return $count > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    $country = $_POST['country'];

    // Validate date formats
    if (!isValidDateFormat($startDate) || !isValidDateFormat($endDate)) {
        // Handle invalid date format error
        echo "Invalid date format. Please use YYYY-MM-DD.";
        exit;
    }

    // Validate country against the list in the database
    if (!isValidCountry($country, $mysqli)) {
        // Handle invalid country error
        echo "Invalid country. Please enter a valid country.";
        exit;
    }

    // Query the database using prepared statements
    $query = "SELECT SUM(confirmed) AS total_confirmed, SUM(recovered) AS total_recovered, SUM(deaths) AS total_deaths 
              FROM covid_data 
              WHERE date BETWEEN ? AND ? AND country LIKE ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("sss", $startDate, $endDate, $country);
    $stmt->execute();
    $stmt->bind_result($totalConfirmed, $totalRecovered, $totalDeaths);
    $stmt->fetch();
    $stmt->close();
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
        <input type="date" name="start_date" required>

        <label for="end_date">End Date:</label>
        <input type="date" name="end_date" required>

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

<?php
$mysqli->close();
?>
