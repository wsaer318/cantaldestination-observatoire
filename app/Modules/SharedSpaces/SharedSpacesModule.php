<?php

declare(strict_types=1);

namespace App\Modules\SharedSpaces;

use App\Core\Router;

class SharedSpacesModule
{
    public function register(Router $router): void
    {
        $controller = new SharedSpacesController();

        $router->get('/shared-spaces', [$controller, 'index']);
        $router->post('/shared-spaces', [$controller, 'store']);
        $router->get('/shared-spaces/{id}', [$controller, 'show']);
        $router->put('/shared-spaces/{id}', [$controller, 'update']);
        $router->delete('/shared-spaces/{id}', [$controller, 'destroy']);

        $router->get('/shared-spaces/{id}/members', [$controller, 'members']);
        $router->post('/shared-spaces/{id}/members', [$controller, 'addMember']);
        $router->put('/shared-spaces/{id}/members/{userId}', [$controller, 'updateMember']);
        $router->delete('/shared-spaces/{id}/members/{userId}', [$controller, 'removeMember']);

        $router->get('/shared-spaces/members/{id}', [$controller, 'membersLegacy']);
        $router->post('/shared-spaces/members/{id}', [$controller, 'addMemberLegacy']);
        $router->put('/shared-spaces/members/{id}/{userId}', [$controller, 'updateMemberLegacy']);
        $router->delete('/shared-spaces/members/{id}/{userId}', [$controller, 'removeMemberLegacy']);

        $router->get('/shared-spaces/{id}/infographics', [$controller, 'listInfographics']);
        $router->post('/shared-spaces/{id}/infographics', [$controller, 'shareInfographic']);

        $router->get('/shared-spaces/infographics/{id}', [$controller, 'listInfographicsLegacy']);
        $router->post('/shared-spaces/infographics/{id}', [$controller, 'shareInfographicLegacy']);
    }
}
