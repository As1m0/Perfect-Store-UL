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
}
