<?php

class OOSAggregator
{
    private $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    /**
     * Aggregates daily, weekly, and monthly OOS stats for a given date
     */
    public function aggregateOOSPeriods($date = null)
    {
        $date = $date ?? date('Y-m-d');
    
        // Daily
        $start = date('Y-m-d 00:00:00', strtotime($date));
        $end = date('Y-m-d 23:59:59', strtotime($date));
        $this->aggregateByPeriod('daily', "DATE(date_checked)", $start, $end);
    
        // Weekly (ISO 8601: Monday-Sunday)
        $weekStart = date('Y-m-d 00:00:00', strtotime('monday this week', strtotime($date)));
        $weekEnd = date('Y-m-d 23:59:59', strtotime('sunday this week', strtotime($date)));
        $this->aggregateByPeriod('weekly', "YEARWEEK(date_checked, 1)", $weekStart, $weekEnd);
    
        // Monthly
        $monthStart = date('Y-m-01 00:00:00', strtotime($date));
        $monthEnd = date('Y-m-t 23:59:59', strtotime($date));
        $this->aggregateByPeriod('monthly', "DATE_FORMAT(date_checked, '%Y-%m')", $monthStart, $monthEnd);
    
        // Yearly
        $yearStart = date('Y-01-01 00:00:00', strtotime($date));
        $yearEnd = date('Y-12-31 23:59:59', strtotime($date));
        $this->aggregateByPeriod('yearly', "YEAR(date_checked)", $yearStart, $yearEnd);
}

    /**
     * General aggregation logic
     */
    private function aggregateByPeriod($periodType, $groupExpr, $start, $end)
    {
        $query = "
            SELECT 
                shop_id,
                {$groupExpr} AS period_value,
                COUNT(*) AS total_products,
                COUNT(CASE WHEN is_available = 0 THEN 1 END) AS unavailable_products
            FROM oos_history
            WHERE date_checked BETWEEN ? AND ?
            GROUP BY shop_id, period_value
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$start, $end]);
        $results = $stmt->fetchAll();

        foreach ($results as $row) {
            $shopId = $row['shop_id'];
            $periodValue = $row['period_value'];
            $total = $row['total_products'];
            $unavailable = $row['unavailable_products'];
            $oosPercentage = $total > 0 ? round(($unavailable * 100.0) / $total, 2) : 0.0;

            $this->upsertAggregate(
                $shopId,
                $periodType,
                $periodValue,
                $oosPercentage,
                $total,
                $unavailable
            );
        }
    }

    /**
     * Inserts or updates an entry in the oos_aggregates table
     */
    private function upsertAggregate($shopId, $periodType, $periodValue, $oosPercentage, $total, $unavailable)
    {
        $sql = "
            INSERT INTO oos_aggregates 
                (shop_id, period_type, period_value, oos_percentage, total_products, unavailable_products)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                oos_percentage = VALUES(oos_percentage),
                total_products = VALUES(total_products),
                unavailable_products = VALUES(unavailable_products),
                updated_at = CURRENT_TIMESTAMP
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $shopId,
            $periodType,
            $periodValue,
            $oosPercentage,
            $total,
            $unavailable
        ]);
    }

    /**
     * Backfills aggregates for a date range
     */
    public function backfill($startDate, $endDate)
    {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);

        for ($d = clone $start; $d <= $end; $d->modify('+1 day')) {
            echo "Aggregating for: " . $d->format('Y-m-d') . "\n";
            $this->aggregateOOSPeriods($d->format('Y-m-d'));
        }

        echo "Backfill complete.\n";
    }
}