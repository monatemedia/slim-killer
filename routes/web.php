<?php
use Slim\App;
use App\Infrastructure\Middleware\AuthMiddleware;

// Public Marketing Input Controllers (Http Adapters)
use App\Http\Application\HomeController;
use App\Http\Application\CalculatorController;
use App\Http\Application\PropertySecretsController;
use App\Http\Application\BuyersGuideController;
use App\Http\Application\ShowApplyController;
use App\Http\Application\ProcessApplyController;
use App\Http\Application\SuccessController;
use App\Http\Application\SubscribedController;

// Auth & Admin Input Controllers
use App\Http\Auth\ShowLoginController;
use App\Http\Auth\LoginController;
use App\Http\Auth\LogoutController;
use App\Http\Admin\DashboardController;

return function (App $app) {
    // --- Public Marketing Layouts ---
    $app->get('/', HomeController::class);
    
    // Support dual URI layouts seamlessly
    $app->get('/property-secrets', PropertySecretsController::class);
    $app->get('/propertysecrets', PropertySecretsController::class);
    
    $app->get('/buyers-guide', BuyersGuideController::class);
    $app->get('/buyersguide', BuyersGuideController::class);

    $app->get('/calculator', CalculatorController::class);
    
    $app->get('/apply', ShowApplyController::class);
    $app->post('/apply', ProcessApplyController::class);
    $app->get('/success', SuccessController::class);
    $app->get('/subscribed', SubscribedController::class);  
    
    // --- Authentication ---
    $app->get('/login', ShowLoginController::class);
    $app->post('/login', LoginController::class);
    $app->get('/logout', LogoutController::class);

    // --- Protected Administrative Routing ---
    $app->group('/admin', function ($group) {
        $group->get('', DashboardController::class);
    })->add(AuthMiddleware::class); // Instantiated dynamically out of your PHP-DI service map!
};