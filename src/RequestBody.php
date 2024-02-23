<?php

declare(strict_types=1);

namespace Inspira\Http;

use InvalidArgumentException;

/**
 * Represents the request body, extending the abstract Body class.
 */
class RequestBody extends Body
{
	/**
	 * Construct a new RequestBody object.
	 *
	 * @param mixed $stream The stream resource for the request body.
	 *
	 * @throws InvalidArgumentException If an invalid stream resource is provided.
	 */
	public function __construct(protected mixed $stream = null)
	{
		if (!empty($this->stream) && !is_resource($this->stream)) {
			throw new InvalidArgumentException("Invalid stream resource provided");
		}

		if (empty($this->stream)) {
			$this->stream = fopen('php://input', 'r+');
		}
	}
}
