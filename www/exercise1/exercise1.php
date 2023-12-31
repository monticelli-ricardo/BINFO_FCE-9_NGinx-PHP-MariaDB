<?php

// URL of the target website
$url = "https://www.wort.lu/";

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Fetch the HTML content of the webpage
$html = file_get_contents($url);

// ReGex that matches the "Neueste Artikel" box
$pattern = '/<span class="BrandedHeading_inner__XHzdj">Neueste Artikel<\/span><\/h2><\/div><\/div><\/div><ul class=.*?>(.*?)<\/ul><\/div><\/div><\/section>/';

// Perform the RegEx match
preg_match($pattern, $html, $matches);

// Check if matches were found
if (!empty($matches)) {
    // New Pattern to look only for the h3 element containing the titles
    $titlesPattern = '/<li class="list_listItem__Ax0u_[^>]*"><article><a class=[^>]*><div class="singlelistteaserwithoutimage_teaser__Mjivv"><time class=.*?<\/time>(.*?)<\/div><\/a><\/article><\/li>/';

    // Extract only the titles within the "Neueste Artikel" box
    preg_match_all($titlesPattern, $matches[1], $titles);
    
    // Create a new array to perform additional clean ups
    $titlesArray = $titles[1];

    // Look for the title string inside h3 elements using a new ReGex pattern
    $desiredPattern = '/<h3 class=[^>]*>(.*?)<\/h3>/';

    // Strip out additional elements inside the h3 element, like images, etc.
    $allowed_tags = '<h3>';
    $filteredArray = preg_grep($desiredPattern, $titlesArray);
    
            // Output of the filtered and cleaned titles string
            foreach ($filteredArray as $h3Title) {
                $cleaned_h3Title = strip_tags($h3Title, $allowed_tags);
                echo $cleaned_h3Title . PHP_EOL;    
            }
// If no matches for "Neuste Artikel" box, then print message below
} else {
    echo "No titles were found in the box \"Neueste Artikel\" from $url" . PHP_EOL;
}
?>


