<?php
// GitHub API authentication PTA
define('PERSONAL_ACCESS_TOKEN','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');

// CSV directory
define('CSV_DIRECTORY', '/var/www/html/exercise2/csse_covid_19_daily_reports');

// GitHub repository URL
$githubRepositoryUrl = 'https://github.com/CSSEGISandData/COVID-19/blob/master/csse_covid_19_data/csse_covid_19_daily_reports';

// Function to download a file from GitHub
function downloadFile($url, $destination)
{
    // Debugging: Output a timestamped message
    logMessage(" Downloading file: $url <br>");

    // Download the file
    $fileContent = file_get_contents($url);

    if ($fileContent !== false) {
        file_put_contents($destination, $fileContent);
        // Debugging: Output a timestamped message
        logMessage(" File downloaded successfully: $destination <br>");
    } else {
        // Debugging: Output a timestamped message
        logMessage(" Error downloading file: $url <br>");
    }
}

// Function to get CSV file links from GitHub repository
function getCSVFileLinks($repositoryUrl)
{
    // GitHub API endpoint for fetching the contents of a repository
    $apiUrl = "https://api.github.com/repos" . parse_url($repositoryUrl, PHP_URL_PATH) . "/contents?access_token=".PERSONAL_ACCESS_TOKEN;

    // Fetch the contents of the repository using GitHub API
    $repoContents = json_decode(file_get_contents($apiUrl), true);

    // Filter out only the CSV files
    $csvFiles = is_array($repoContents) ? array_filter($repoContents, function ($file) {
        return pathinfo($file['name'], PATHINFO_EXTENSION) === 'csv';
    }) : [];

    // Return an array of CSV file download links
    return array_map(function ($file) {
        return $file['download_url'];
    }, $csvFiles);
}

// Get CSV file links from GitHub
$csvFileLinks = getCSVFileLinks($githubRepositoryUrl);

// Download the CSV files
foreach ($csvFileLinks as $link) {
    $destination = CSV_DIRECTORY . '/' . basename($link);

    // Download the file
    downloadFile($link, $destination);
}

?>
