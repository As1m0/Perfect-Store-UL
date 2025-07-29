<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

require_once("../config.php");
require_once("../core/DBParam.php");
require_once("../core/DBHandler.php");
require_once("../core/Model.php");
require_once("../core/enums.php");
require_once("../core/exceptions/DBException.php");


$data = json_decode(file_get_contents('php://input'), true);

if ($data['token'] !== 'ajQinfF23nnAlaq') {
    http_response_code(403);
    exit('Forbidden');
}

$ean = $data['ean'];
$shopId = $data['shop_id'];
$isAvailable = $data['is_available'];
if (empty($ean) || empty($shopId) || !isset($isAvailable)) {
    http_response_code(400);
    exit('Bad Request: Missing parameters');
}

try {
    DBHandler::Init();
    Model::uploadScrapeResult($ean, $shopId, $isAvailable);
    DBHandler::Disconnect();
} catch (DBException $error) {
    $response['success'] = false;
    $response['error'] = $error->getMessage();
    echo json_encode($response);
}
$response['success'] = true;
echo json_encode($response);
