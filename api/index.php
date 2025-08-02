<?php


require_once 'classes/QueryGenerator.php';
require_once 'classes/ApiResponse.php';
require_once 'classes/OOSService.php';
require_once 'config/database.php';
require_once '../config.php';

// Set headers
ApiResponse::sendHeaders();

try {
    global $cfg;
    $oosService = new OOSService($cfg);
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
                    
                case 'sub-categories':
                    $data = $oosService->getSubcategories();
                    echo ApiResponse::success($data, 'Subcategories retrieved successfully');
                    break;
                    
                case 'shops':
                    $data = $oosService->getShops();
                    echo ApiResponse::success($data, 'Shops retrieved successfully');
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

                    case 'check-ean':
                    $input = json_decode(file_get_contents('php://input'), true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        echo ApiResponse::error('Invalid JSON in request body', 400);
                        break;
                    }
                    $ean = $input['EAN'] ?? '';
                    if ($ean) {
                        $data = $oosService->getProductByEANwithIds($ean);
                        if ($data) {        
                            echo ApiResponse::success($data, 'Product retrieved successfully');
                        } else {
                            echo ApiResponse::success([],'Product not found');
                        }
                    } else {
                        echo ApiResponse::error('EAN parameter required', 400);
                    }
                    break;

                    case 'add-product':
                    $input = json_decode(file_get_contents('php://input'), true);
                    if (json_last_error() !== JSON_ERROR_NONE) {    
                        echo ApiResponse::error('Invalid JSON in request body', 400);
                        break;
                    }
                    $ean = $input['EAN'] ?? '';
                    $name = $input['name'] ?? '';
                    $brandId = $input['brand_id'] ?? '';
                    $categoryId = $input['category_id'] ?? '';
                    $subcategoryId = $input['subcategory_id'] ?? '';
                    if ($ean && $name && $brandId && $categoryId && $subcategoryId) {
                        $result = $oosService->addProduct($ean, $name, $brandId, $categoryId, $subcategoryId);
                        if ($result) {
                            echo ApiResponse::success(null, 'Product added successfully');
                        } else {
                            echo ApiResponse::error('Failed to add product', 500);
                        }
                    } else {
                        echo ApiResponse::error('All parameters are required', 400);
                    }
                    break;
                    
                    case 'add-product-link':
                    $input = json_decode(file_get_contents('php://input'), true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        echo ApiResponse::error('Invalid JSON in request body', 400);
                        break;
                    }
                    $ean = $input['EAN'] ?? '';
                    $shopId = $input['shopId'] ?? '';
                    $productUrl = $input['product_url'] ?? '';
                    if ($ean && $shopId && $productUrl) {
                        $result = $oosService->addProductLink($ean, $shopId, $productUrl);
                        if ($result) {
                            echo ApiResponse::success(null, 'Product link added successfully');
                        } else {
                            echo ApiResponse::error('Failed to add product link', 500);
                        }
                    } else {
                        echo ApiResponse::error('EAN, ShopId, and ProductUrl parameters required', 400);
                    }
                    break;

                    case 'chart-data':
                        $input = json_decode(file_get_contents('php://input'), true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            echo ApiResponse::error('Invalid JSON in request body', 400);
                            break;
                        }
                        $startDate = $input['start_date'] ?? null;
                        $endDate = $input['end_date'] ?? null;
                        $data = $oosService->getOOSChartData($startDate, $endDate);
                        echo ApiResponse::success($data, 'Chart data retrieved successfully');
                        break;

                        case 'product-history':
                        $input = json_decode(file_get_contents('php://input'), true);
                        if (json_last_error() !== JSON_ERROR_NONE){
                            echo ApiResponse::error('Invalid JSON in request body', 400);
                        break;
                         }
                         $startDate = $input['start_date'] ?? null;
                         $endDate = $input['end_date'] ?? null;
                         $shop_id = $input['shop_id'] ?? null;
                         $ean = $input['ean'] ?? null;
                         if ($ean && $shop_id) {
                            $result = $oosService->getProductHistory($ean, $shop_id, $startDate, $endDate);
                            if ($result) {
                                echo ApiResponse::success($result, 'Product history requested successfuly');
                            } else {
                                echo ApiResponse::error('Failed to load product history', 500);
                            }
                        } else {
                            echo ApiResponse::error('EAN, ShopId  parameters required', 400);
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

