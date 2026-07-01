<?php

declare(strict_types=1);

namespace Rokke\Runtime\Contracts;

use Rokke\Contracts\Resources\ResourceProviderInterface;

interface PoolManagerInterface extends ResourceProviderInterface
{
	public function registerPool(string $name, callable $factory, int $min, int $max, int $timeout): void;

	/** @return array<string, mixed> */
	public function stats(?string $poolName = null): array;

	public function closeAll(): void;
}
