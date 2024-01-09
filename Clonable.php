<?php

declare(strict_types=1);

namespace Inspira\Http;

use Inspira\Container\Container;

/**
 * The Clonable trait provides a custom cloning behavior for objects.
 *
 * When a class uses this trait and an instance of that class is cloned, the __clone method
 * will be automatically called. In this case, the __clone method sets the cloned object as
 * a resolved instance in the Container class. The Container class is often used for managing
 * dependency injection and resolving instances of classes.
 */
trait Clonable
{
	/**
	 * Handle the cloning behavior for the object.
	 *
	 * When an object utilizing this trait is cloned, this method is automatically invoked.
	 * It sets the cloned object as a resolved instance in the Container class if available.
	 *
	 * @return void
	 */
	public function __clone(): void
	{
		// Set the cloned object as resolved in the Container, if available.
		Container::getInstance()?->setResolved(__CLASS__, $this);
	}
}
