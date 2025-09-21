<?php
declare(strict_types=1);

namespace App\Core;

class Request
{
    private string $method;
    private string $path;
    private array $query;
    private array $headers;
    private ?string $rawBody;
    private bool $jsonDecoded = false;
    private ?array $jsonData = null;

    private function __construct(string $method, string $path, array $query, array $headers, ?string $rawBody)
    {
        $this->method = strtoupper($method);
        $this->path = $path === '' ? '/' : $path;
        $this->query = $query;
        $this->headers = $headers;
        $this->rawBody = $rawBody;
    }

    public static function fromGlobals(string $basePath = ''): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($requestUri, PHP_URL_PATH) ?? '/';

        if ($basePath !== '') {
            if (str_starts_with($path, $basePath)) {
                $path = substr($path, strlen($basePath));
            } else {
                $position = strpos($path, $basePath);
                if ($position !== false) {
                    $path = substr($path, $position + strlen($basePath));
                }
            }
        }

        $path = '/' . ltrim($path, '/');
        if ($path === '//' || $path === '') {
            $path = '/';
        }

        $query = $_GET ?? [];
        $headers = self::collectHeaders();
        $rawBody = file_get_contents('php://input');
        $rawBody = $rawBody === false ? null : $rawBody;

        return new self($method, $path, $query, $headers, $rawBody);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQueryParams(): array
    {
        return $this->query;
    }

    public function getQuery(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function getHeader(string $name, mixed $default = null): mixed
    {
        $key = strtolower($name);
        return $this->headers[$key] ?? $default;
    }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        $data = $this->decodeJsonBody();
        if ($key === null) {
            return $data;
        }
        return $data[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $json = $this->decodeJsonBody();
        if (array_key_exists($key, $json)) {
            return $json[$key];
        }

        return $_POST[$key] ?? $default;
    }

    public function getRawBody(): ?string
    {
        return $this->rawBody;
    }

    private static function collectHeaders(): array
    {
        $headers = [];

        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                $headers[strtolower($name)] = $value;
            }
        } else {
            foreach ($_SERVER as $key => $value) {
                if (str_starts_with($key, 'HTTP_')) {
                    $name = strtolower(str_replace('_', '-', substr($key, 5)));
                    $headers[$name] = $value;
                }
            }
        }

        if (!isset($headers['content-type']) && isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }

        if (!isset($headers['content-length']) && isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['content-length'] = $_SERVER['CONTENT_LENGTH'];
        }

        return $headers;
    }

    private function decodeJsonBody(): array
    {
        if ($this->jsonDecoded) {
            return $this->jsonData ?? [];
        }

        $this->jsonDecoded = true;
        $this->jsonData = [];

        if ($this->rawBody === null || $this->rawBody === '') {
            return $this->jsonData;
        }

        $decoded = json_decode($this->rawBody, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $this->jsonData = $decoded;
        }

        return $this->jsonData;
    }
}