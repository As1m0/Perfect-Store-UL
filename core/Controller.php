<?php

abstract class Controller
{
    public static function Route() : void
    {
        global $cfg;
        // Logout
        if (isset($_GET['logout']) && $_GET['logout']) {
            session_unset();
            session_destroy();
            header("Location: index.php?p=login");
            exit();
        }

        $requestUri = $_SERVER['REQUEST_URI'];
        if (strpos($requestUri, '/api/') !== false || (isset($_GET['api']) && $_GET['api'] === '1')) {
            self::HandleApiRequest();
            return; // Exit early, don't process as regular page
        }


        $page = $cfg["mainPage"];
        if(isset($_GET[$cfg["pageKey"]]))
        {
            $page = htmlspecialchars($_GET[$cfg["pageKey"]]);
        }
        try
        {
           // Check if the page is 'login' and if the user is not logged in
            if(!isset($_SESSION["loggedIn"]) && $page !== "login")
            {
            header("Location: index.php?p=login");
            exit();
            }
            DBHandler::Init();
            View::setBaseTemplate(Template::Load($cfg["mainPageTemplate"]));
            $pageData = Model::GetPageData($page);
            if(class_exists($pageData["class"]) && in_array("IPageBase", class_implements($pageData["class"])))
            {
                $pageObject = new $pageData["class"]();
                $pageObject->Run($pageData);
                $result = $pageObject->GetTemplate();
                if($result !== null)
                {
                    if($pageData["fullTemplate"])
                    {
                        View::setBaseTemplate($result);
                    }
                    else
                    {
                        if(isset($_SESSION["loggedIn"]))
                        {
                            //Add the navigation module only if the user is logged in
                            View::getBaseTemplate()->AddData($cfg["defaultNavFlag"], Controller::RunModule("NavModule"));
                        }
                        View::getBaseTemplate()->AddData($cfg["defaultContentFlag"], $result);
                       //View::getBaseTemplate()->AddData($cfg["defaultFooterFlag"], Template::Load("footer.html"));
                    }
                }
                else
                {
                    throw new PageLoadException("A megadott oldal nem generált tartalmat!");
                }
            }
            else
            {
                throw new PageLoadException("A megadott oldalhoz tartozó osztály ({$pageData["class"]}) nem létezik, vagy nem megfelelő");
            }
        }
        catch (NotFoundException $ex)
        {
            View::setBaseTemplate(Template::Load($cfg["PageNotFoundTemplate"]));
        }
        catch (Exception $ex)
        {
            if($cfg["debug"] /*&& (is_a($ex, "TemplateException") || is_a($ex, "PageLoadException"))*/)
            {
                View::setBaseTemplate(Template::Load($cfg["debugErrorPage"]));
                View::getBaseTemplate()->AddData("EXCEPTION", get_class($ex));
                View::getBaseTemplate()->AddData("MESSAGE", $ex->getMessage());
                View::getBaseTemplate()->AddData("TRACE", $ex->getTraceAsString());
            }
            elseif(!$cfg["debug"])
            {
                View::setBaseTemplate(Template::Load($cfg["maintanceTemplate"]));
            }
        }
        finally
        {
            try
            {
               DBHandler::Disconnect();
            }
            catch (Exception $ex)
            {
                //do nothing...
            }
            View::PrintFinalTemplate();
        }
    }
    
    public static function RunModule(string $moduleName, array $data = []) : null|Template
    {
        $modules = Model::GetModules();
        if(isset($modules[$moduleName]) && class_exists($moduleName))
        {
            if($modules[$moduleName]["enabled"] === true)
            {
                if(in_array("IVisibleModuleBase", class_implements($moduleName)))
                {
                    $module = new $moduleName();
                    $module->Run($data);
                    return $module->GetTemplate();
                }
                elseif(in_array("IModuleBase", class_implements($moduleName)))
                {
                    $module = new $moduleName();
                    $module->Run($data);
                    return null;
                }
                else
                {
                    throw new ModuleException("A megadott modul szerkezetileg hibás!");
                }
            }
            else
            {
                throw new ModuleException("A megadott modul nem engedélyezett!");
            }
        }
        else
        {
            throw new NotFoundException("A megadott modul nem található!");
        }
    }

    private static function HandleApiRequest() : void
    {
        try {
            //DBHandler::Init();
            
            // Set API headers
            header('Content-Type: application/json');
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
            
            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                http_response_code(200);
                exit();
            }
            
            $endpoint = $_GET['endpoint'] ?? '';
            $requestMethod = $_SERVER['REQUEST_METHOD'];
            $oosService = new OOSService();
            
            // Handle API routing
            switch ($requestMethod) {
                case 'GET':
                    switch ($endpoint) {
                        case 'categories':
                            $data = $oosService->getCategories();
                            echo ApiResponse::success($data, 'Categories retrieved successfully');
                            break;
                        case 'brands':
                            $data = $oosService->getBrands();
                            echo ApiResponse::success($data, 'Brands retrieved successfully');
                            break;
                        case 'product':
                            $ean = $_GET['ean'] ?? '';
                            if ($ean) {
                                $data = $oosService->getProductByEAN($ean);
                                echo ApiResponse::success($data, 'Product retrieved successfully');
                            } else {
                                echo ApiResponse::error('EAN parameter required', 400);
                            }
                            break;
                        default:
                            echo ApiResponse::error('Endpoint not found', 404);
                    }
                    break;
                    
                case 'POST':
                    switch ($endpoint) {
                        case 'oos-data':
                            $input = json_decode(file_get_contents('php://input'), true);
                            if (json_last_error() !== JSON_ERROR_NONE) {
                                echo ApiResponse::error('Invalid JSON in request body', 400);
                                break;
                            }
                            $options = [
                                'shopId' => $input['shopId'] ?? 3,
                                'filters' => $input['filters'] ?? [],
                                'sort' => $input['sort'] ?? ['column' => 'category', 'direction' => 'ASC']
                            ];
                            $data = $oosService->getOOSData($options);
                            echo ApiResponse::success($data, 'OOS data retrieved successfully');
                            break;
                        default:
                            echo ApiResponse::error('Endpoint not found', 404);
                    }
                    break;
                    
                default:
                    echo ApiResponse::error('Method not allowed', 405);
            }
            
        } catch (Exception $e) {
            error_log("API Error: " . $e->getMessage());
            echo ApiResponse::error('Internal server error', 500);
        } finally {
            try {
                DBHandler::Disconnect();
            } catch (Exception $ex) {
                // do nothing...
            }
        }
        exit(); // Important: stop execution after API response
    }
}
