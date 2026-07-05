<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Cache\Fixture;

use Rokke\Runtime\Contracts\OperationContextInterface;

final class CacheableHandler
{
	public function __invoke(OperationContextInterface $ctx): string
	{
		return 'pong';
	}
}
