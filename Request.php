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
		protected UriInterface $uri,
		RequestBody $body,
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
	) {
		$this->body = $body;
		$this->all();
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
	 * Retrieves the message's request target.
	 *
	 * Retrieves the message's request-target either as it will appear (for
	 * clients), as it appeared at request (for servers), or as it was
	 * specified for the instance (see withRequestTarget()).
	 *
	 * In most cases, this will be the origin-form of the composed URI,
	 * unless a value was provided to the concrete implementation (see
	 * withRequestTarget() below).
	 *
	 * If no URI is available, and no request-target has been specifically
	 * provided, this method MUST return the string "/".
	 *
	 * @return string
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
	 * Return an instance with the specific request-target.
	 *
	 * If the request needs a non-origin-form request-target — e.g., for
	 * specifying an absolute-form, authority-form, or asterisk-form —
	 * this method may be used to create an instance with the specified
	 * request-target, verbatim.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * changed request target.
	 *
	 * @link http://tools.ietf.org/html/rfc7230#section-5.3 (for the various
	 *     request-target forms allowed in request messages)
	 * @param string $requestTarget
	 * @return static
	 */
	public function withRequestTarget(string $requestTarget): RequestInterface
	{
		$self = clone $this;
		$self->requestTarget = $requestTarget;

		return $self;
	}

	/**
	 * Retrieves the HTTP method of the request.
	 *
	 * @return string Returns the request method.
	 */
	public function getMethod(): string
	{
		if (empty($this->method)) {
			$this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
		}

		return strtoupper($this->method);
	}

	/**
	 * Return an instance with the provided HTTP method.
	 *
	 * While HTTP method names are typically all uppercase characters, HTTP
	 * method names are case-sensitive and thus implementations SHOULD NOT
	 * modify the given string.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * changed request method.
	 *
	 * @param string $method Case-sensitive method.
	 * @return static
	 * @throws InvalidArgumentException for invalid HTTP methods.
	 */
	public function withMethod(string $method): RequestInterface
	{
		$self = clone $this;
		$self->method = $method;

		return $self;
	}

	/**
	 * Retrieves the URI instance.
	 *
	 * This method MUST return a UriInterface instance.
	 *
	 * @link http://tools.ietf.org/html/rfc3986#section-4.3
	 * @return UriInterface Returns a UriInterface instance
	 *     representing the URI of the request.
	 */
	public function getUri(): UriInterface
	{
		return $this->uri;
	}

	/**
	 * Returns an instance with the provided URI.
	 *
	 * This method MUST update the Host header of the returned request by
	 * default if the URI contains a host component. If the URI does not
	 * contain a host component, any pre-existing Host header MUST be carried
	 * over to the returned request.
	 *
	 * You can opt-in to preserving the original state of the Host header by
	 * setting `$preserveHost` to `true`. When `$preserveHost` is set to
	 * `true`, this method interacts with the Host header in the following ways:
	 *
	 * - If the Host header is missing or empty, and the new URI contains
	 *   a host component, this method MUST update the Host header in the returned
	 *   request.
	 * - If the Host header is missing or empty, and the new URI does not contain a
	 *   host component, this method MUST NOT update the Host header in the returned
	 *   request.
	 * - If a Host header is present and non-empty, this method MUST NOT update
	 *   the Host header in the returned request.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * new UriInterface instance.
	 *
	 * @link http://tools.ietf.org/html/rfc3986#section-4.3
	 * @param UriInterface $uri New request URI to use.
	 * @param bool $preserveHost Preserve the original state of the Host header.
	 * @return static
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
	 * Retrieve server parameters.
	 *
	 * Retrieves data related to the incoming request environment,
	 * typically derived from PHP's $_SERVER superglobal. The data IS NOT
	 * REQUIRED to originate from $_SERVER.
	 *
	 * @return array
	 */
	public function getServerParams(): array
	{
		if (empty($this->server)) {
			$this->server = $_SERVER;
		}

		return $this->server;
	}

	/**
	 * Retrieve cookies.
	 *
	 * Retrieves cookies sent by the client to the server.
	 *
	 * The data MUST be compatible with the structure of the $_COOKIE
	 * superglobal.
	 *
	 * @return array
	 */
	public function getCookieParams(): array
	{
		if (empty($this->cookies)) {
			$this->cookies = $_COOKIE;
		}

		return $this->cookies;
	}

	/**
	 * Return an instance with the specified cookies.
	 *
	 * The data IS NOT REQUIRED to come from the $_COOKIE superglobal, but MUST
	 * be compatible with the structure of $_COOKIE. Typically, this data will
	 * be injected at instantiation.
	 *
	 * This method MUST NOT update the related Cookie header of the request
	 * instance, nor related values in the server params.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * updated cookie values.
	 *
	 * @param array $cookies Array of key/value pairs representing cookies.
	 * @return static
	 */
	public function withCookieParams(array $cookies): ServerRequestInterface
	{
		$self = clone $this;
		$self->cookies = $cookies;

		return $self;
	}

	/**
	 * Retrieve query string arguments.
	 *
	 * Retrieves the deserialized query string arguments, if any.
	 *
	 * Note: the query params might not be in sync with the URI or server
	 * params. If you need to ensure you are only getting the original
	 * values, you may need to parse the query string from `getUri()->getQuery()`
	 * or from the `QUERY_STRING` server param.
	 *
	 * @return array
	 */
	public function getQueryParams(): array
	{
		if ($this->query) {
			return $this->query;
		}

		$queries = explode('&', $this->uri->getQuery());
		foreach ($queries as $q) {
			$query = explode('=', $q);
			$name = $query[0] ?? '';
			$value = urldecode($query[1] ?? '');
			if (empty($name)) continue;

			$this->query[$name] = $value;
		}

		return $this->query;
	}

	/**
	 * Return an instance with the specified query string arguments.
	 *
	 * These values SHOULD remain immutable over the course of the incoming
	 * request. They MAY be injected during instantiation, such as from PHP's
	 * $_GET superglobal, or MAY be derived from some other value such as the
	 * URI. In cases where the arguments are parsed from the URI, the data
	 * MUST be compatible with what PHP's parse_str() would return for
	 * purposes of how duplicate query parameters are handled, and how nested
	 * sets are handled.
	 *
	 * Setting query string arguments MUST NOT change the URI stored by the
	 * request, nor the values in the server params.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * updated query string arguments.
	 *
	 * @param array $query Array of query string arguments, typically from
	 *     $_GET.
	 * @return static
	 */
	public function withQueryParams(array $query): ServerRequestInterface
	{
		$self = clone $this;
		$self->query = $query;

		return $self;
	}

	/**
	 * Retrieve normalized file upload data.
	 *
	 * This method returns upload metadata in a normalized tree, with each leaf
	 * an instance of Psr\Http\Message\UploadedFileInterface.
	 *
	 * These values MAY be prepared from $_FILES or the message body during
	 * instantiation, or MAY be injected via withUploadedFiles().
	 *
	 * @return array An array tree of UploadedFileInterface instances; an empty
	 *     array MUST be returned if no data is present.
	 */
	public function getUploadedFiles(): array
	{
		if (empty($this->files)) {
			$this->files = $_FILES;
		}

		return $this->files;
	}

	/**
	 * Create a new instance with the specified uploaded files.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * updated body parameters.
	 *
	 * @param array $uploadedFiles An array tree of UploadedFileInterface instances.
	 * @return static
	 * @throws InvalidArgumentException if an invalid structure is provided.
	 */
	public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface
	{
		$self = clone $this;
		$self->files = $uploadedFiles;

		return $self;
	}

	/**
	 * Retrieve any parameters provided in the request body.
	 *
	 * If the request Content-Type is either application/x-www-form-urlencoded
	 * or multipart/form-data, and the request method is POST, this method MUST
	 * return the contents of $_POST.
	 *
	 * Otherwise, this method may return any results of deserializing
	 * the request body content; as parsing returns structured content, the
	 * potential types MUST be arrays or objects only. A null value indicates
	 * the absence of body content.
	 *
	 * @return null|array|object The deserialized body parameters, if any.
	 *     These will typically be an array or object.
	 */
	public function getParsedBody(): array|object|null
	{
		if ($this->parsedBody) {
			return $this->parsedBody;
		}

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

		return $this->parsedBody;
	}

	/**
	 * Return an instance with the specified body parameters.
	 *
	 * These MAY be injected during instantiation.
	 *
	 * If the request Content-Type is either application/x-www-form-urlencoded
	 * or multipart/form-data, and the request method is POST, use this method
	 * ONLY to inject the contents of $_POST.
	 *
	 * The data IS NOT REQUIRED to come from $_POST, but MUST be the results of
	 * deserializing the request body content. Deserialization/parsing returns
	 * structured data, and, as such, this method ONLY accepts arrays or objects,
	 * or a null value if nothing was available to parse.
	 *
	 * As an example, if content negotiation determines that the request data
	 * is a JSON payload, this method could be used to create a request
	 * instance with the deserialized parameters.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * updated body parameters.
	 *
	 * @param null|array|object $data The deserialized body data. This will
	 *     typically be in an array or object.
	 * @return static
	 * @throws InvalidArgumentException if an unsupported argument type is
	 *     provided.
	 */
	public function withParsedBody($data): ServerRequestInterface
	{
		$self = clone $this;
		$self->parsedBody = $data;

		return $self;
	}

	/**
	 * Retrieve attributes derived from the request.
	 *
	 * The request "attributes" may be used to allow injection of any
	 * parameters derived from the request: e.g., the results of path
	 * match operations; the results of decrypting cookies; the results of
	 * deserializing non-form-encoded message bodies; etc. Attributes
	 * will be application and request specific, and CAN be mutable.
	 *
	 * @return array Attributes derived from the request.
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
	 * Retrieve a single derived request attribute.
	 *
	 * Retrieves a single derived request attribute as described in
	 * getAttributes(). If the attribute has not been previously set, returns
	 * the default value as provided.
	 *
	 * This method obviates the need for a hasAttribute() method, as it allows
	 * specifying a default value to return if the attribute is not found.
	 *
	 * @param string $name The attribute name.
	 * @param mixed $default Default value to return if the attribute does not exist.
	 * @return mixed
	 * @see getAttributes()
	 */
	public function getAttribute(string $name, $default = null): mixed
	{
		return $this->attributes[$name] ?? $default;
	}

	/**
	 * Return an instance with the specified derived request attribute.
	 *
	 * This method allows setting a single derived request attribute as
	 * described in getAttributes().
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * updated attribute.
	 *
	 * @param string $name The attribute name.
	 * @param mixed $value The value of the attribute.
	 * @return static
	 * @see getAttributes()
	 */
	public function withAttribute(string $name, $value): ServerRequestInterface
	{
		$self = clone $this;
		$self->attributes[$name] = $value;

		return $self;
	}

	/**
	 * Return an instance that removes the specified derived request attribute.
	 *
	 * This method allows removing a single derived request attribute as
	 * described in getAttributes().
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that removes
	 * the attribute.
	 *
	 * @param string $name The attribute name.
	 * @return static
	 * @see getAttributes()
	 */
	public function withoutAttribute(string $name): ServerRequestInterface
	{
		$self = clone $this;
		unset($self->attributes[$name]);

		return $self;
	}
}
