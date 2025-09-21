<?php

declare(strict_types=1);

use App\Core\Application;
use App\Core\Router;
use App\Modules\SharedSpaces\SharedSpacesModule;
use App\Modules\Infographie\InfographieModule;

return (function (): Application {
    $router = new Router();

    (new SharedSpacesModule())->register($router);
    (new InfographieModule())->register($router);

    return new Application($router);
})();
