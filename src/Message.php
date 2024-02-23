<?php

declare(strict_types=1);

namespace Inspira\Http;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Abstract class representing an HTTP message and implementing the MessageInterface.
 */
abstract class Message implements MessageInterface
{
	/**
	 * @var array The headers of the message.
	 */
	protected array $headers;

	/**
	 * @var string The HTTP protocol version.
	 */
	protected string $version;

	/**
	 * @var StreamInterface The body of the message.
	 */
	protected StreamInterface $body;

	/**
	 * {@inheritdoc}
	 */
	public function getProtocolVersion(): string
	{
		if (empty($this->version)) {
			$protocol = $_SERVER['SERVER_PROTOCOL'] ?? '1.1';
			$protocolArray = explode('/', $protocol);
			$this->version = end($protocolArray) ?? '1.1';
		}

		return $this->version;
	}

	/**
	 * {@inheritdoc}
	 */
	public function withProtocolVersion(string $version): MessageInterface
	{
		$self = clone $this;
		$self->version = $version;

		return $self;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getHeaders(): array
	{
		// If headers are set, return them; otherwise, parse them from $_SERVER.
		if ($this->headers) {
			return $this->headers;
		}

		foreach ($_SERVER as $key => $value) {
			if (str_starts_with($key, 'HTTP_')) {
				$name = str_replace('HTTP_', '', $key);
				$name = str_replace('_', ' ', $name);
				$name = ucwords(strtolower($name));
				$name = str_replace(' ', '-', $name);
				// Split value with comma except for those inside parentheses
				$parts = preg_split('/,\s*(?![^()]*\))/', $value);
				$this->headers[$name] = $parts;
			}
		}

		return $this->headers;
	}

	/**
	 * {@inheritdoc}
	 */
	public function hasHeader(string $name): bool
	{
		return isset($this->headers[$name]);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getHeader(string $name): array
	{
		$headers = array_change_key_case($this->headers, CASE_LOWER);
		$name = strtolower($name);

		return $headers[$name] ?? [];
	}

	/**
	 * {@inheritdoc}
	 */
	public function getHeaderLine(string $name): string
	{
		$header = $this->getHeader($name);

		return empty($header) ? '' : implode(",", $header);
	}

	/**
	 * {@inheritdoc}
	 */
	public function withHeader(string $name, $value): MessageInterface
	{
		$self = clone $this;
		$self->headers[$name] = [$value];

		return $self;
	}

	/**
	 * {@inheritdoc}
	 */
	public function withAddedHeader(string $name, $value): MessageInterface
	{
		$self = clone $this;
		$self->headers[$name][] = $value;

		return $self;
	}

	/**
	 * {@inheritdoc}
	 */
	public function withoutHeader(string $name): MessageInterface
	{
		$self = clone $this;
		unset($self->headers[$name]);

		return $self;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getBody(): StreamInterface
	{
		return $this->body;
	}

	/**
	 * {@inheritdoc}
	 */
	public function withBody(StreamInterface $body): MessageInterface
	{
		$self = clone $this;
		$self->body = $body;

		return $self;
	}
}
