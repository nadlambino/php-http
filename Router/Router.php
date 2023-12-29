<?php

declare(strict_types=1);

namespace Inspira\Http\Router;

use Closure;
use Exception;
use Inspira\Contracts\RenderableException;
use Inspira\Http\Exceptions\DuplicateRouteNameException;
use Inspira\Http\Exceptions\MethodNotAllowedException;
use Inspira\Http\Exceptions\RouteNotFoundException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @author Ronald Lambino
 */
class Router
{
	/**
	 * Array of all registered routes
	 *
	 * @var array<string, Route[]> $routes
	 */
	private array $routes = [
		'GET'       => [],
		'POST'      => [],
		'PUT'       => [],
		'DELETE'    => [],
	];

	/**
	 * Array of all registered named routes
	 *
	 * @var array<string, Route> $namedRoutes
	 */
	private array $namedRoutes = [];

	/**
	 * The route object that matches the request uri
	 * It could also be an instance of RouteNotFoundException or MethodNotAllowedException
	 *
	 * @var Route|Exception|null
	 */
	private Route|Exception|null $currentRoute;

	/**
	 * The exception to be thrown when route not found was encountered
	 *
	 * @var Exception|RenderableException|string
	 */
	private Exception|RenderableException|string $notFoundException = RouteNotFoundException::class;

	/**
	 * The exception to be thrown when method not allowed was encountered
	 *
	 * @var Exception|RenderableException|string
	 */
	private Exception|RenderableException|string $notAllowedException = MethodNotAllowedException::class;

	public function __construct(protected ServerRequestInterface $request) { }

	/**
	 * Register a http get request on the given route
	 * Callback could be a closure or an array of [class, method]
	 * See register method for all accepted parameters
	 *
	 * @param string $uri
	 * @param Closure|array $handler
	 * @param array|string $middlewares
	 * @param string|null $name
	 * @return Route
	 */
	public function get(string $uri, Closure|array $handler, array|string $middlewares = [], ?string $name = null): Route
	{
		return $this->register('GET', $uri, $handler, $middlewares, $name);
	}

	/**
	 * Register a http post request on the given route
	 * Callback could be a closure or an array of [class, method]
	 * See register method for all accepted parameters
	 *
	 * @param string $uri
	 * @param Closure|array $handler
	 * @param array|string $middlewares
	 * @param string|null $name
	 * @return Route
	 */
	public function post(string $uri, Closure|array $handler, array|string $middlewares = [], ?string $name = null): Route
	{
		return $this->register('POST', $uri, $handler, $middlewares, $name);
	}

	/**
	 * Register a http put request on the given route
	 * Callback could be a closure or an array of [class, method]
	 * See register method for all accepted parameters
	 *
	 * @param string $uri
	 * @param Closure|array $handler
	 * @param array|string $middlewares
	 * @param string|null $name
	 * @return Route
	 */
	public function put(string $uri, Closure|array $handler, array|string $middlewares = [], ?string $name = null): Route
	{
		return $this->register('PUT', $uri, $handler, $middlewares, $name);
	}

	/**
	 * Register a http delete request on the given route
	 * Callback could be a closure or an array of [class, method]
	 * See register method for all accepted parameters
	 *
	 * @param string $uri
	 * @param Closure|array $handler
	 * @param array|string $middlewares
	 * @param string|null $name
	 * @return Route
	 */
	public function delete(string $uri, Closure|array $handler, array|string $middlewares = [], ?string $name = null): Route
	{
		return $this->register('DELETE', $uri, $handler, $middlewares, $name);
	}

	/**
	 * Get the route object that matches the request uri
	 *
	 * If the uri matches a registered route and the methods are the same
	 * Then return the route object
	 *
	 * If the uri matches a registered route and the request method is OPTIONS or HEAD
	 * Then return a null. The handler will interpret it that there's no response content
	 *
	 * If the uri matches a registered route but the method did not match
	 * Then return a MethodNotAllowedException
	 *
	 * If the uri did not match any registered route
	 * Then return a RouteNotFoundException
	 *
	 * @return Route|Exception|null
	 */
	public function getCurrentRoute(): Route|Exception|null
	{
		$this->setCurrentRoute();

		if ($this->currentRoute) {
			return $this->currentRoute;
		}

		$routes = [...$this->routes];
		unset($routes[$this->request->getMethod()]);
		$route = $this->getMatchedRoute(
			array_merge(...array_values($routes)),
			$this->request->getUri()->getPath()
		);

		if ($route && ($this->request->isOptions() || $this->request->isHead())) {
			return null;
		}

		if ($route) {
			return $this->currentRoute = is_object($this->notAllowedException)
				? $this->notAllowedException
				: new $this->notAllowedException();
		}

		return $this->currentRoute = is_object($this->notFoundException)
			? $this->notFoundException
			: new $this->notFoundException();
	}

