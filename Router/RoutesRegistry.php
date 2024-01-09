<?php

declare(strict_types=1);

namespace Inspira\Http\Router;

/**
 * The RoutesRegistry class for registering routes.
 */
class RoutesRegistry
{
	/**
	 * Register routes from the specified file using the given router.
	 *
	 * @param string $routes The path to the routes file.
	 * @param Router $router The router instance.
	 * @return void
	 */
	public static function register(string $routes, Router $router): void
	{
		require $routes;
	}
}
