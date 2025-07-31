<?php
class OOSService {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    /**
     * Get OOS data with filters and sorting
     */
    public function getOOSData($options) {
        try {
            $query = QueryGenerator::generateOOSQuery($options);
            $params = QueryGenerator::getQueryParameters($options['filters'] ?? []);
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            $results = $stmt->fetchAll();
            
            // Convert numeric strings to appropriate types
            foreach ($results as &$row) {
                $row['ean'] = (string) $row['ean'];
                $row['last_status'] = (int) $row['last_status'];
                $row['oos_percentage'] = (float) $row['oos_percentage'];
                $row['days_oos'] = (int) $row['days_oos'];
                $row['product_url'] = $row['product_url'] ?? '';
            }
            
            return $results;
            
        } catch (Exception $e) {
            error_log("Error in getOOSData: " . $e->getMessage());
            throw new Exception("Failed to fetch OOS data: " . $e->getMessage());
        }
    }

    /**
     * Get all categories
     */
    public function getCategories() {
        try {
            $query = "SELECT id, name FROM categories ORDER BY name";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error in getCategories: " . $e->getMessage());
            throw new Exception("Failed to fetch categories");
        }
    }

    /**
     * Get all brands
     */
    public function getBrands() {
        try {
            $query = "SELECT id, name FROM brands ORDER BY name";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error in getBrands: " . $e->getMessage());
            throw new Exception("Failed to fetch brands");
        }
    }

    /**
     * Get all subcategories
     */
    public function getSubcategories() {
        try {
            $query = "SELECT id, name FROM subcategories ORDER BY name";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error in getSubcategories: " . $e->getMessage());
            throw new Exception("Failed to fetch subcategories");
        }
    }

    /**
     * Get all shops
     */
    public function getShops() {
        try {
            $query = "SELECT id, name, base_url FROM shops ORDER BY name";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error in getShops: " . $e->getMessage());
            throw new Exception("Failed to fetch shops");
        }
    }

