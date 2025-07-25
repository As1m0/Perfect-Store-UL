<?php

abstract class Logger
{
    public static function WriteLog(string $message, LogLevel $level) : void
    {
        global $cfg;
        $filename = "log_".date("Y-m-d").".log";
        $log = fopen($cfg["contentFolder"]."/".$filename, "a");
        fputs($log, "[".date("H:i:s")."][".$level->name."] - $message\n");
        fclose($log);
    }
}
