<?php

declare(strict_types=1);

namespace Inspira\Http\Router;

class RoutesRegistry
{
	public static function register(string $routes, Router $router): void
	{
		require $routes;
	}
}
