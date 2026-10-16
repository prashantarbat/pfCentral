<?php

// Get all SVG files in the current folder
$svgFiles = glob('*.svg');

// Set the number of columns
$columns = 6;

// Loop through each SVG file
foreach ($svgFiles as $index => $svgFile) {
    // Get the file name
    $fileName = basename($svgFile);

    // Read the contents of the SVG file
    $svgContent = file_get_contents($svgFile);

    // Output the SVG content within a column
    echo "<div style='width: 200px; height: 200px; margin: 10px; float: left;'>";
    echo "<h4 style='text-align: center;'>$fileName</h4>";
    echo $svgContent;
    echo "</div>";

    // Check if the next column needs to start
    if (($index + 1) % $columns === 0) {
        echo "<div style='clear: both;'></div>"; // Start a new row
    }
}

// Clear any remaining floats
echo "<div style='clear: both;'></div>";

?>
