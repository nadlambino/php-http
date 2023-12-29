<?php

declare(strict_types=1);

namespace Inspira\Http;

use Inspira\Contracts\Arrayable;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

class Response extends Message implements ResponseInterface
{
	use Clonable;

	public function __construct(
		ResponseBody $body,
		protected mixed $content = '',
		protected int $status = 200,
		protected $reason = '',
		protected array $headers = [],
		protected string $version = '',
	) {
		$this->body = $body;
	}

	/**
	 * Gets the response status code.
	 *
	 * The status code is a 3-digit integer result code of the server's attempt
	 * to understand and satisfy the request.
	 *
	 * @return int Status code.
	 */
	public function getStatusCode(): int
	{
		return $this->status ??= Status::OK->value;
	}

	/**
	 * Return an instance with the specified status code and, optionally, reason phrase.
	 *
	 * If no reason phrase is specified, implementations MAY choose to default
	 * to the RFC 7231 or IANA recommended reason phrase for the response's
	 * status code.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * updated status and reason phrase.
	 *
	 * @link http://tools.ietf.org/html/rfc7231#section-6
	 * @link http://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
	 * @param int $code The 3-digit integer result code to set.
	 * @param string $reasonPhrase The reason phrase to use with the
	 *     provided status code; if none is provided, implementations MAY
	 *     use the defaults as suggested in the HTTP specification.
	 * @return static
	 * @throws InvalidArgumentException For invalid status code arguments.
	 */
	public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface
	{
		$self = clone $this;
		$self->status = $code;
		$self->reason = $reasonPhrase;

		return $self;
	}

	/**
	 * Gets the response reason phrase associated with the status code.
	 *
	 * Because a reason phrase is not a required element in a response
	 * status line, the reason phrase value MAY be null. Implementations MAY
	 * choose to return the default RFC 7231 recommended reason phrase (or those
	 * listed in the IANA HTTP Status Code Registry) for the response's
	 * status code.
	 *
	 * @link http://tools.ietf.org/html/rfc7231#section-6
	 * @link http://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
	 * @return string Reason phrase; must return an empty string if none present.
	 */
	public function getReasonPhrase(): string
	{
		return $this->reason ??= '';
	}

	public function getContent()
	{
		return $this->content;
	}

	public function withContent(string $content): self
	{
		$self = clone $this;
		$self->content = $content;

		return $self;
	}

	public function withoutContent(): self
	{
		$self = clone $this;
		$self->content = '';

		return $self;
	}

	public function redirect(string $url, bool $permanent = false): void
	{
		$self = clone $this;
		$self
			->withStatus($permanent ? Status::PERMANENT_REDIRECT->value : Status::TEMPORARY_REDIRECT->value)
			->withAddedHeader('Location', $url);
	}

	public function json(array|\Iterator|Arrayable $data): self
	{
		$data = match (true) {
			is_array($data)            => $data,
			$data instanceof \Iterator => iterator_to_array($data),
			$data instanceof Arrayable => $data->toArray()
		};

		$self = clone $this;
		return $self
			->withHeader('Content-Type', 'application/json')
			->withContent((string) json_encode($data));
	}
}
