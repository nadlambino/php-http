<?php

declare(strict_types=1);

namespace Inspira\Http\Router;

use Closure;

class Route
{
	/**
	 * Given name for this route
	 *
	 * @var string
	 */
	protected string $name;

	/**
	 * Route uri pattern
	 *
	 * @var string
	 */
	protected string $uri;

	/**
	 * The handler for this route
	 * It can be a closure or an array of class and method
	 *
	 * @var Closure|String[]|null
	 */
	protected Closure|array|null $handler;

	/**
	 * Route methods
	 *
	 * @var array
	 */
	protected array $methods = ['HEAD', 'OPTIONS'];

	/**
	 * Route registered method
	 *
	 * @var string
	 */
	protected string $method;

	/**
	 * The values for the route attributes that matches the uri pattern
	 * For example: /user/:id/post/:post?
	 * The attributes would be ['id' => '', 'post' => '']
	 * where `id` is a required attribute and `post` is optional
	 *
	 * @var array<string, string|int>
	 */
	protected array $attributes = [];

	/**
	 * Route middlewares
	 *
	 * @var array
	 */
	protected array $middlewares = [];

	public function __construct(
		string $method,
		string $uri,
		Closure|array $handler,
		array $middlewares = [],
		?string $name = null
	)
	{
		$this->setMethod($method);
		$this->name = $name ?? $uri;
		$this->uri = $uri;
		$this->handler = $handler;
		$this->middlewares(...$middlewares);
	}

	public function middlewares(...$middlewares): self
	{
		$this->middlewares = [...$this->middlewares, ...$middlewares];

		return $this;
	}

	public function getHandler(): array|Closure|null
	{
		return $this->handler;
	}

	public function getUri(): string
	{
		return $this->uri;
	}

	public function getMethods(): array
	{
		return $this->methods;
	}

	public function getMethod(): string
	{
		return $this->method;
	}

	public function getMiddlewares(): array
	{
		return $this->middlewares;
	}

	/**
	 * Return this instance if it matches the given request uri
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

	public function getAttributes(): array
	{
		return $this->attributes;
	}

	protected function setAttributes(array $attributes): void
	{
		foreach ($attributes as $key => $value) {
			if (!is_string($key)) {
				continue;
			}

			$this->attributes[$key] = empty($value) ? null : $value;
		}
	}

	protected function setMethod(string $method): void
	{
		$method = strtoupper($method);
		$this->methods[] = $method;
		$this->method = $method;
	}
}
