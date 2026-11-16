<?php

declare(strict_types=1);

namespace Atrium\Admin\Mcp;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Facade;
use Laravel\Mcp\Response;

/**
 * Runs an MCP tool call through the admin API itself, with the same token.
 *
 * Nothing about records is decided here: validation, permissions of the token
 * and of its owner, the current site, translations — all of it is the API's,
 * so an assistant can do exactly what a program with that token can do.
 */
final class ApiGateway
{
    public function __construct(private readonly Router $router) {}

    /**
     * @param  array<string,mixed>  $query
     * @param  array<string,mixed>|null  $body
     */
    public function call(string $method, string $path, array $query = [], ?array $body = null): Response
    {
        $current = request();
        $uri = '/'.trim((string) config('atrium.api.prefix', 'api/v1'), '/').'/'.ltrim($path, '/');

        $sub = Request::create(
            $current->getSchemeAndHttpHost().$uri,
            $method,
            $query,
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: $body === null ? null : (string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
        $sub->headers->set('Authorization', (string) $current->headers->get('Authorization'));

        // The router does not swap the container's request by itself, and the
        // API's controllers read the body from there.
        app()->instance('request', $sub);
        Facade::clearResolvedInstance('request');

        try {
            $response = $this->router->dispatch($sub);
        } finally {
            // The MCP response still belongs to the original request.
            app()->instance('request', $current);
            Facade::clearResolvedInstance('request');
            url()->setRequest($current);
        }

        $status = $response->getStatusCode();
        $content = (string) $response->getContent();
        $data = $content === '' ? null : json_decode($content, true);

        if ($status >= 400) {
            return Response::error(self::message($status, is_array($data) ? $data : []));
        }

        return $status === 204
            ? Response::text('Done.')
            : Response::json($data);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private static function message(int $status, array $data): string
    {
        $message = (string) ($data['message'] ?? 'The request failed.');

        foreach ((array) ($data['errors'] ?? []) as $field => $errors) {
            $message .= "\n- {$field}: ".implode(' ', (array) $errors);
        }

        return "HTTP {$status}. {$message}";
    }
}
