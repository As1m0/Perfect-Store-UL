<?php

// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'classes/QueryGenerator.php';
require_once 'classes/ApiResponse.php';
require_once 'classes/OOSService.php';
require_once 'config/database.php';

// Set headers
ApiResponse::sendHeaders();

try {
    $oosService = new OOSService();
    $requestUri = $_SERVER['REQUEST_URI'];
    $requestMethod = $_SERVER['REQUEST_METHOD'];
    
    // Remove query parameters and leading/trailing slashes
    $path = trim(parse_url($requestUri, PHP_URL_PATH), '/');
    $pathParts = explode('/', $path);
    
    // Route handling
    switch ($requestMethod) {
        case 'GET':
            switch (end($pathParts)) {
                case 'categories':
                    $data = $oosService->getCategories();
                    echo ApiResponse::success($data, 'Categories retrieved successfully');
                    break;
                    
                case 'brands':
                    $data = $oosService->getBrands();
                    echo ApiResponse::success($data, 'Brands retrieved successfully');
                    break;
                    
                case 'subcategories':
                    $data = $oosService->getSubcategories();
                    echo ApiResponse::success($data, 'Subcategories retrieved successfully');
                    break;
                    
                case 'shops':
                    $data = $oosService->getShops();
                    echo ApiResponse::success($data, 'Shops retrieved successfully');
                    break;
                    
                case 'summary':
                    $shopId = $_GET['shop_id'] ?? 3;
                    $data = $oosService->getSummaryStats((int)$shopId);
                    echo ApiResponse::success($data, 'Summary statistics retrieved successfully');
                    break;
                    
                default:
                    echo ApiResponse::error('Endpoint not found', 404);
                    break;
            }
            break;
            
        case 'POST':
            switch (end($pathParts)) {
                case 'oos-data':
                    $input = json_decode(file_get_contents('php://input'), true);
                    
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        echo ApiResponse::error('Invalid JSON in request body', 400);
                        break;
                    }
                    
                    // Validate required fields
                    $options = [
                        'shopId' => $input['shopId'] ?? 3,
                        'filters' => $input['filters'] ?? [],
                        'sort' => $input['sort'] ?? ['column' => 'category', 'direction' => 'ASC']
                    ];
                    
                    $data = $oosService->getOOSData($options);
                    echo ApiResponse::success($data, 'OOS data retrieved successfully');
                    break;

                case 'untrack-product':
                    $input = json_decode(file_get_contents('php://input'), true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        echo ApiResponse::error('Invalid JSON in request body', 400);
                        break;
                    }
                    $ean = $input['EAN'] ?? '';
                    $shopId = $input['shopId'] ?? '';
                    if ($ean && $shopId) {
                        $result = $oosService->untrackProduct($ean, $shopId);
                        if ($result) {
                            echo ApiResponse::success(null, 'Product deleted successfully');
                        } else {
                            echo ApiResponse::error('Failed to delete product', 500);
                        }
                    } else {
                        echo ApiResponse::error('EAN and ShopId parameter required', 400);
                    }
                    break;
                
                    case 'edit-product-url':
                    $input = json_decode(file_get_contents('php://input'), true);  
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        echo ApiResponse::error('Invalid JSON in request body', 400);
                        break;
                    }
                    $ean = $input['EAN'] ?? '';
                    $shopId = $input['shopId'] ?? '';
                    $productUrl = $input['product_url'] ?? '';
                    if ($ean && $shopId && $productUrl) {
                        $result = $oosService->editProductUrl($ean, $shopId, $productUrl);
                        if ($result) {
                            echo ApiResponse::success(null, 'Product URL updated successfully');
                        } else {
                            echo ApiResponse::error('Failed to update product URL', 500);
                        }
                    } else {
                        echo ApiResponse::error('EAN, ShopId, and ProductUrl parameters required', 400);
                    }
                    break;
                default:
                    echo ApiResponse::error('Endpoint not found', 404);
            }
            break;
            
        default:
            echo ApiResponse::error('Method not allowed', 405);
            break;
    }
    
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    echo ApiResponse::error('Internal server error', 500, $e->getMessage());
}
?>

