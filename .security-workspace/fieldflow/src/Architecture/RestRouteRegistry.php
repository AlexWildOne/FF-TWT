<?php
namespace RoutesPro\Architecture;

if (!defined('ABSPATH')) exit;

final class RestRouteRegistry {
    private const CONTROLLERS = [
        '\\RoutesPro\\Rest\\LocationsController',
        '\\RoutesPro\\Rest\\StoreProfilesController',
        '\\RoutesPro\\Rest\\RoutesController',
        '\\RoutesPro\\Rest\\EventsController',
        '\\RoutesPro\\Rest\\OptimizeController',
        '\\RoutesPro\\Rest\\StatsController',
        '\\RoutesPro\\Rest\\IntegrationsController',
        '\\RoutesPro\\Rest\\CategoriesController',
        '\\RoutesPro\\Rest\\CommercialController',
        '\\RoutesPro\\Rest\\PlacesController',
        '\\RoutesPro\\Rest\\CoreIntegrityController',
        '\\RoutesPro\\Rest\\MappingController',
        '\\RoutesPro\\Rest\\SubscriptionsController',
        '\\RoutesPro\\Rest\\OperationsController',
        '\\RoutesPro\\Integrations\\PublicApiController',
        '\\RoutesPro\\Billing\\BillingController',
        '\\RoutesPro\\Expenses\\ExpensesController',
        '\\RoutesPro\\Briefings\\BriefingsController',
        '\\RoutesPro\\Implementations\\ImplementationsController',
        '\\RoutesPro\\Stocks\\StocksController',
        '\\RoutesPro\\Shopping\\ShoppingController',
        '\\RoutesPro\\Mobile\\Controller',
        '\\RoutesPro\\Mobile\\StabilityController',
        '\\RoutesPro\\Localization\\LanguageController',
    ];

    public static function register(): void {
        foreach (self::CONTROLLERS as $controllerClass) {
            $controller = new $controllerClass();
            $controller->register_routes();
        }
    }

    public static function status(): array {
        $missing = [];
        foreach (self::CONTROLLERS as $controllerClass) if (!class_exists($controllerClass)) $missing[] = $controllerClass;
        return ['registered_controllers'=>count(self::CONTROLLERS),'missing_controllers'=>$missing];
    }
}
