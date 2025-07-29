<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
// Database configuration
$host = 'localhost';
$username = 'website';
$password = 'gehNuf-nakjiz-wamna3';
$database = 'OOS';
$csvFile = 'data.csv';

// Create mysqli connection
$mysqli = new mysqli($host, $username, $password, $database);

// Check connection
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$notUploadaed = [];

// Open and read CSV file
if (($handle = fopen($csvFile, "r")) !== FALSE) {
    
    
    while (($data = fgetcsv($handle, null, ";")) !== FALSE) {
        try {
            // Assuming CSV columns are: ean, shop_id, url
           // print_r($data); // Debugging line to see the CSV data
            if (count($data) < 3) {
                //echo "Error: Not enough columns in CSV row. Skipping...\n";
                continue;
            }
            $ean = $data[0];
            $shop_id = $data[1];
            $url = $data[2];

            $stmt = $mysqli->prepare("INSERT INTO `urls` (`ean`, `shop_id`, `url`) VALUES (?, ?, ?)");
            if ($stmt === false) {
                //echo "Prepare failed: " . $mysqli->error . ". Skipping row...\n";
                continue;
            }
            $stmt->bind_param("iis", $ean, $shop_id, $url);

            if ($stmt->execute()) {
                echo "New record created successfully\n";
            } else {
                //echo "Error: " . $stmt->error ."EAN: ".$data[0] ." Skipping row...\n";
            }
            $stmt->close();
        } catch (Exception $e) {
            //echo "Unexpected error: " . $e->getMessage() . ". Skipping row...\n";
            $notUploadaed[] = $ean;
            continue;
        }
    }
    
    fclose($handle);
}

$mysqli->close();

print_r($notUploadaed); // Output the not uploaded records for debugging