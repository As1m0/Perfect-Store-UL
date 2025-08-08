<?php
class ProductService {
    private $db;

    public function __construct($cfg) {
        $database = new Database();
        $this->db = $database->getConnection($cfg);
    }


    public function getProductList($options) {  
        try {
            $query = QueryGenerator::generateProductListQuery($options);
            $params = QueryGenerator::getQueryParameters($options['filters'] ?? []);
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            $results = $stmt->fetchAll();
            
            // Convert numeric strings to appropriate types
            foreach ($results as &$row) {
                $row['ean'] = (string) $row['ean'];
                $row['brand_id'] = (int) $row['brand_id'];
                $row['category_id'] = (int) $row['category_id'];
                $row['subcategory_id'] = (int) $row['subcategory_id'];
            }
            
            return $results;
            
        } catch (Exception $e) {
            error_log("Error in getProductList: " . $e->getMessage());
            throw new Exception("Failed to fetch product list: " . $e->getMessage() . " Query: " . $query);
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
    
    public function removeProduct($ean) {
        try {
            // Start transaction for data consistency
            $this->db->beginTransaction();
            
            // First, check if product exists in products table
            $checkQuery = "SELECT COUNT(*) FROM products WHERE ean = :ean";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->execute([':ean' => $ean]);
            
            if ($checkStmt->fetchColumn() === 0) {
                throw new Exception("Product with EAN $ean does not exist");
            }
            
            // Delete related data in correct order (due to foreign key constraints)
            
            // 1. Delete from history table (references products.ean)
            $historyQuery = "DELETE FROM oos_history WHERE ean = :ean";
            $historyStmt = $this->db->prepare($historyQuery);
            $historyStmt->execute([':ean' => $ean]);
            
            // 2. Delete from product_flags table (references products.ean) 
            $flagsQuery = "DELETE FROM product_flags WHERE ean = :ean";
            $flagsStmt = $this->db->prepare($flagsQuery);
            $flagsStmt->execute([':ean' => $ean]);
            
            // 3. Delete from urls table (references products.ean)
            $urlsQuery = "DELETE FROM urls WHERE ean = :ean";
            $urlsStmt = $this->db->prepare($urlsQuery);
            $urlsStmt->execute([':ean' => $ean]);
            
            // 4. Finally delete from products table
            $productQuery = "DELETE FROM products WHERE ean = :ean";
            $productStmt = $this->db->prepare($productQuery);
            $productStmt->execute([':ean' => $ean]);
            
            // Check if product was actually deleted
            if ($productStmt->rowCount() === 0) {
                throw new Exception("Failed to delete product from products table");
            }
            
            // Commit transaction
            $this->db->commit();
            
            // Return success information
            return [
                'success' => true,
                'ean' => $ean,
                'deleted_records' => [
                    'history' => $historyStmt->rowCount(),
                    'product_flags' => $flagsStmt->rowCount(), 
                    'urls' => $urlsStmt->rowCount(),
                    'products' => $productStmt->rowCount()
                ]
            ];
            
        } catch (Exception $e) {
            // Rollback transaction on error
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            
            error_log("Error in removeProduct: " . $e->getMessage());
            throw new Exception("Failed to remove product: " . $e->getMessage());
        }
    }

}