<?php
ini_set('default_charset', 'UTF-8');

require_once("config.php");

if($cfg["debug"])
{
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

require_once("autoload.php");
session_start();
Controller::Route();