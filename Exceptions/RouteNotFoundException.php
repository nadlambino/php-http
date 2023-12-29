<?php

declare(strict_types=1);

namespace Inspira\Http\Exceptions;

use Exception;
use Inspira\Http\Status;

class RouteNotFoundException extends Exception
{
	public function __construct()
	{
		parent::__construct('Route not found', Status::NOT_FOUND->value);
	}
}
