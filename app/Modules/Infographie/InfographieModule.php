<?php
declare(strict_types=1);

namespace App\Modules\Infographie;

use App\Core\Router;

class InfographieModule
{
    public function register(Router $router): void
    {
        $controller = new InfographieController();

        $router->get('/infographie/departements-touristes', [$controller, 'departementsTouristes']);
        $router->get('/infographie/regions-touristes', [$controller, 'regionsTouristes']);
        $router->get('/infographie/pays-touristes', [$controller, 'paysTouristes']);
        $router->get('/infographie/departements-excursionnistes', [$controller, 'departementsExcursionnistes']);
        $router->get('/infographie/regions-excursionnistes', [$controller, 'regionsExcursionnistes']);
        $router->get('/infographie/pays-excursionnistes', [$controller, 'paysExcursionnistes']);
    }
}

