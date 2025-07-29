<?php

require_once("core/enums.php");
spl_autoload_register(function(string $type)
{
    //Itt bármire alapozhatunk, hogy mi alapján tudjuk megtalálni az adott típust, keresés, valamilyen fájlban tárolt érték, fájl keresés stb. - a PHP-t nem érdekli, hogy mi alapján jövünk rá, hogy amit ő keres az hol van, csak találjuk meg neki, ha meg lehet.
    if(strpos($type, "Exception") !== false && file_exists("core/exceptions/$type.php"))
    {
        require_once("core/exceptions/$type.php");
    }
    elseif(file_exists("core/$type.php"))
    {
        require_once("core/$type.php");
    }
    elseif(file_exists("core/modules/$type.php"))
    {
        require_once("core/modules/$type.php");
    }
    elseif(file_exists("core/pages/$type.php"))
    {
        require_once("core/pages/$type.php");
    }
    elseif(file_exists("api/classes/$type.php"))
    {
        require_once("api/classes/$type.php");
    }
    elseif(file_exists("api/config/$type.php"))
    {
        require_once("api/config/$type.php");
    }
});