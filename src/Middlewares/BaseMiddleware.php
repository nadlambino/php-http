<?php

declare(strict_types=1);

namespace Inspira\Http\Middlewares;

use Inspira\Http\Router\Route;
use Inspira\Http\Router\Router;
use Inspira\Http\Status;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The BaseMiddleware class provides a foundational middleware for processing incoming server requests.
 *
 * This middleware handles OPTIONS and HEAD requests, providing appropriate responses.
 * It also extracts attributes from the current route and adds them to the request before passing
 * it to the next request handler in the middleware stack.
 */
class BaseMiddleware extends Middleware
{
	/**
	 * Indicates whether the middleware is global and should be applied to all routes.
	 *
	 * @var bool
	 */
	public bool $global = true;

	/**
	 * Create a new BaseMiddleware instance.
	 *
	 * @param Router $router The router instance used for route information.
	 * @param ResponseInterface $response The response instance used for generating responses.
	 */
	public function __construct(protected Router $router, protected ResponseInterface $response)
	{

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
		$response = $this->handleOptionsRequest($request);
		if ($response) return $response;

		$response = $this->handleHeadRequest($request);
		if ($response) return $response;

		$route = $this->router->getCurrentRoute();
		$attributes = $route instanceof Route ? $route->getAttributes() : [];

		return $handler->handle($request->withAttributes($attributes ?? []));
	}

	/**
	 * Handle OPTIONS requests.
	 *
	 * If the incoming request is an OPTIONS request, this method generates an appropriate response
	 * including the allowed HTTP methods for the requested resource.
	 *
	 * @param ServerRequestInterface $request
	 * @return ResponseInterface|null
	 */
	protected function handleOptionsRequest(ServerRequestInterface $request): ?ResponseInterface
	{
		if ($request->isOptions()) {
			$allowed = $this->router->getAllowedMethods($request->getUri()->getPath());

			return $this->response
				->withAddedHeader('Allow', implode(', ', $allowed))
				->withStatus(Status::NO_CONTENT->value)
				->withoutContent();
		}

		return null;
	}

	/**
	 * Handle HEAD requests.
	 *
	 * If the incoming request is a HEAD request, this method generates an appropriate response
	 * with an empty body.
	 *
	 * @param ServerRequestInterface $request
	 * @return ResponseInterface|null
	 */
	protected function handleHeadRequest(ServerRequestInterface $request): ?ResponseInterface
	{
		if ($request->isHead()) {
			return $this->response
				->withStatus(Status::NO_CONTENT->value)
				->withoutContent();
		}

		return null;
	}
}
