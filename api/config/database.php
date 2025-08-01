<?php
class Database {
    private $conn = "";

    public function getConnection($cfg) {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $cfg["db"]["hostname"] . ";dbname=" . $cfg["db"]["db"] . ";charset=utf8mb4",
                $cfg["db"]["username"],
                $cfg["db"]["pass"],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            throw new Exception("Database connection failed");
        }
        return $this->conn;
    }
}