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
 * The Router class handles the registration of routes and matching incoming requests.
 *
 * This class allows you to register routes for various HTTP methods (GET, POST, PUT, DELETE).
 * It also supports named routes and provides methods to retrieve information about the current route.
 *
 * @author Ronald Lambino
 */
class Router
{
	/**
	 * Array of all registered routes.
	 *
	 * @var array<string, Route[]> $routes
	 */
	protected array $routes = [
		'GET' => [],
		'POST' => [],
		'PUT' => [],
		'DELETE' => [],
	];

	/**
	 * Array of all registered named routes.
	 *
	 * @var array<string, Route> $namedRoutes
	 */
	protected array $namedRoutes = [];

	/**
	 * The route object that matches the request URI.
	 * It could also be an instance of RouteNotFoundException or MethodNotAllowedException.
	 *
	 * @var Route|Exception|null
	 */
	protected Route|Exception|null $currentRoute;

	/**
	 * The exception to be thrown when a route is not found.
	 *
	 * @var Exception|RenderableException|string
	 */
	protected Exception|RenderableException|string $notFoundException = RouteNotFoundException::class;

	/**
	 * The exception to be thrown when the method is not allowed.
	 *
	 * @var Exception|RenderableException|string
	 */
	protected Exception|RenderableException|string $notAllowedException = MethodNotAllowedException::class;

	/**
	 * Router constructor.
	 *
	 * @param ServerRequestInterface $request
	 */
	public function __construct(protected ServerRequestInterface $request)
	{
	}

	/**
	 * Register a http get request on the given route.
	 *
	 * Callback could be a closure or an array of [class, method].
	 * See register method for all accepted parameters.
	 *
	 * @param string $uri
	 * @param Closure|array $handler
	 * @param array|string $middlewares
	 * @param string|null $name
	 * @return Route
	 * @throws DuplicateRouteNameException
	 */
	public function get(string $uri, Closure|array $handler, array|string $middlewares = [], ?string $name = null): Route
	{
		return $this->register('GET', $uri, $handler, $middlewares, $name);
	}

	/**
	 * Register a http post request on the given route.
	 *
	 * Callback could be a closure or an array of [class, method].
	 * See register method for all accepted parameters.
	 *
	 * @param string $uri
	 * @param Closure|array $handler
	 * @param array|string $middlewares
	 * @param string|null $name
	 * @return Route
	 * @throws DuplicateRouteNameException
	 */
	public function post(string $uri, Closure|array $handler, array|string $middlewares = [], ?string $name = null): Route
	{
		return $this->register('POST', $uri, $handler, $middlewares, $name);
	}

	/**
	 * Register a http put request on the given route.
	 *
	 * Callback could be a closure or an array of [class, method].
	 * See register method for all accepted parameters.
	 *
	 * @param string $uri
	 * @param Closure|array $handler
	 * @param array|string $middlewares
	 * @param string|null $name
	 * @return Route
	 * @throws DuplicateRouteNameException
	 */
	public function put(string $uri, Closure|array $handler, array|string $middlewares = [], ?string $name = null): Route
	{
		return $this->register('PUT', $uri, $handler, $middlewares, $name);
	}

	/**
	 * Register a http delete request on the given route.
	 *
	 * Callback could be a closure or an array of [class, method].
	 * See register method for all accepted parameters.
	 *
	 * @param string $uri
	 * @param Closure|array $handler
	 * @param array|string $middlewares
	 * @param string|null $name
	 * @return Route
	 * @throws DuplicateRouteNameException
	 */
	public function delete(string $uri, Closure|array $handler, array|string $middlewares = [], ?string $name = null): Route
	{
		return $this->register('DELETE', $uri, $handler, $middlewares, $name);
	}

	/**
	 * Get the route object that matches the request URI.
	 *
	 * If the URI matches a registered route and the methods are the same,
	 * then return the route object.
	 *
	 * If the URI matches a registered route and the request method is OPTIONS or HEAD,
	 * then return null. The handler will interpret it that there's no response content.
	 *
	 * If the URI matches a registered route but the method did not match,
	 * then return a MethodNotAllowedException.
	 *
	 * If the URI did not match any registered route,
	 * then return a RouteNotFoundException.
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
	 * Set the value of NotFoundException to handle route not found scenarios.
	 *
	 * @param string|Exception|RenderableException $exception
	 * @return $this
	 * @throws Exception
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
	 * Set the value of NotAllowedException to handle method not allowed scenarios.
	 *
	 * @param string|Exception|RenderableException $exception
	 * @return $this
	 * @throws Exception
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
	 * Get the allowed HTTP methods for the given URI.
	 *
	 * This is usually used by OPTIONS request method.
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
	 * Register a new route with the given attributes.
	 *
	 * @param string $method
	 * @param string $uri
	 * @param Closure|array $handler
	 * @param array|string $middlewares
	 * @param string|null $name
	 * @return Route
	 * @throws DuplicateRouteNameException
	 */
	protected function register(
		string        $method,
		string        $uri,
		Closure|array $handler,
		array|string  $middlewares = [],
		?string       $name = null
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
	 * Register a new named route.
	 *
	 * @param string $name
	 * @param Route $route
	 * @return void
	 * @throws DuplicateRouteNameException
	 */
	protected function registerNamedRoute(string $name, Route $route): void
	{
		$namedRoute = $this->namedRoutes[$name] ?? null;
		if ($namedRoute) {
			$uri = $namedRoute->getMethod() . ' Router.php' . $namedRoute->getUri();
			throw new DuplicateRouteNameException("Route name `$name` has already been used for route `$uri`");
		}

		$this->namedRoutes[$name] = $route;
	}

	/**
	 * Set the value of currentRoute to avoid calling the getMatchedRoute many times throughout the request.
	 *
	 * @return void
	 */
	protected function setCurrentRoute(): void
	{
		$method = $this->request->getMethod();
		$uri = $this->request->getUri()->getPath();
		$this->currentRoute ??= $this->getMatchedRoute($this->routes[$method] ?? [], $uri);
	}

	/**
	 * Get the route instance that matches the given URI.
	 *
	 * @param Route[] $routes
	 * @param string $uri
	 * @return Route|null
	 */
	protected function getMatchedRoute(array $routes, string $uri): ?Route
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
