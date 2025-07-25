<?php

abstract class DBHandler
{
    private static mysqli $con;
    
    public static function Init() : void
    {
        global $cfg;
        $driver = new mysqli_driver();
        $driver->report_mode = MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT;
        try
        {
            self::$con = new mysqli($cfg["db"]["hostname"], $cfg["db"]["username"], $cfg["db"]["pass"], $cfg["db"]["db"], $cfg["db"]["port"]);
        }
        catch (Exception $ex)
        {
            throw new DBException("Az adatbázis csatlakozás sikertelen!", $ex);
        }
    }
    
    public static function Disconnect() : void
    {
        try
        {
            self::$con->close();
        }
        catch (Exception $ex)
        {
            throw new DBException("Az adatbáziskapcsolat bontása sikertelen!", $ex);
        }
    }
    
    public static function RunCommand(string $sql) : void
    {
        try
        {
            self::$con->query($sql);
        }
        catch (Exception $ex)
        {
            throw new DBException("Az SQL parancs futtatása sikertelen!", $ex);
        }
    }
    
    public static function RunQuery(string $sql, array $params = [], bool $getId = false) : mixed
    {
        try
        {
            $stmt = self::$con->prepare($sql);
            if(count($params) > 0)
            {
                $types = "";
                $values = [];
                foreach($params as $p)
                {
                    $types .= $p->getType()->value;
                    $values[] = $p->getParam();
                }
                //$stmt->bind_params("típusok", $p1, $p2...)
                $paramArray = array_merge([$types], $values);
                //TODO: Referencia szerinti átadásra átalakítani!
                @call_user_func_array([$stmt, 'bind_param'], $paramArray);
            }
            $stmt->execute();

            if ($getId !== false)
            {
            return self::$con->insert_id;
            }

            return $stmt->get_result();
        }
        catch (Exception $ex)
        {
            throw new DBException("Az SQL parancs futtatása sikertelen!");
        }
    }
}
