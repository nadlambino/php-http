<?php

declare(strict_types=1);

namespace Inspira\Http;

use Exception;
use Inspira\Http\Exceptions\RequestPropertyNotFoundException;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

class Request extends Message implements ServerRequestInterface
{
	use Clonable;

	private array $middlewares = [];

	public function __construct(
		protected string $method = '',
		protected array $files = [],
		protected array $query = [],
		protected array $attributes = [],
		protected array $headers = [],
		protected array $cookies = [],
		protected array $server = [],
		protected string $requestTarget = '',
		protected mixed $parsedBody = null,
		protected string $version = '',
		protected ?UriInterface $uri = null,
		?RequestBody $body = null,
	) {
		$this->body = $body ?? new RequestBody();
		$this->uri ??= new Uri();
		$this->extractQueryParams()
			->extractParsedBody();
	}

	/**
	 * Get request parameter/attribute via property access
	 *
	 * @param string $property
	 * @return mixed
	 * @throws Exception
	 */
	public function __get(string $property): mixed
	{
		$data = $this->all();
		if (array_key_exists($property, $data)) {
			return $data[$property];
		}

		throw new RequestPropertyNotFoundException("Property `$property` does not exist on request object");
	}

	/**
	 * Get a request parameter/attribute
	 *
	 * @param string $property
	 * @param $default
	 * @return mixed
	 */
	public function get(string $property, $default = null): mixed
	{
		$data = $this->all();
		return $data[$property] ?? $default;
	}

	/**
	 * Get all request parameters/attributes
	 *
	 * @return array
	 */
	public function all(): array
	{
		return [
			...$this->getParsedBody(),
			...$this->getAttributes(),
			...$this->getQueryParams()
		];
	}

	public function isGet(): bool
	{
		return $this->getMethod() === 'GET';
	}

	public function isPost(): bool
	{
		return $this->getMethod() === 'POST';
	}

	public function isPut(): bool
	{
		return $this->getMethod() === 'PUT';
	}

	public function isDelete(): bool
	{
		return $this->getMethod() === 'DELETE';
	}

	public function isHead(): bool
	{
		return $this->getMethod() === 'HEAD';
	}

	public function isOptions(): bool
	{
		return $this->getMethod() === 'OPTIONS';
	}

	/**
	 * Append a middleware that were run during this request
	 *
	 * @param string $middleware
	 * @param bool $global
	 */
	public function appendMiddleware(string $middleware, bool $global)
	{
		$this->middlewares[$global ? 'global' : 'route'][] = $middleware;
	}

	/**
	 * Get all middlewares that were run during this request
	 *
	 * @return array
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
			$this->parsedBody = [...$_GET,  ...$data];
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
	 * Set new attributes
	 *
	 * @param array $attributes
	 * @return Request
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