    /**
     * Get summary statistics
     */
    public function getSummaryStats($shopId = 3) {
        try {
            $query = "
            SELECT 
                COUNT(DISTINCT p.ean) as total_products,
                COUNT(DISTINCT CASE WHEN latest.is_available = 1 THEN p.ean END) as in_stock_products,
                COUNT(DISTINCT CASE WHEN latest.is_available = 0 THEN p.ean END) as out_of_stock_products,
                COUNT(DISTINCT CASE WHEN latest.is_available IS NULL THEN p.ean END) as no_data_products,
                ROUND(AVG(CASE 
                    WHEN total_checks.total_records = 0 THEN 0
                    ELSE ((total_checks.total_records - COALESCE(out_of_stock.oos_count, 0)) * 100.0 / total_checks.total_records)
                END), 2) as avg_availability_percentage
            FROM products p
            INNER JOIN urls u ON p.ean = u.ean AND u.shop_id = ?
            LEFT JOIN (
                SELECT 
                    h1.ean,
                    h1.is_available
                FROM oos_history h1
                INNER JOIN (
                    SELECT ean, MAX(date_checked) as max_date
                    FROM oos_history
                    WHERE shop_id = ?
                    GROUP BY ean
                ) h2 ON h1.ean = h2.ean AND h1.date_checked = h2.max_date
                WHERE h1.shop_id = ?
            ) latest ON p.ean = latest.ean
            LEFT JOIN (
                SELECT 
                    ean,
                    COUNT(*) as total_records
                FROM oos_history
                WHERE shop_id = ?
                GROUP BY ean
            ) total_checks ON p.ean = total_checks.ean
            LEFT JOIN (
                SELECT 
                    ean,
                    COUNT(*) as oos_count
                FROM oos_history
                WHERE is_available = 0 AND shop_id = ?
                GROUP BY ean
            ) out_of_stock ON p.ean = out_of_stock.ean";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$shopId, $shopId, $shopId, $shopId, $shopId]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Error in getSummaryStats: " . $e->getMessage());
            throw new Exception("Failed to fetch summary statistics");
        }
    }

    public function getProductByEAN($ean): array{
        try {
                    $query = "SELECT 
            b.name AS brand,
            c.name AS category,
            sc.name AS subcategory,
            p.ean,
            p.name,
            GROUP_CONCAT(
                CONCAT(s.name, ': ', u.url) 
                ORDER BY s.name 
                SEPARATOR ' | '
            ) AS urls
        FROM products p
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN subcategories sc ON p.subcategory_id = sc.id
        LEFT JOIN urls u ON p.ean = u.ean
        LEFT JOIN shops s ON u.shop_id = s.id
        WHERE p.ean = {$ean}  -- Only active URLs
        GROUP BY p.ean, b.name, c.name, sc.name, p.name
        ORDER BY b.name, c.name, sc.name, p.name;";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error in getShops: " . $e->getMessage());
            throw new Exception("Failed to fetch product");
        }
    }



    public function untrackProduct($ean, $shopId): bool {  
        try {
            $query = "DELETE FROM urls WHERE ean = :ean AND shop_id = :shopId";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$ean, $shopId]);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error in deleteProduct: " . $e->getMessage());
            throw new Exception("Failed to delete product");
        }
    }

    public function addProduct($ean, $name, $brandId, $categoryId, $subcategoryId): bool {
        try {
                $query = "INSERT INTO products (ean, name, brand_id, category_id, subcategory_id) VALUES (:ean, :name, :brandId, :categoryId, :subcategoryId)";
                $stmt = $this->db->prepare($query);
                return $stmt->execute([
                    ':ean' => $ean,
                    ':name' => $name,
                    ':brandId' => $brandId,
                    ':categoryId' => $categoryId,
                    ':subcategoryId' => $subcategoryId
                ]);
        } catch (Exception $e) {
            error_log("Error in addProduct: " . $e->getMessage());
            throw new Exception("Failed to add product");
        }
    }

    public function editProductUrl($ean, $shopId, $productUrl): bool {
        try {
            $query = "UPDATE urls SET url = :url WHERE ean = :ean AND shop_id = :shopId";
            $stmt = $this->db->prepare($query);
            return $stmt->execute([':url' => $productUrl, ':ean' => $ean, ':shopId' => $shopId]);
        } catch (Exception $e) {
            error_log("Error in editProductUrl: " . $e->getMessage());
            throw new Exception("Failed to update product URL");
        }
    }

    public function getProductByEANwithIds($ean): array {
        try {
            $query = "SELECT 
                p.ean,
                p.name,
                b.id AS brand_id,
                b.name AS brand_name,
                c.id AS category_id,
                c.name AS category_name,
                sc.id AS subcategory_id,
                sc.name AS subcategory_name
            FROM products p
            LEFT JOIN brands b ON p.brand_id = b.id
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN subcategories sc ON p.subcategory_id = sc.id
            WHERE p.ean = :ean";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':ean' => $ean]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error in getProductByEANwithIds: " . $e->getMessage());
            throw new Exception("Failed to fetch product by EAN");
        }
    }

    public function addProductLink($ean, $shopId, $productUrl): bool {
        try {
            // Check if the link already exists
            $checkQuery = "SELECT COUNT(*) FROM urls WHERE ean = :ean AND shop_id = :shopId";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->execute([':ean' => $ean, ':shopId' => $shopId]);
            if ($checkStmt->fetchColumn() > 0) {
                // Optionally, update the URL if it already exists
                $updateQuery = "UPDATE urls SET url = :url WHERE ean = :ean AND shop_id = :shopId";
                $updateStmt = $this->db->prepare($updateQuery);
                return $updateStmt->execute([':url' => $productUrl, ':ean' => $ean, ':shopId' => $shopId]);
            }
            $query = "INSERT INTO urls (ean, shop_id, url) VALUES (:ean, :shopId, :url)";
            $stmt = $this->db->prepare($query);
            return $stmt->execute([':ean' => $ean, ':shopId' => $shopId, ':url' => $productUrl]);
        } catch (Exception $e) {
            error_log("Error in addProductLink: " . $e->getMessage());
            throw new Exception("Failed to add product link");
        }
    }

    public function getOOSChartData($startDate = null, $endDate = null) {
    try {
        $whereClause = "";
        $params = [];
        
        if ($startDate && $endDate) {
            $whereClause = "WHERE h.date_checked BETWEEN ? AND ?";
            $params = [$startDate, $endDate];
        } elseif ($startDate) {
            $whereClause = "WHERE h.date_checked >= ?";
            $params = [$startDate];
        } elseif ($endDate) {
            $whereClause = "WHERE h.date_checked <= ?";
            $params = [$endDate];
        }
        
        $query = "
        SELECT 
            s.name AS shop_name,
            s.id AS shop_id,
            h.date_checked,
            ROUND(
                (COUNT(CASE WHEN h.is_available = 1 THEN 1 END) * 100.0 / COUNT(*)), 
                2
            ) AS availability_percentage
        FROM oos_history h
        INNER JOIN shops s ON h.shop_id = s.id
        {$whereClause}
        GROUP BY s.id, s.name, h.date_checked
        ORDER BY h.date_checked ASC, s.name ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll();
        
        // Transform data for chart format
        $chartData = $this->transformToChartData($results);
        
        return $chartData;
        
        } catch (Exception $e) {
            error_log("Error in getOOSChartData: " . $e->getMessage());
            throw new Exception("Failed to fetch OOS chart data");
        }
    }

    private function transformToChartData($results) {
        $labels = [];
        $shopData = [];
        
        // Process results
        foreach ($results as $row) {
            $date = $row['date_checked'];
            $shopName = $row['shop_name'];
            $percentage = (float) $row['availability_percentage'];
            
            // Collect unique dates for labels
            if (!in_array($date, $labels)) {
                $labels[] = $date;
            }
            
            // Initialize shop data if not exists
            if (!isset($shopData[$shopName])) {
                $shopData[$shopName] = [];
            }
            
            $shopData[$shopName][$date] = $percentage;
        }
        
        // Sort labels chronologically
        sort($labels);
        
        // Create datasets
        $datasets = [];
        $colors = [
            '#667eea', '#764ba2', '#f093fb', '#f5576c', 
            '#4facfe', '#00f2fe', '#43e97b', '#38f9d7',
            '#ffecd2', '#fcb69f', '#a8edea', '#fed6e3'
        ];
        
        $colorIndex = 0;
        foreach ($shopData as $shopName => $data) {
            $dataPoints = [];
            
            // Fill data points for each date
            foreach ($labels as $date) {
                $dataPoints[] = isset($data[$date]) ? $data[$date] : null;
            }
            
            $datasets[] = [
                'label' => $shopName,
                'data' => $dataPoints,
                'borderColor' => $colors[$colorIndex % count($colors)],
                'backgroundColor' => $colors[$colorIndex % count($colors)] . '20', // Add transparency
                'borderWidth' => 2,
                'fill' => false,
                'tension' => 0.1
            ];
            
            $colorIndex++;
        }
        
        return [
            'labels' => $labels,
            'datasets' => $datasets
        ];
    }

    

}