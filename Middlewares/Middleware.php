<?php

namespace Inspira\Http\Middlewares;

use Psr\Http\Server\MiddlewareInterface;

/**
 * The Middleware abstract class provides a base class for middleware implementations.
 *
 * Middleware classes that extend this abstract class should implement the
 * MiddlewareInterface. This class includes a property indicating whether
 * the middleware is global, meaning it should be applied to all routes.
 */
abstract class Middleware implements MiddlewareInterface
{
	/**
	 * Indicates whether the middleware is global and should be applied to all routes.
	 *
	 * @var bool
	 */
	public bool $global = false;
}
