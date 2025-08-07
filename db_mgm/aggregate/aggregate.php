<?php
require_once 'OOSAggregator.php';
$pdo = new PDO(
                "mysql:host=" . "localhost" . ";dbname=" . "perfec14_OOS" . ";charset=utf8mb4",
                "perfec14_website",
                "Diamond92!",
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
$aggregator = new OOSAggregator($pdo);
$aggregator->aggregateOOSPeriods(); // For today
//$aggregator->backfill('2025-06-01', '2025-08-05');
echo ("Aggregation runned successfully at: " . date("Y-m-d") . " \n");