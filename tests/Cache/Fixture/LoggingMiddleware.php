<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Cache\Fixture;

use Rokke\Runtime\Contracts\MiddlewareInterface;
use Rokke\Runtime\Contracts\OperationContextInterface;
use Rokke\Runtime\Contracts\OperationInterface;

final class LoggingMiddleware implements MiddlewareInterface
{
	public static bool $invoked = false;

	public function handle(OperationInterface $op, OperationContextInterface $ctx, callable $next): mixed
	{
		self::$invoked = true;

		return '[mw]' . $next();
	}
}
