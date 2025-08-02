<?php
class QueryGenerator {
    /**
     * Generate dynamic OOS analysis query with filtering and sorting
     * @param array $options - Query options
     * @return string - Generated SQL query
     */
    public static function generateOOSQuery($options = []) {
        $shopId = $options['shopId'] ?? 3;
        $filters = $options['filters'] ?? [];
        $sort = $options['sort'] ?? ['column' => 'category', 'direction' => 'ASC'];
        
        // Validate sort column
        $validSortColumns = [
            'category', 'subcategory', 'brand', 'ean', 'name', 
            'last_status', 'oos_percentage', 'days_oos'
        ];
        
        $sortColumn = in_array($sort['column'], $validSortColumns) ? $sort['column'] : 'category';
        $sortDirection = in_array(strtoupper($sort['direction']), ['ASC', 'DESC']) 
            ? strtoupper($sort['direction']) 
            : 'ASC';

        // Base query
        $query = "
                SELECT 
                    c.name AS category,
                    sc.name AS subcategory,
                    b.name AS brand,
                    p.ean,
                    p.name,
                    u.url AS product_url,
                    COALESCE(latest.is_available, -1) AS last_status,
                    CASE 
    WHEN total_checks.total_records = 0 THEN 0
    ELSE ROUND((COALESCE(out_of_stock.oos_count, 0) * 100.0 / total_checks.total_records), 2)
END AS oos_percentage,
                    COALESCE(oos_days.days_oos, 0) AS days_oos
                FROM products p
                INNER JOIN urls u ON p.ean = u.ean AND u.shop_id = {$shopId}
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN subcategories sc ON p.subcategory_id = sc.id
                LEFT JOIN brands b ON p.brand_id = b.id

                -- Get the latest status for each product
                LEFT JOIN (
                    SELECT 
                        h1.ean,
                        h1.is_available
                    FROM oos_history h1
                    INNER JOIN (
                        SELECT ean, MAX(date_checked) as max_date
                        FROM oos_history
                        WHERE shop_id = {$shopId}
                        GROUP BY ean
                    ) h2 ON h1.ean = h2.ean AND h1.date_checked = h2.max_date
                    WHERE h1.shop_id = {$shopId}
                ) latest ON p.ean = latest.ean

                -- Count total history records per product
                LEFT JOIN (
                    SELECT 
                        ean,
                        COUNT(*) as total_records
                    FROM oos_history
                    WHERE shop_id = {$shopId}
                    GROUP BY ean
                ) total_checks ON p.ean = total_checks.ean

                -- Count out-of-stock records per product
                LEFT JOIN (
                    SELECT 
                        ean,
                        COUNT(*) as oos_count
                    FROM oos_history
                    WHERE is_available = 0 AND shop_id = {$shopId}
                    GROUP BY ean
                ) out_of_stock ON p.ean = out_of_stock.ean

                -- Calculate days out of stock
                LEFT JOIN (
                    SELECT 
                        ean,
                        COUNT(DISTINCT date_checked) as days_oos
                    FROM oos_history
                    WHERE is_available = 0 AND shop_id = {$shopId}
                    GROUP BY ean
                ) oos_days ON p.ean = oos_days.ean";

        // Add WHERE clause for filters
        $whereConditions = [];

        // Category filter
        if (!empty($filters['category'])) {
            $categories = is_array($filters['category']) ? $filters['category'] : [$filters['category']];
            $placeholders = str_repeat('?,', count($categories) - 1) . '?';
            $whereConditions[] = "c.name IN ({$placeholders})";
        }

        // Subcategory filter  
        if (!empty($filters['subcategory'])) {
            $subcategories = is_array($filters['subcategory']) ? $filters['subcategory'] : [$filters['subcategory']];
            $placeholders = str_repeat('?,', count($subcategories) - 1) . '?';
            $whereConditions[] = "sc.name IN ({$placeholders})";
        }

        // Brand filter
        if (!empty($filters['brand'])) {
            $brands = is_array($filters['brand']) ? $filters['brand'] : [$filters['brand']];
            $placeholders = str_repeat('?,', count($brands) - 1) . '?';
            $whereConditions[] = "b.name IN ({$placeholders})";
        }

        // Last status filter
        if (isset($filters['last_status'])) {
            $statuses = is_array($filters['last_status']) ? $filters['last_status'] : [$filters['last_status']];
            $placeholders = str_repeat('?,', count($statuses) - 1) . '?';
            $whereConditions[] = "COALESCE(latest.is_available, -1) IN ({$placeholders})";
        }

        // Add WHERE clause if there are conditions
        if (!empty($whereConditions)) {
            $query .= "\nWHERE " . implode(' AND ', $whereConditions);
        }

        // Add ORDER BY clause
        $query .= "\nORDER BY {$sortColumn} {$sortDirection}";

        return $query;
    }

    /**
     * Get parameters array for prepared statement
     */
    public static function getQueryParameters($filters = []) {
        $params = [];

        if (!empty($filters['category'])) {
            $categories = is_array($filters['category']) ? $filters['category'] : [$filters['category']];
            $params = array_merge($params, $categories);
        }

        if (!empty($filters['subcategory'])) {
            $subcategories = is_array($filters['subcategory']) ? $filters['subcategory'] : [$filters['subcategory']];
            $params = array_merge($params, $subcategories);
        }

        if (!empty($filters['brand'])) {
            $brands = is_array($filters['brand']) ? $filters['brand'] : [$filters['brand']];
            $params = array_merge($params, $brands);
        }

        if (isset($filters['last_status'])) {
            $statuses = is_array($filters['last_status']) ? $filters['last_status'] : [$filters['last_status']];
            $params = array_merge($params, $statuses);
        }

        return $params;
    }
}