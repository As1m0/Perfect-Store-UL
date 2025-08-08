<?php
class OOSService {
    private $db;

    public function __construct($cfg) {
        $database = new Database();
        $this->db = $database->getConnection($cfg);
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

    public function getOOSChartData($startDate = null, $endDate = null, $groupBy = 'weekly') {
        try {
            $whereClause = "WHERE a.period_type = ?";
            $params = [$groupBy];

            // Add date filtering if present
            if ($startDate && $endDate) {
                if ($groupBy === 'daily') {
                    // e.g., '2025-08-01'
                    $whereClause .= " AND a.period_value BETWEEN ? AND ?";
                    $params[] = $startDate;
                    $params[] = $endDate;
                } elseif ($groupBy === 'monthly') {
                    // e.g., '2025-07'
                    $whereClause .= " AND a.period_value BETWEEN ? AND ?";
                    $params[] = substr($startDate, 0, 7);
                    $params[] = substr($endDate, 0, 7);
                } elseif ($groupBy === 'weekly') {
                    // e.g., '202531'
                    $params[] = date('oW', strtotime($startDate));
                    $params[] = date('oW', strtotime($endDate));
                    $whereClause .= " AND a.period_value BETWEEN ? AND ?";
                } elseif ($groupBy === 'yearly') {
                    // e.g., '2025'
                    $params[] = date('Y', strtotime($startDate));
                    $params[] = date('Y', strtotime($endDate));
                    $whereClause .= " AND a.period_value BETWEEN ? AND ?";
                }
            }

            $query = "
                SELECT 
                    s.name AS shop_name,
                    s.id AS shop_id,
                    a.period_value AS period,
                    a.oos_percentage,
                    a.unavailable_products
                FROM oos_aggregates a
                INNER JOIN shops s ON a.shop_id = s.id
                {$whereClause}
                ORDER BY a.period_value ASC, s.name ASC
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $results = $stmt->fetchAll();

            return $this->transformToChartData($results, $groupBy);

        } catch (Exception $e) {
            error_log("Error in getOOSChartData: " . $e->getMessage());
            throw new Exception("Failed to fetch OOS chart data");
        }
    }

    private function transformToChartData($results, $groupBy) {
        $rawLabels = [];
        $formattedLabels = [];
        $shopData = [];

        // Collect raw periods
        foreach ($results as $row) {
            $period = $row['period'];
            $shopName = $row['shop_name'];
            $oooProducts = $row['unavailable_products'];
            $percentage = (float) $row['oos_percentage'];

            // Collect raw periods
            if (!in_array($period, $rawLabels)) {
                $rawLabels[] = $period;
            }

            // Initialize shop data if not exists
            if (!isset($shopData[$shopName])) {
                $shopData[$shopName] = [];
            }

            $shopData[$shopName][$period] = $percentage;
            //$shopData[$shopName][$oooProducts] = $oooProducts;
        }

        // Sort raw labels (periods) chronologically
        sort($rawLabels);

        // Format labels
        foreach ($rawLabels as $raw) {
            switch ($groupBy) {
                case 'weekly':
                    // Format: 202531 => "Week 31, 2025"
                    $year = substr($raw, 0, 4);
                    $week = substr($raw, 4);
                    $formattedLabels[$raw] = "Week $week, $year";
                    break;

                case 'monthly':
                    // Format: 2025-08 => "Aug 2025"
                    $dt = DateTime::createFromFormat('Y-m', $raw);
                    $formattedLabels[$raw] = $dt ? $dt->format('M Y') : $raw;
                    break;

                case 'yearly':
                    $formattedLabels[$raw] = $raw;
                    break;

                case 'daily':
                default:
                    // Format: 2025-08-01 => "Aug 1, 2025"
                    $dt = DateTime::createFromFormat('Y-m-d', $raw);
                    $formattedLabels[$raw] = $dt ? $dt->format('M j, Y') : $raw;
                    break;
            }
        }

        // Create datasets
        $datasets = [];
        $colors = [
            '#667eea', '#e4a752', '#f5576c', '#a8edea', 
            '#4facfe', '#00f2fe', '#43e97b', '#38f9d7',
            '#ffecd2', '#fcb69f', '#a8edea', '#fed6e3'
        ];

        $colorIndex = 0;
        foreach ($shopData as $shopName => $data) {
            $dataPoints = [];

            foreach ($rawLabels as $raw) {
                $dataPoints[] = isset($data[$raw]) ? $data[$raw] : null;
            }

            $datasets[] = [
                'label' => $shopName,
                'data' => $dataPoints,
                'borderColor' => $colors[$colorIndex % count($colors)],
                'backgroundColor' => $colors[$colorIndex % count($colors)] . '20',
                'borderWidth' => 2,
                'fill' => false,
                'tension' => 0.1
            ];

            $colorIndex++;
        }

        return [
            'labels' => array_values($formattedLabels),
            'datasets' => $datasets
        ];
    }

    public function getProductHistory($ean, $shopId, $startDate = null, $endDate = null) {
        try {
            $whereConditions = ["h.ean = ?"];
            $params = [$ean];
            
            // Optional shop filter
            if ($shopId) {
                $whereConditions[] = "h.shop_id = ?";
                $params[] = $shopId;
            }
            
            // Optional date range filter
            if ($startDate && $endDate) {
                $whereConditions[] = "h.date_checked BETWEEN ? AND ?";
                $params[] = $startDate;
                $params[] = $endDate;
            } elseif ($startDate) {
                $whereConditions[] = "h.date_checked >= ?";
                $params[] = $startDate;
            } elseif ($endDate) {
                $whereConditions[] = "h.date_checked <= ?";
                $params[] = $endDate;
            }
            
            $whereClause = implode(' AND ', $whereConditions);
            
            $query = "
            SELECT 
                h.date_checked,
                h.is_available,
                s.name AS shop_name,
                s.id AS shop_id
            FROM oos_history h
            INNER JOIN shops s ON h.shop_id = s.id
            WHERE {$whereClause}
            ORDER BY h.date_checked ASC, s.name ASC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $results = $stmt->fetchAll();
            
            // Convert data types
            foreach ($results as &$row) {
                $row['is_available'] = (int) $row['is_available'];
                $row['shop_id'] = (int) $row['shop_id'];
            }

            return $results;
            
        } catch (Exception $e) {
            error_log("Error in getProductHistory: " . $e->getMessage());
            throw new Exception("Failed to fetch product history");
        }
    }

    

}