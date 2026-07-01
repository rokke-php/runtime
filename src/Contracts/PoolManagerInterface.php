<?php

declare(strict_types=1);

namespace Rokke\Runtime\Contracts;

interface PoolManagerInterface
{
	public function acquire(string $poolName): mixed;

	public function release(string $poolName, mixed $resource): void;

	public function registerPool(string $name, callable $factory, int $min, int $max, int $timeout): void;

	/** @return array<string, mixed> */
	public function stats(?string $poolName = null): array;

	public function closeAll(): void;
}
