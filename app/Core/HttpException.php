<?php

declare(strict_types=1);

namespace App\Core;


use RuntimeException;

class HttpException extends RuntimeException
{
    private int $status;
    private array $payload;

    public function __construct(string $message, int $status = 500, array $payload = [])
    {
        parent::__construct($message);
        $this->status = $status;
        $this->payload = $payload;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public static function badRequest(string $message = 'Requête invalide', array $payload = []): self
    {
        return new self($message, 400, $payload);
    }

    public static function unauthorized(string $message = 'Authentification requise', array $payload = []): self
    {
        return new self($message, 401, $payload);
    }

    public static function forbidden(string $message = 'Accès interdit', array $payload = []): self
    {
        return new self($message, 403, $payload);
    }

    public static function notFound(string $message = 'Ressource introuvable', array $payload = []): self
    {
        return new self($message, 404, $payload);
    }
}
