<?php

declare(strict_types=1);

namespace Inspira\Http;

use Exception;
use Inspira\Http\Exceptions\RequestPropertyNotFoundException;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * Represents an HTTP request and implements the ServerRequestInterface.
 */
class Request extends Message implements ServerRequestInterface
{
	use Clonable;

	/**
	 * @var array Middleware array containing both global and route-specific middleware.
	 */
	private array $middlewares = [];

	/**
	 * Constructor for the Request class.
	 *
	 * @param string $method The HTTP request method.
	 * @param array $files An array of uploaded files.
	 * @param array $query An array of query parameters.
	 * @param array $attributes An array of request attributes.
	 * @param array $headers An array of request headers.
	 * @param array $cookies An array of request cookies.
	 * @param array $server An array of server parameters.
	 * @param string $requestTarget The request target.
	 * @param mixed $parsedBody The parsed request body.
	 * @param string $version The HTTP protocol version.
	 * @param UriInterface|null $uri The request URI.
	 * @param RequestBody|null $body The request body.
	 */
	public function __construct(
		protected string        $method = '',
		protected array         $files = [],
		protected array         $query = [],
		protected array         $attributes = [],
		protected array         $headers = [],
		protected array         $cookies = [],
		protected array         $server = [],
		protected string        $requestTarget = '',
		protected mixed         $parsedBody = null,
		protected string        $version = '',
		protected ?UriInterface $uri = null,
		?RequestBody            $body = null,
	)
	{
		$this->body = $body ?? new RequestBody();
		$this->uri ??= new Uri();
		$this->extractQueryParams()->extractParsedBody();
	}

	/**
	 * Get request parameter/attribute via property access.
	 *
	 * @param string $property The property to retrieve.
	 * @return mixed The value of the specified property.
	 * @throws Exception If the property does not exist on the request object.
	 */
	public function __get(string $property): mixed
	{
		$data = $this->all();
		if (array_key_exists($property, $data)) {
			return $data[$property];
		}

		throw new RequestPropertyNotFoundException("Property `$property` does not exist on the request object");
	}

	/**
	 * Get a request parameter/attribute.
	 *
	 * @param string $property The property to retrieve.
	 * @param mixed $default The default value if the property is not found.
	 * @return mixed The value of the specified property or the default value.
	 */
	public function get(string $property, mixed $default = null): mixed
	{
		$data = $this->all();
		return $data[$property] ?? $default;
	}

	/**
	 * Get all request parameters/attributes.
	 *
	 * @return array An array containing all request parameters and attributes.
	 */
	public function all(): array
	{
		return [
			...$this->getParsedBody(),
			...$this->getAttributes(),
			...$this->getQueryParams(),
		];
	}

	/**
	 * Check if the request method is GET.
	 *
	 * @return bool True if the request method is GET; false otherwise.
	 */
	public function isGet(): bool
	{
		return $this->getMethod() === 'GET';
	}

	/**
	 * Check if the request method is POST.
	 *
	 * @return bool True if the request method is POST; false otherwise.
	 */
	public function isPost(): bool
	{
		return $this->getMethod() === 'POST';
	}

	/**
	 * Check if the request method is PUT.
	 *
	 * @return bool True if the request method is PUT; false otherwise.
	 */
	public function isPut(): bool
	{
		return $this->getMethod() === 'PUT';
	}

	/**
	 * Check if the request method is DELETE.
	 *
	 * @return bool True if the request method is DELETE; false otherwise.
	 */
	public function isDelete(): bool
	{
		return $this->getMethod() === 'DELETE';
	}

	/**
	 * Check if the request method is HEAD.
	 *
	 * @return bool True if the request method is HEAD; false otherwise.
	 */
	public function isHead(): bool
	{
		return $this->getMethod() === 'HEAD';
	}

	/**
	 * Check if the request method is OPTIONS.
	 *
	 * @return bool True if the request method is OPTIONS; false otherwise.
	 */
	public function isOptions(): bool
	{
		return $this->getMethod() === 'OPTIONS';
	}

	/**
	 * Append a middleware that was run during this request.
	 *
	 * @param string $middleware The middleware to append.
	 * @param bool $global Whether the middleware is global or route-specific.
	 */
	public function appendMiddleware(string $middleware, bool $global)
	{
		$this->middlewares[$global ? 'global' : 'route'][] = $middleware;
	}

