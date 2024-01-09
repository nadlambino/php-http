<?php

declare(strict_types=1);

namespace Inspira\Http;

use Psr\Http\Message\UriInterface;

/**
 * Represents a Uniform Resource Identifier (URI) and implements the UriInterface.
 */
class Uri implements UriInterface
{
	/**
	 * Constructor to initialize URI components.
	 *
	 * @param string $scheme     The URI scheme.
	 * @param string $host       The URI host.
	 * @param int|null $port     The URI port.
	 * @param string $path       The URI path.
	 * @param string $query      The URI query.
	 * @param string $fragment   The URI fragment.
	 * @param string $userInfo   The URI user info.
	 */
	public function __construct(
		protected string $scheme = '',
		protected string $host = '',
		protected ?int $port = null,
		protected string $path = '',
		protected string $query = '',
		protected string $fragment = '',
		protected string $userInfo = ''
	) {
		$this->setScheme($scheme);
		$this->setHost($host);
		$this->setPort($port);
		$this->setUserInfo($userInfo);
		$this->setPath($path);
		$this->setQuery($query);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getScheme(): string
	{
		return strtolower($this->scheme);
	}

	/**
	 * Set the URI scheme. If the scheme is null, retrieve it from the $_SERVER.
	 *
	 * @param string|null $scheme The URI scheme.
	 *
	 * @return $this
	 */
	public function setScheme(string $scheme = null): static
	{
		$this->scheme = strtolower($scheme ?? $_SERVER['REQUEST_SCHEME'] ?? '');

		return $this;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getAuthority(): string
	{
		$authority = rtrim(implode(':', [$this->getHost(), $this->getPort()]), ':');
		if (!empty($this->userInfo)) {
			$authority = $this->userInfo . '@' . $authority;
		}

		return $authority;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getUserInfo(): string
	{
		return $this->userInfo;
	}

	/**
	 * Set the user info component of the URI. If the userInfo is null, retrieve it from the $_SERVER.
	 *
	 * @param string|null $userInfo The user info component of the URI.
	 *
	 * @return $this
	 */
	public function setUserInfo(string $userInfo = null): static
	{
		if (!empty($userInfo)) {
			$this->userInfo = $userInfo;
			return $this;
		}

		$host = $_SERVER['HTTP_HOST'] ?? '';
		if (str_contains($host, '@')) {
			[$userInfo] = explode('@', $host);
			$this->userInfo = $userInfo;
		}

		return $this;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getHost(): string
	{
		return strtolower($this->host);
	}

	/**
	 * Set the host component of the URI. If the host is empty, retrieve it from the $_SERVER.
	 *
	 * @param string|null $host The URI host.
	 *
	 * @return $this
	 */
	public function setHost(string $host = null): static
	{
		if (empty($host)) {
			$domain = $_SERVER['HTTP_HOST'] ?? '';
			[$host] = explode(':', $domain);
		}

		$this->host = strtolower($host);

		return $this;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getPort(): ?int
	{
		return $this->port;
	}

	/**
	 * Set the port component of URI. If port is empty, retrieve it from the $_SERVER.
	 *
	 * @param int|null $port The URI port.
	 *
	 * @return $this
	 */
	public function setPort(int $port = null): static
	{
		$port ??= $_SERVER['SERVER_PORT'] ?? null;
		$this->port = ($port === 80 || is_null($port)) ? null : (int) $port;

		return $this;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getPath(): string
	{
		return $this->path;
	}

	/**
	 * Set the path component of the URI. If path is empty, retrieve it from the $_SERVER.
	 *
	 * @param string|null $path The URI path.
	 *
	 * @return $this
	 */
	public function setPath(string $path = null): static
	{
		if (empty($path)) {
			$uri = $_SERVER['REQUEST_URI'] ?? '';
			[$path] = explode('?', $uri);
		}

		$this->path = $path;

		return $this;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getQuery(): string
	{
		return $this->query;
	}

	/**
	 * Set the query component of the URI. If the query is empty, retrieve it from the $_SERVER.
	 *
	 * @param string|null $query The URI query.
	 *
	 * @return $this
	 */
	public function setQuery(string $query = null): static
	{
		$this->query = $query ?? $_SERVER['QUERY_STRING'] ?? '';

		return $this;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFragment(): string
	{
		return $this->fragment;
	}

	/**
	 * Set the fragment component of the URI.
	 *
	 * @param string|null $fragment
	 * @return $this
	 */
	public function setFragment(string $fragment = null): static
	{
		$this->fragment = $fragment;

		return $this;
	}

	/**
	 * {@inheritdoc}
	 */
	public function withScheme(string $scheme): UriInterface
	{
		$self = clone $this;
		$self->scheme = $scheme;

		return $self;
	}

	/**
	 * {@inheritdoc}
	 */
	public function withUserInfo(string $user, ?string $password = null): UriInterface
	{
		$self = clone $this;
		$self->userInfo = $user;
		$self->userInfo .= empty($password) ? '' : ':' . $password;

		return $self;
	}

	/**
	 * {@inheritdoc}
	 */
	public function withHost(string $host): UriInterface
	{
		$self = clone $this;
		$self->setHost($host);

		return $self;
	}

	/**
	 * {@inheritdoc}
	 */
	public function withPort(?int $port): UriInterface
	{
		$self = clone $this;
		$self->setPort($port);

		return $self;
	}

	/**
	 * {@inheritdoc}
	 */
	public function withPath(string $path): UriInterface
	{
		$self = clone $this;
		$self->setPath($path);

		return $self;
	}

	/**
	 * {@inheritdoc}
	 */
	public function withQuery(string $query): UriInterface
	{
		$self = clone $this;
		$self->setQuery($query);

		return $self;
	}

	/**
	 * {@inheritdoc}
	 */
	public function withFragment(string $fragment): UriInterface
	{
		$self = clone $this;
		$self->setFragment($fragment);

		return $self;
	}

	/**
	 * Build the complete URI string based on the current object state.
	 *
	 * This method assembles the URI components into a complete URI string.
	 *
	 * @return string The complete URI string.
	 */
	public function build(): string
	{
		$scheme = !empty($this->scheme) ? $this->scheme . '://' : '';
		$host = rtrim($this->getAuthority(), '/');
		$uri = '/' . trim($this->path, '/');
		$query = !empty($this->query) ? '?' . $this->query : '';
		$fragment = !empty($this->fragment) ? '#' . $this->fragment : $this->fragment;

		return $scheme . $host . $uri . $query . $fragment;
	}

	/**
	 * {@inheritdoc}
	 */
	public function __toString(): string
	{
		return $this->build();
	}
}
