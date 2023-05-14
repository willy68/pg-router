<?php

declare(strict_types=1);

namespace Pg\Router\Middlewares;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class CallableMiddleware
 * @package Pg\Router\Middlewares
 */
class CallableMiddleware implements MiddlewareInterface
{
  /**
   * @var string|callable
   */
    private $callable;

    /**
     * @param callable|string $callable
     */
    public function __construct(callable|string $callable)
    {
        $this->callable = $callable;
    }

    /**
     * @return string|callable
     */
    public function getCallable(): callable|string
    {
        return $this->callable;
    }

    /**
     * Process an incoming server request.
     *
     * Processes an incoming server request in order to produce a response.
     * If unable to produce the response itself, it may delegate to the provided
     * request handler to do so.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request);
    }
}
