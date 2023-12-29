<?php

declare(strict_types=1);

namespace Inspira\Http;

use Inspira\Container\Container;

trait Clonable
{
	public function __clone(): void
	{
		Container::getInstance()?->setResolved(__CLASS__, $this);
	}
}
