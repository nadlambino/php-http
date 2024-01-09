<?php

declare(strict_types=1);

namespace Inspira\Http\Router;

use Closure;

/**
 * The Route class represents a registered route in the router.
 *
 * A route includes information such as the URI pattern, HTTP methods,
 * handler (closure or controller method), and any middleware assigned to it.
 */
class Route
{
	/**
	 * Given name for this route.
	 *
	 * @var string
	 */
	protected string $name;

	/**
	 * Route URI pattern.
	 *
	 * @var string
	 */
	protected string $uri;

	/**
	 * The handler for this route.
	 * It can be a closure or an array of class and method.
	 *
	 * @var Closure|array|null
	 */
	protected Closure|array|null $handler;

	/**
	 * Route methods.
	 *
	 * @var array
	 */
	protected array $methods = ['HEAD', 'OPTIONS'];

	/**
	 * Route registered method.
	 *
	 * @var string
	 */
	protected string $method;

	/**
	 * The values for the route attributes that match the URI pattern.
	 * For example: /user/:id/post/:post?
	 * The attributes would be ['id' => '', 'post' => '']
	 * where `id` is a required attribute and `post` is optional.
	 *
	 * @var array<string, string|int>
	 */
	protected array $attributes = [];

	/**
	 * Route middlewares.
	 *
	 * @var array
	 */
	protected array $middlewares = [];

	/**
	 * Route constructor.
	 *
	 * @param string $method
	 * @param string $uri
	 * @param Closure|array $handler
	 * @param array $middlewares
	 * @param string|null $name
	 */
	public function __construct(
		string        $method,
		string        $uri,
		Closure|array $handler,
		array         $middlewares = [],
		?string       $name = null
	)
	{
		$this->setMethod($method);
		$this->name = $name ?? $uri;
		$this->uri = $uri;
		$this->handler = $handler;
		$this->middlewares(...$middlewares);
	}

	/**
	 * Add middlewares to the route.
	 *
	 * @param mixed ...$middlewares
	 * @return $this
	 */
	public function middlewares(...$middlewares): self
	{
		$this->middlewares = [...$this->middlewares, ...$middlewares];

		return $this;
	}

	/**
	 * Get the handler for the route.
	 *
	 * @return array|Closure|null
	 */
	public function getHandler(): array|Closure|null
	{
		return $this->handler;
	}

	/**
	 * Get the URI pattern for the route.
	 *
	 * @return string
	 */
	public function getUri(): string
	{
		return $this->uri;
	}

	/**
	 * Get the allowed HTTP methods for the route.
	 *
	 * @return array
	 */
	public function getMethods(): array
	{
		return $this->methods;
	}

	/**
	 * Get the registered HTTP method for the route.
	 *
	 * @return string
	 */
	public function getMethod(): string
	{
		return $this->method;
	}

	/**
	 * Get the middlewares assigned to the route.
	 *
	 * @return array
	 */
	public function getMiddlewares(): array
	{
		return $this->middlewares;
	}

	/**
	 * Return this instance if it matches the given request URI.
	 *
	 * @param string $uri
	 * @return $this|null
	 */
	public function getIfMatched(string $uri): ?self
	{
		$uri = trim($uri, '/');
		$registeredUri = trim($this->uri, '/');
		$string = str_replace('/', '\/?', $registeredUri);
		$pattern = '/:(\w+)/';
		$replacement = '(?P<$1>\w+)';
		$newPattern = preg_replace($pattern, $replacement, $string);
		$matched = preg_match('#^' . $newPattern . '$#', $uri, $matches);

		if ($matched === false || $matched === 0) {
			return null;
		}

		$this->setAttributes($matches);

		return $this;
	}

	/**
	 * Get the attributes matched from the URI pattern.
	 *
	 * @return array
	 */
	public function getAttributes(): array
	{
		return $this->attributes;
	}

	/**
	 * Set the attributes from the matched URI.
	 *
	 * @param array $attributes
	 * @return void
	 */
	protected function setAttributes(array $attributes): void
	{
		foreach ($attributes as $key => $value) {
			if (!is_string($key)) {
				continue;
			}

			$this->attributes[$key] = empty($value) ? null : $value;
		}
	}

	/**
	 * Set the HTTP method for the route.
	 *
	 * @param string $method
	 * @return void
	 */
	protected function setMethod(string $method): void
	{
		$method = strtoupper($method);
		$this->methods[] = $method;
		$this->method = $method;
	}
}