	/**
	 * @param string|Exception|RenderableException $exception
	 * @return $this
	 * @throws
	 */
	public function setNotFoundException(string|Exception|RenderableException $exception): self
	{
		if (is_string($exception) && !class_exists($exception)) {
			throw new Exception("Unknown class `$exception`");
		}

		$this->notFoundException = $exception;

		return $this;
	}

	/**
	 * @param string|Exception|RenderableException $exception
	 * @return $this
	 * @throws
	 */
	public function setNotAllowedException(string|Exception|RenderableException $exception): self
	{
		if (is_string($exception) && !class_exists($exception)) {
			throw new Exception("Unknown class `$exception`");
		}

		$this->notAllowedException = $exception;

		return $this;
	}

	/**
	 * Get methods that are allowed from the given uri
	 * This is usually used by OPTIONS request method
	 *
	 * @param string $uri
	 * @return array
	 */
	public function getAllowedMethods(string $uri): array
	{
		$allowed = [];
		foreach ($this->routes as $routes) {
			foreach ($routes as $route) {
				if ($route->getIfMatched($uri)) {
					$allowed[] = $route->getMethods();
				}
			}
		}

		return array_unique(array_merge(...$allowed));
	}

	/**
	 * Register a new route with the given attributes
	 *
	 * @param string $method
	 * @param string $uri
	 * @param Closure|array $handler
	 * @param array|string $middlewares
	 * @param string|null $name
	 * @return Route
	 * @throws
	 */
	private function register(
		string $method,
		string $uri,
		Closure|array $handler,
		array|string $middlewares = [],
		?string $name = null
	): Route
	{
		$uri = rtrim($uri, '/');
		$uri = empty($uri) ? '/' : $uri;
		$middlewares = is_array($middlewares) ? $middlewares : [$middlewares];

		$route = new Route($method, $uri, $handler, $middlewares, $name);
		$this->routes[$method][$uri] = $route;
		$this->routes['HEAD'][$uri] = $route;
		$this->routes['OPTIONS'][$uri] = $route;

		if ($name) {
			$this->registerNamedRoute($name, $route);
		}

		return $route;
	}

	/**
	 * @param string $name
	 * @param Route $route
	 * @return void
	 * @throws DuplicateRouteNameException
	 */
	private function registerNamedRoute(string $name, Route $route): void
	{
		$namedRoute = $this->namedRoutes[$name] ?? null;
		if ($namedRoute) {
			$uri = $namedRoute->getMethod() . ' ' . $namedRoute->getUri();
			throw new DuplicateRouteNameException("Route name `$name` has already been used for route `$uri`");
		}

		$this->namedRoutes[$name] = $route;
	}

	/**
	 * Set the value of currentRoute to avoid calling the getMatchedRoute many times throughout the request
	 *
	 * @return void
	 */
	private function setCurrentRoute(): void
	{
		$method = $this->request->getMethod();
		$uri = $this->request->getUri()->getPath();
		$this->currentRoute ??= $this->getMatchedRoute($this->routes[$method] ?? [], $uri);
	}

	/**
	 * Get the route instance that matches the given uri
	 *
	 * @param Route[] $routes
	 * @param string $uri
	 * @return Route|null
	 */
	private function getMatchedRoute(array $routes, string $uri): ?Route
	{
		$route = $routes[$uri] ?? null;
		if ($route instanceof Route) {
			return $route;
		}

		foreach ($routes as $route) {
			$matchedRoute = $route->getIfMatched($uri);

			if ($matchedRoute) {
				return $matchedRoute;
			}
		}

		return null;
	}
}
