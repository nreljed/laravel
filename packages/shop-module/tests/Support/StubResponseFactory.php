<?php

namespace Nreljed\ShopModule\Tests\Support;

use Closure;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;
use Illuminate\Http\StreamedEvent;

/**
 * The `response()` helper for schema-harness tests.
 *
 * The harness runs a deliberately minimal container with no view stack, route
 * table or URL generator, so the real routing factory cannot be constructed
 * without dragging all of that in. The methods the tests use - `make()` and
 * friends for plain, JSON and empty responses - are implemented for real; the
 * rest throw rather than silently returning something, so a test that starts
 * depending on a redirect or a rendered view is told immediately rather than
 * quietly asserting against a stub.
 */
final class StubResponseFactory implements ResponseFactory
{
    /**
     * @param  Closure(string, int, array): Response  $respond
     */
    public function __construct(
        private readonly Closure $respond,
    ) {}

    public function make($content = '', $status = 200, array $headers = [])
    {
        $content = $content instanceof Response ? $content : ($this->respond)($content, $status, $headers);

        return new Response($content, $status, $headers);
    }

    public function noContent($status = 204, array $headers = [])
    {
        return new Response('', $status, $headers);
    }

    public function json($data = [], $status = 200, array $headers = [], $options = 0)
    {
        return new Response(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            $status,
            array_merge(['Content-Type' => 'application/json'], $headers),
        );
    }

    public function view($view, $data = [], $status = 200, array $headers = [])
    {
        throw new \LogicException('View rendering is not available in the schema harness.');
    }

    public function jsonp($callback, $data = [], $status = 200, array $headers = [], $options = 0)
    {
        throw new \LogicException('jsonp() is not available in the schema harness.');
    }

    public function eventStream(Closure $callback, array $headers = [], StreamedEvent|string|null $endStreamWith = '</stream>')
    {
        throw new \LogicException('eventStream() is not available in the schema harness.');
    }

    public function stream($callback, $status = 200, array $headers = [])
    {
        throw new \LogicException('stream() is not available in the schema harness.');
    }

    public function streamJson($data, $status = 200, $headers = [], $encodingOptions = 15)
    {
        throw new \LogicException('streamJson() is not available in the schema harness.');
    }

    public function streamDownload($callback, $name = null, array $headers = [], $disposition = 'attachment')
    {
        throw new \LogicException('streamDownload() is not available in the schema harness.');
    }

    public function download($file, $name = null, array $headers = [], $disposition = 'attachment')
    {
        throw new \LogicException('download() is not available in the schema harness.');
    }

    public function file($file, array $headers = [])
    {
        throw new \LogicException('file() is not available in the schema harness.');
    }

    public function redirectTo($path, $status = 302, $headers = [], $secure = null)
    {
        throw new \LogicException('Redirects are not available in the schema harness.');
    }

    public function redirectToRoute($route, $parameters = [], $status = 302, $headers = [])
    {
        throw new \LogicException('Redirects are not available in the schema harness.');
    }

    public function redirectToAction($action, $parameters = [], $status = 302, $headers = [])
    {
        throw new \LogicException('Redirects are not available in the schema harness.');
    }

    public function redirectGuest($path, $status = 302, $headers = [], $secure = null)
    {
        throw new \LogicException('Redirects are not available in the schema harness.');
    }

    public function redirectToIntended($default = '/', $status = 302, $headers = [], $secure = null)
    {
        throw new \LogicException('Redirects are not available in the schema harness.');
    }
}
