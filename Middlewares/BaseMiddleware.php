<?php

declare(strict_types=1);

namespace Inspira\Http\Middlewares;

use Inspira\Http\Router\Route;
use Inspira\Http\Router\Router;
use Inspira\Http\Status;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class BaseMiddleware extends Middleware
{
	public bool $global = true;

	public function __construct(protected Router $router, protected ResponseInterface $response) { }

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

	private function handleOptionsRequest(ServerRequestInterface $request): ?ResponseInterface
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

	private function handleHeadRequest(ServerRequestInterface $request): ?ResponseInterface
	{
		if ($request->isHead()) {
			return $this->response
				->withStatus(Status::NO_CONTENT->value)
				->withoutContent();
		}

		return null;
	}
}
