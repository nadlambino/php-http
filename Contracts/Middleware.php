<?php

declare(strict_types=1);

namespace Inspira\Http\Contracts;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface Middleware
{
	public function process(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface;
}