	/**
	 * Get all middlewares that were run during this request.
	 *
	 * @return array An array containing global and route-specific middlewares.
	 */
	public function getMiddlewares(): array
	{
		return $this->middlewares;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getRequestTarget(): string
	{
		if (empty($this->requestTarget)) {
			$path = '/' . trim($this->uri->getPath(), '/');
			$uri = rtrim(implode('?', [$path, $this->uri->getQuery()]), '?');
			$fragment = $this->uri->getFragment();
			$this->requestTarget = empty($fragment) ? $uri : implode('#', [$uri, $fragment]);
		}

		return $this->requestTarget;
	}

	/**
	 * {@inheritdoc}
	 */
	public function withRequestTarget(string $requestTarget): RequestInterface
	{
		$self = clone $this;
		$self->requestTarget = $requestTarget;

		return $self;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getMethod(): string
	{
		if (empty($this->method)) {
			$this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
		}

		return strtoupper($this->method);
	}

	/**
	 * {@inheritdoc}
	 */
	public function withMethod(string $method): RequestInterface
	{
		$self = clone $this;
		$self->method = $method;

		return $self;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getUri(): UriInterface
	{
		return $this->uri;
	}

	/**
	 * {@inheritdoc}
	 */
	public function withUri(UriInterface $uri, bool $preserveHost = false): RequestInterface
	{
		$self = clone $this;
		$self->uri = $uri;

		if ($preserveHost && !$uri->getHost()) {
			return $self;
		}

		if (!$preserveHost) {
			$self->headers['Host'] = [$uri->getHost()];
		}

		return $self;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getServerParams(): array
	{
		if (empty($this->server)) {
			$this->server = $_SERVER;
		}

		return $this->server;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getCookieParams(): array
	{
		if (empty($this->cookies)) {
			$this->cookies = $_COOKIE;
		}

		return $this->cookies;
	}

	/**
	 * {@inheritdoc}
	 */
	public function withCookieParams(array $cookies): ServerRequestInterface
	{
		$self = clone $this;
		$self->cookies = $cookies;

		return $self;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getQueryParams(): array
	{
		if ($this->query) {
			return $this->query;
		}

		$this->extractQueryParams();

		return $this->query;
	}

	/**
	 * Extract query parameters from the request URI.
	 *
	 * @return static The modified request object.
	 */
	protected function extractQueryParams(): static
	{
		$queries = explode('&', $this->uri->getQuery());
		foreach ($queries as $q) {
			$query = explode('=', $q);
			$name = $query[0] ?? '';
			$value = urldecode($query[1] ?? '');
			if (empty($name)) continue;

			$this->query[$name] = $value;
		}

		return $this;
	}

	/**
	 * {@inheritdoc}
	 */
	public function withQueryParams(array $query): ServerRequestInterface
	{
		$self = clone $this;
		$self->query = $query;

		return $self;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getUploadedFiles(): array
	{
		if (empty($this->files)) {
			$this->files = $_FILES;
		}

		return $this->files;
	}

	/**
	 * {@inheritdoc}
	 */
	public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface
	{
		$self = clone $this;
		$self->files = $uploadedFiles;

		return $self;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getParsedBody(): array|object|null
	{
		if ($this->parsedBody) {
			return $this->parsedBody;
		}

		$this->extractParsedBody();

		return $this->parsedBody;
	}

	/**
	 * Extract and parse the request body.
	 *
	 * @return static The modified request object.
	 */
	protected function extractParsedBody(): static
	{
		$contents = $this->body->getContents();
		$parsed = json_decode($contents, true);

		if (in_array($this->getMethod(), ['POST', 'PUT', 'DELETE'])) {
			$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
			$data = is_array($parsed) ? $parsed : [];
			$this->parsedBody = $contentType === 'application/json' ? $data : [...$_POST, ...$_FILES];
		} else {
			$data = is_array($parsed) ? $parsed : [];
			$this->parsedBody = [...$_GET, ...$data];
		}

		return $this;
	}

	/**
	 * {@inheritdoc}
	 */
	public function withParsedBody($data): ServerRequestInterface
	{
		$self = clone $this;
		$self->parsedBody = $data;

		return $self;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getAttributes(): array
	{
		return $this->attributes;
	}

	/**
	 * Set new request attributes.
	 *
	 * @param array $attributes The attributes to set.
	 * @return Request The modified request object.
	 */
	public function withAttributes(array $attributes): Request
	{
		$self = clone $this;
		$self->attributes = $attributes;

		return $self;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getAttribute(string $name, $default = null): mixed
	{
		return $this->attributes[$name] ?? $default;
	}

	/**
	 * {@inheritdoc}
	 */
	public function withAttribute(string $name, $value): ServerRequestInterface
	{
		$self = clone $this;
		$self->attributes[$name] = $value;

		return $self;
	}

	/**
	 * {@inheritdoc}
	 */
	public function withoutAttribute(string $name): ServerRequestInterface
	{
		$self = clone $this;
		unset($self->attributes[$name]);

		return $self;
	}
}
