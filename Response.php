<?php

declare(strict_types=1);

namespace Inspira\Http;

use Inspira\Contracts\Arrayable;
use Iterator;
use Psr\Http\Message\ResponseInterface;

/**
 * Represents an HTTP response and implements the ResponseInterface.
 */
class Response extends Message implements ResponseInterface
{
	use Clonable;

	/**
	 * Constructor for the Response class.
	 *
	 * @param mixed $content The response content.
	 * @param int $status The HTTP response status code.
	 * @param mixed $reason The reason phrase associated with the status code.
	 * @param array $headers An array of response headers.
	 * @param string $version The HTTP protocol version.
	 * @param ResponseBody|null $body The response body.
	 */
	public function __construct(
		protected mixed  $content = '',
		protected int    $status = 200,
		protected string $reason = '',
		protected array  $headers = [],
		protected string $version = '',
		?ResponseBody    $body = null,
	)
	{
		$this->body = $body ?? new ResponseBody();
	}

	/**
	 * {@inheritdoc}
	 */
	public function getStatusCode(): int
	{
		return $this->status ??= Status::OK->value;
	}

	/**
	 * {@inheritdoc}
	 */
	public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface
	{
		$self = clone $this;
		$self->status = $code;
		$self->reason = $reasonPhrase;

		return $self;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getReasonPhrase(): string
	{
		return $this->reason ??= '';
	}

	/**
	 * Get the response content.
	 *
	 * @return string The response content.
	 */
	public function getContent(): string
	{
		return $this->content;
	}

	/**
	 * Set the response content.
	 *
	 * @param string $content The content to set.
	 * @return self The modified response object.
	 */
	public function withContent(string $content): self
	{
		$self = clone $this;
		$self->content = $content;

		return $self;
	}

	/**
	 * Remove the response content.
	 *
	 * @return self The modified response object.
	 */
	public function withoutContent(): self
	{
		$self = clone $this;
		$self->content = '';

		return $self;
	}

	/**
	 * Redirect the response to a specified URL.
	 *
	 * @param string $url The URL to redirect to.
	 * @param bool $permanent Whether the redirect is permanent.
	 */
	public function redirect(string $url, bool $permanent = false): void
	{
		$self = clone $this;
		$self
			->withStatus($permanent ? Status::PERMANENT_REDIRECT->value : Status::TEMPORARY_REDIRECT->value)
			->withAddedHeader('Location', $url);
	}

	/**
	 * Set the response content as JSON.
	 *
	 * @param array|Iterator|Arrayable $data The data to encode as JSON.
	 * @return self The modified response object.
	 */
	public function json(array|Iterator|Arrayable $data): self
	{
		$data = match (true) {
			is_array($data) => $data,
			$data instanceof Iterator => iterator_to_array($data),
			$data instanceof Arrayable => $data->toArray(),
		};

		$self = clone $this;
		return $self
			->withHeader('Content-Type', 'application/json')
			->withContent((string)json_encode($data));
	}
}
