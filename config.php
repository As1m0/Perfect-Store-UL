<?php
$cfg = [];

$cfg["debug"] = true;

//templates
$cfg["debugErrorPage"] = "DebugError.html";
$cfg["mainPageTemplate"] = "index.html";
$cfg["maintanceTemplate"] = "maintance.html";
$cfg["PageNotFoundTemplate"] = "404.html";

$cfg["contentFolder"] = "content";
$cfg["uploadsFolder"] = $cfg["contentFolder"]."/uploads";
$cfg["templateFolder"] = "template";
$cfg["defaultContentType"] = "text/html";
$cfg["templateFlag"] = "/%!([A-Z]+)!%/";
$cfg["pageKey"] = "p";
$cfg["mainPage"] = "index";

//flags
$cfg["defaultContentFlag"] = "CONTENT";
$cfg["defaultNavFlag"] = "NAV";
$cfg["defaultFooterFlag"] = "FOOTER";

//Database
$cfg["db"]["hostname"] = "localhost";
$cfg["db"]["username"] = "website";
$cfg["db"]["pass"] = "gehNuf-nakjiz-wamna3";
$cfg["db"]["db"] = "OOS";
$cfg["db"]["port"] = "3306";

//API
$cfg["api"]["baseUrl"] = "./api/index.php";