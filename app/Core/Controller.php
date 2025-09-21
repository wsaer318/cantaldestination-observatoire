<?php

declare(strict_types=1);

namespace App\Core;


abstract class Controller
{
    protected function json(array $data, int $status = 200, array $headers = []): Response
    {
        return Response::json($data, $status, $headers);
    }
}
