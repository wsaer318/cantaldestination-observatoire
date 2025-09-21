<?php

declare(strict_types=1);

namespace App\Core;


class Application
{
    private Router $router;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    public function handle(Request $request): Response
    {
        try {
            $response = $this->router->dispatch($request);
        } catch (HttpException $exception) {
            $payload = $exception->getPayload();
            if (empty($payload)) {
                $payload = [
                    'success' => false,
                    'error' => $exception->getMessage(),
                ];
            }

            $response = Response::json($payload, $exception->getStatus());
        } catch (\Throwable $exception) {
            error_log('[API][Error] ' . $exception->getMessage());
            $response = Response::json([
                'success' => false,
                'error' => 'Erreur interne du serveur',
            ], 500);
        }

        return $this->applyDefaultHeaders($response);
    }

    private function applyDefaultHeaders(Response $response): Response
    {
        return $response->withHeaders([
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, PATCH, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, X-CSRF-Token, X-Api-Token',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
        ]);
    }
}

