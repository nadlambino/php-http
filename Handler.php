<?php

declare(strict_types=1);

namespace Inspira\Http;

use Closure;
use Exception;
use Inspira\Container\Container;
use Inspira\Contracts\Renderable;
use Inspira\Contracts\RenderableException;
use Inspira\Http\Router\Route;
use Inspira\Http\Router\Router;
use JetBrains\PhpStorm\NoReturn;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Handler implements RequestHandlerInterface
{
	public function __construct(protected Container $container, protected Router $router) { }

	/**
	 * @param ServerRequestInterface $request
	 * @return ResponseInterface
	 * @throws
	 */
	public function handle(ServerRequestInterface $request): ResponseInterface
	{
		/** @var ResponseInterface $response */
		$response = $this->container->make(ResponseInterface::class);
		if ($request->isHead() || $request->isOptions()) {
			$response = $response->withStatus(Status::NO_CONTENT->value)->withoutContent();
		}

		return $response;
	}

	/**
	 * @param ResponseInterface $response
	 * @throws
	 */
	#[NoReturn]
	public function send(ResponseInterface $response)
	{
		$route = $this->router->getCurrentRoute();
		$handler = $route instanceof Route ? $route->getHandler() : $route;

		$resolved = $this->resolveHandler($handler, $response);

		$response = $this->processResolved($resolved);
		$this->sendHttpResponse($response);
	}

	/**
	 * @param mixed $handler
	 * @param ResponseInterface $response
	 * @return mixed
	 * @throws
	 */
	private function resolveHandler(mixed $handler, ResponseInterface $response): mixed
	{
		return match (true) {
			$response->getStatusCode() >= Status::BAD_REQUEST->value => $response,
			$handler instanceof Closure                              => $this->container->resolve($handler),
			is_array($handler)                                       => $this->container->resolve($handler[0], $handler[1]),
			is_null($handler)                                        => $response->withStatus(Status::OK->value),
			$handler instanceof RenderableException                  => $response->withStatus($handler->getCode())->withContent($handler->render()),
			$handler instanceof Exception                            => $response->withStatus($handler->getCode()),
			default                                                  => $response->withStatus(Status::INTERNAL_SERVER_ERROR->value)
		};
	}

	/**
	 * @param mixed $resolved
	 * @return ResponseInterface
	 * @throws
	 */
	private function processResolved(mixed $resolved): ResponseInterface
	{
		/** @var ResponseInterface $response */
		$response = $this->container->make(Response::class);
		$responseHasContent = !empty($response->getContent());

		return match (true) {
			/** $response already has a content, so immediately return */
			$responseHasContent                         => $response,
			$resolved instanceof ResponseInterface      => $resolved,
			/** $resolved is renderable but not a renderable exception */
			$resolved instanceof Renderable
			&& !($resolved instanceof Exception)        => $response->withContent($resolved->render()),
			/** $resolved is a renderable exception */
			$resolved instanceof RenderableException    => $response->withStatus($resolved->getCode())->withContent($resolved->render()),
			$resolved instanceof Exception              => $response->withStatus($resolved->getCode()),
			stringable($resolved)                       => $response->withContent((string) $resolved),
			arrayable($resolved)                        => $response->json($resolved),
			default                                     => $response->withStatus(Status::INTERNAL_SERVER_ERROR->value)
		};
	}

	/**
	 * @param ResponseInterface $response
	 * @throws
	 */
	#[NoReturn]
	private function sendHttpResponse(ResponseInterface $response)
	{
		/**
		 * Get an updated request object because there might be a modification happened from the implementor/middlewares
		 *
		 * @var ServerRequestInterface $request
		 */
		$request = $this->container->make(ServerRequestInterface::class);

		foreach ($response->getHeaders() as $name => $values) {
			foreach ($values as $value) {
				header("$name: $value", false);
			}
		}

		foreach ($request->getCookieParams() as $name => $value) {
			setcookie($name, $value);
		}

		http_response_code($response->getStatusCode());
		$response->getBody()->write($response->getContent());
		exit(0);
	}
}
