<?php

declare(strict_types=1);

namespace Inspira\Http;

use Psr\Http\Message\UriInterface;

class Uri implements UriInterface
{
	public function __construct(
		protected string $scheme = '',
		protected string $host = '',
		protected ?int $port = null,
		protected string $path = '',
		protected string $query = '',
		protected string $fragment = '',
		protected string $userInfo = '',
	)
	{
		$this->setScheme($scheme);
		$this->setHost($host);
		$this->setPort($port);
		$this->setUserInfo($userInfo);
		$this->setPath($path);
		$this->setQuery($query);
	}

	/**
	 * @inheritdoc
	 */
	public function getScheme(): string
	{
		return strtolower($this->scheme);
	}

	/**
	 * Set the URI scheme. If the scheme is null, get the scheme from the $_SERVER
	 *
	 * @param string|null $scheme
	 * @return $this
	 */
	public function setScheme(string $scheme = null): static
	{
		$this->scheme = strtolower($scheme ?? $_SERVER['REQUEST_SCHEME'] ?? '');

		return $this;
	}

	/**
	 * @inheritdoc
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
	 * @inheritdoc
	 */
	public function getUserInfo(): string
	{
		return $this->userInfo;
	}

	/**
	 * Set the user info component of the URI. If the userInfo is null, get the value from the $_SERVER.
	 *
	 * @param string|null $userInfo
	 * @return $this
	 */
	public function setUserInfo(string $userInfo = null): static
	{
		if (!empty($userInfo)) {
			$this->userInfo = $userInfo;
			return $this;
		}

		$host = $_SERVER['HTTP_HOST'] ?? '';
		if (str_contains('@', $host)) {
			[$userInfo] = explode('@', $host)[0];
			$this->userInfo = $userInfo;
		}

		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function getHost(): string
	{
		return strtolower($this->host);
	}

	/**
	 * Set the host component of the URI. If the host is empty, get the value from the $_SERVER
	 *
	 * @param string|null $host
	 * @return $this
	 */
	public function setHost(string $host = null): static
	{
		if (!empty($host)) {
			$this->host = strtolower($host);
			return $this;
		}

		$host = $_SERVER['HTTP_HOST'] ?? '';
		[$hostname] = explode(':', $host);
		$this->host = strtolower($hostname);

		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function getPort(): ?int
	{
		return $this->port;
	}

	/**
	 * Set the port component of URI. If port is empty, get the value from the $_SERVER
	 *
	 * @param int|null $port
	 * @return $this
	 */
	public function setPort(int $port = null): static
	{
		$port ??= $_SERVER['SERVER_PORT'] ?? null;
		$this->port = ($port === 80 || is_null($port)) ? null : (int) $port;

		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function getPath(): string
	{
		return $this->path;
	}

	/**
	 * Set the path component of the URI. If path is empty, get the value from the $_SERVER.
	 *
	 * @param string|null $path
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
	 * @inheritdoc
	 */
	public function getQuery(): string
	{
		return $this->query;
	}

	/**
	 * Set the query component of the URI. If the query is empty, get the value from the $_SERVER.
	 *
	 * @param string|null $query
	 * @return $this
	 */
	public function setQuery(string $query = null): static
	{
		$this->query = $query ?? $_SERVER['QUERY_STRING'] ?? '';

		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function getFragment(): string
	{
		return $this->fragment;
	}

	/**
	 * @inheritdoc
	 */
	public function withScheme(string $scheme): UriInterface
	{
		$self = clone $this;
		$self->scheme = $scheme;

		return $self;
	}

	/**
	 * @inheritdoc
	 */
	public function withUserInfo(string $user, ?string $password = null): UriInterface
	{
		$self = clone $this;
		$self->userInfo = $user;
		if (!empty($password)) {
			$self->userInfo .= ':' . $password;
		}

		return $self;
	}

	/**
	 * @inheritdoc
	 */
	public function withHost(string $host): UriInterface
	{
		$self = clone $this;
		$self->host = $host;

		return $self;
	}

	/**
	 * @inheritdoc
	 */
	public function withPort(?int $port): UriInterface
	{
		$self = clone $this;
		$self->port = $port;

		return $self;
	}

	/**
	 * @inheritdoc
	 */
	public function withPath(string $path): UriInterface
	{
		$self = clone $this;
		$self->path = $path;

		return $self;
	}

	/**
	 * @inheritdoc
	 */
	public function withQuery(string $query): UriInterface
	{
		$self = clone $this;
		$self->$query = $query;

		return $self;
	}

	/**
	 * @inheritdoc
	 */
	public function withFragment(string $fragment): UriInterface
	{
		$self = clone $this;
		$self->fragment = $fragment;

		return $self;
	}

	/**
	 * @inheritdoc
	 */
	public function __toString(): string
	{
		$scheme = !empty($this->scheme) ? $this->scheme . '://' : '';
		$host = rtrim($this->getAuthority(), '/');
		$uri = '/' . trim($this->path, '/');
		$query = !empty($this->query) ? '?' . $this->query : '';
		$fragment = !empty($this->fragment) ? '#' . $this->fragment : $this->fragment;

		return $scheme . $host . $uri . $query . $fragment;
	}
}