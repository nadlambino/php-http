<?php

declare(strict_types=1);

namespace Inspira\Http\Exceptions;

use Exception;
use Inspira\Http\Status;

class MethodNotAllowedException extends Exception
{
	public function __construct()
	{
		parent::__construct("Method not allowed", Status::METHOD_NOT_ALLOWED->value);
	}
}
