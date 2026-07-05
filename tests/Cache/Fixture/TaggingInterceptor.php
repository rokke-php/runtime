<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Cache\Fixture;

use Rokke\Runtime\Contracts\InvokerInterceptorInterface;
use Rokke\Runtime\Contracts\OperationContextInterface;
use Rokke\Runtime\Contracts\OperationInterface;

final class TaggingInterceptor implements InvokerInterceptorInterface
{
	public static bool $invoked = false;

	public function intercept(OperationInterface $op, OperationContextInterface $ctx, array $args, callable $next): mixed
	{
		self::$invoked = true;

		return '[ic]' . $next($args);
	}
}
