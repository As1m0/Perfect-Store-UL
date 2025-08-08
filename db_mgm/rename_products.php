<!DOCTYPE html>
<html>
<head>
    <title>Product Name Update</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .skip { color: orange; }
        .error { color: red; }
        .summary { background: #f0f0f0; padding: 10px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>Product Name Update Script</h1>
    
<?php
try {
    // Database connection - UPDATE THESE CREDENTIALS
    $host = 's58.tarhely.com';
    $dbname = 'perfec14_OOS';
    $username = 'your_username'; // CHANGE THIS
    $password = 'your_password'; // CHANGE THIS
    
    echo "<p>Connecting to database...</p>";
    
    $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", 'perfec14_external', 'Diamond92!');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p class='success'>Connected successfully!</p>";
    
    // Start transaction for data consistency
    $pdo->beginTransaction();
    echo "<p>Starting transaction...</p>";
    
    // Get all products with their brand names
    $selectQuery = "
        SELECT p.ean, p.name as product_name, b.name as brand_name 
        FROM products p 
        JOIN brands b ON p.brand_id = b.id 
        WHERE p.brand_id IS NOT NULL
    ";
    
    $selectStmt = $pdo->prepare($selectQuery);
    $selectStmt->execute();
    
    $totalProducts = $selectStmt->rowCount();
    echo "<p>Found {$totalProducts} products with brands to process...</p>";
    
    // Prepare update statement
    $updateQuery = "UPDATE products SET name = ? WHERE ean = ?";
    $updateStmt = $pdo->prepare($updateQuery);
    
    $updatedCount = 0;
    $skippedCount = 0;
    
    // Process each product
    while ($row = $selectStmt->fetch(PDO::FETCH_ASSOC)) {
        $brandName = $row['brand_name'];
        $currentProductName = $row['product_name'];
        $ean = $row['ean'];
        
        // Check if brand name is already at the beginning of product name
        if (stripos($currentProductName, $brandName) === 0) {
            echo "<div class='skip'>Skipping EAN {$ean}: Product name already starts with brand name</div>";
            $skippedCount++;
            continue;
        }
        
        // Create new product name with brand prefix
        $newProductName = ucfirst( strtolower($brandName) ) . ' ' . $currentProductName;
        
        // Update the product
        $updateStmt->execute([$newProductName, $ean]);
        
        echo "<div class='success'>Updated EAN {$ean}: '{$currentProductName}' -> '{$newProductName}'</div>";
        $updatedCount++;
    }
    
    // Commit the transaction
    $pdo->commit();
    
    echo "<div class='summary'>";
    echo "<h3>Update completed successfully!</h3>";
    echo "<p><strong>Products updated:</strong> {$updatedCount}</p>";
    echo "<p><strong>Products skipped:</strong> {$skippedCount}</p>";
    echo "<p><strong>Total processed:</strong> {$totalProducts}</p>";
    echo "</div>";
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    echo "<div class='error'><h3>Database error:</h3><p>" . htmlspecialchars($e->getMessage()) . "</p></div>";
} catch (Exception $e) {
    // Rollback transaction on any other error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    echo "<div class='error'><h3>Error:</h3><p>" . htmlspecialchars($e->getMessage()) . "</p></div>";
}
?>

</body>
</html>