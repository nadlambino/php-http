<?php

declare(strict_types=1);

namespace Inspira\Http;

use InvalidArgumentException;

class RequestBody extends Body
{
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