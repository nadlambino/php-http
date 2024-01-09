<?php

declare(strict_types=1);

namespace Inspira\Http;

use InvalidArgumentException;

/**
 * Represents the response body, extending the abstract Body class.
 */
class ResponseBody extends Body
{
	/**
	 * Construct a new ResponseBody object.
	 *
	 * @param mixed $stream The stream resource for the response body.
	 *
	 * @throws InvalidArgumentException If an invalid stream resource is provided.
	 */
	public function __construct(protected mixed $stream = null)
	{
		if (!empty($this->stream) && !is_resource($this->stream)) {
			throw new InvalidArgumentException("Invalid stream resource provided");
		}

		if (empty($this->stream)) {
			$this->stream = fopen('php://output', 'r+w+');
		}
	}
}
