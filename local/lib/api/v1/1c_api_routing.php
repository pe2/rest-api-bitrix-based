<?php
require __DIR__ . '/api_bootstrap.php';

use Bitrix\Main\HttpRequest;
use Bitrix\Main\Routing\RoutingConfigurator;
use Local\API\RequestHandler;


/** API for 1C services */
const _1C_API_PREFIX_V1 = 'api/1c/v1';


return function (RoutingConfigurator $routes) {
    $routes->prefix(_1C_API_PREFIX_V1)->group(function (RoutingConfigurator $routes) {

        $routes->get('images/link/{image_xml_id}/', function (HttpRequest $request) {
            $apiRequest = new RequestHandler('Images\GetLink', $request);
            $apiRequest->handleRequest();
        });

        $routes->post('images/links/', function (HttpRequest $request) {
            $apiRequest = new RequestHandler('Images\GetLinks', $request, file_get_contents('php://input'));
            $apiRequest->handleRequest();
        });

        $routes->prefix('orders')->group(function (RoutingConfigurator $routes) {
            $routes->get('in-final-status/', function (HttpRequest $request) {
                $apiRequest = new RequestHandler('Orders\InFinalStatus', $request);
                $apiRequest->handleRequest();
            });
            $routes->post('set-properties/', function (HttpRequest $request) {
                $apiRequest = new RequestHandler('Orders\SetOrderProperties', $request, file_get_contents('php://input'));
                $apiRequest->handleRequest();
            });
        });

        $routes->prefix('products')->group(function (RoutingConfigurator $routes) {
            $routes->post('ids/', function (HttpRequest $request) {
                $apiRequest = new RequestHandler('Products\GetIds', $request, file_get_contents('php://input'));
                $apiRequest->handleRequest();
            });
        });

        $routes->prefix('sections')->group(function (RoutingConfigurator $routes) {
            $routes->get('list/', function (HttpRequest $request) {
                $apiRequest = new RequestHandler('Sections\GetList', $request);
                $apiRequest->handleRequest();
            });
            $routes->get('tree/', function (HttpRequest $request) {
                $apiRequest = new RequestHandler('Sections\GetTree', $request);
                $apiRequest->handleRequest();
            });
        });
    });
};