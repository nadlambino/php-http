<?php

namespace Inspira\Http\Middlewares;

use Psr\Http\Server\MiddlewareInterface;

abstract class Middleware implements MiddlewareInterface
{
	public bool $global = false;
}
