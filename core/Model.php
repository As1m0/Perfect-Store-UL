<?php

abstract class Model
{
    public static function GetPageData(string $page) : array
    {
        //return ["page" => $page, "template" => "main.html", "fullTemplate" => false, "Class" => "IndexPage"];
        global $cfg;
        $pagesJson = json_decode(file_get_contents($cfg["contentFolder"]."/pages.json"), true);
        if($pagesJson !== null)
        {
            $pageData = null;
            foreach ($pagesJson as $p)
            {
                if($p["page"] == $page)
                {
                    $pageData = $p;
                    break;
                }
            }
            if($pageData !== null)
            {
                return $pageData;
            }
            else
            {
                throw new NotFoundException("A megadott oldal nem található!");
            }
        }
        else
        {
            throw new Exception("Az oldalak feldolgozása hibára futott!");
        }
    }
    
    public static function LoadText(string $page, string $flag) : array
    {
        //return ["flag" => $flag, "text" => "ASD"];
        global $cfg;
        $contentJson = json_decode(file_get_contents($cfg["contentFolder"]."/content.json"), true);
        if($contentJson !== null)
        {
            if(isset($contentJson[$page]) && isset($contentJson[$page][$flag]))
            {
                return ["flag" => $flag, "text" => $contentJson[$page][$flag]];
            }
            else
            {
                throw new NotFoundException("A megadott oldal ($page) és a megadott flag ($flag) nem található a tartlmak között!");
            }
        }
        else
        {
            throw new Exception("A tartalmakat tároló JSON feldolgozása meghiúsult!");
        }
    }
    
    public static function GetModules() : array
    {
        global $cfg;
        $moduleJson = json_decode(file_get_contents($cfg["contentFolder"]."/modules.json"), true);
        if($moduleJson !== null)
        {
            return $moduleJson;
        }
        else
        {
            throw new Exception("A modulokat tartalmazó JSON feldolgozása meghiúsult!");
        }
    }

        public static function GetPageDataDB(string $page) : array
    {
        try {
              $result = DBHandler::RunQuery("SELECT * FROM `pages` WHERE `pageKey` = ?", [new DBParam(DBTypes::String, $page)]);
              if($result->num_rows > 0)
              {
                  return $result->fetch_assoc();
                  //return $result->fetch_all(MYSQLI_ASSOC);
              }
              else
              {
                  throw new NotFoundException("A megadott oldal nem található!");
              }
        } catch (Exception $e) {
             throw new DBException("Az oldal lekérdezése során hiba történt!", 0, $e);
        }
    }

    public static function GetProducts($query) : array
    {
        $result = DBHandler::RunQuery($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public static function uploadScrapeResult($ean, $shopId, $isAvailable){
        try {
            DBHandler::RunQuery("
                INSERT INTO `history` (`ean`, `shop_id`, `is_available`)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE is_available = VALUES(is_available)
            ", [
                new DBParam(DBTypes::Int, $ean),
                new DBParam(DBTypes::Int, $shopId),
                new DBParam(DBTypes::Int, $isAvailable)
            ]);
        } catch (Exception $e) {
            throw new DBException("Az adat feltöltése során hiba történt!", 0, $e);
        }
    }


    /**
     * Generate dynamic OOS analysis query with filtering and sorting
     * @param array $options - Query options
     * @return string - Generated SQL query
     */
    public static function generateOOSQuery(array $options = []) : string {
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
        COALESCE(latest.is_available, -1) AS last_status,
        CASE 
            WHEN total_checks.total_records = 0 THEN 0
            ELSE ROUND(((total_checks.total_records - out_of_stock.oos_count) * 100.0 / total_checks.total_records), 2)
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
            $categoryList = implode(', ', array_map(function($cat) {
                return "'" . str_replace("'", "''", $cat) . "'";
            }, $categories));
            $whereConditions[] = "c.name IN ({$categoryList})";
        }

        // Subcategory filter  
        if (!empty($filters['subcategory'])) {
            $subcategories = is_array($filters['subcategory']) ? $filters['subcategory'] : [$filters['subcategory']];
            $subcategoryList = implode(', ', array_map(function($subcat) {
                return "'" . str_replace("'", "''", $subcat) . "'";
            }, $subcategories));
            $whereConditions[] = "sc.name IN ({$subcategoryList})";
        }

        // Brand filter
        if (!empty($filters['brand'])) {
            $brands = is_array($filters['brand']) ? $filters['brand'] : [$filters['brand']];
            $brandList = implode(', ', array_map(function($brand) {
                return "'" . str_replace("'", "''", $brand) . "'";
            }, $brands));
            $whereConditions[] = "b.name IN ({$brandList})";
        }

        // Last status filter
        if (isset($filters['last_status'])) {
            $statuses = is_array($filters['last_status']) ? $filters['last_status'] : [$filters['last_status']];
            $statusList = implode(', ', array_map('intval', $statuses));
            $whereConditions[] = "COALESCE(latest.is_available, -1) IN ({$statusList})";
        }

        // Add WHERE clause if there are conditions
        if (!empty($whereConditions)) {
            $query .= " WHERE " . implode(' AND ', $whereConditions);
        }

        // Add ORDER BY clause
        $query .= " ORDER BY {$sortColumn} {$sortDirection}";

        return $query;
    }
}
