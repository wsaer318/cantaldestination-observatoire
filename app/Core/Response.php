<?php

declare(strict_types=1);

namespace App\Core;


class Response
{
    private int $status;
    private array $headers;
    private string $body;

    public function __construct(string $body = '', int $status = 200, array $headers = [])
    {
        $this->status = $status;
        $this->headers = $headers;
        $this->body = $body;
    }

    public static function json(array $data, int $status = 200, array $headers = []): self
    {
        $options = defined('JSON_OPTIONS') ? JSON_OPTIONS : JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $headers['Content-Type'] = $headers['Content-Type'] ?? 'application/json; charset=utf-8';

        return new self(json_encode($data, $options), $status, $headers);
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function withHeaders(array $headers): self
    {
        $clone = clone $this;
        foreach ($headers as $name => $value) {
            $clone->headers[$name] = $value;
        }
        return $clone;
    }

    public function withStatus(int $status): self
    {
        $clone = clone $this;
        $clone->status = $status;
        return $clone;
    }

    public function withoutBody(): self
    {
        $clone = clone $this;
        $clone->body = '';
        return $clone;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }

        echo $this->body;
    }
}
