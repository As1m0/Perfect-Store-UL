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
}