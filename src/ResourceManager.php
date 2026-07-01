<?php

declare(strict_types=1);

namespace Rokke\Runtime;

use Rokke\Runtime\Contracts\PoolManagerInterface;
use RuntimeException;

final class ResourceManager implements PoolManagerInterface
{
	/** @var array<string, ResourcePool> */
	private array $pools = [];

	public function registerPool(string $name, callable $factory, int $min, int $max, int $timeout): void
	{
		if (isset($this->pools[$name])) {
			throw new RuntimeException("The pool [{$name}] is already registered.");
		}

		$this->pools[$name] = new ResourcePool($name, $factory, $min, $max, $timeout);
	}

	public function acquire(string $poolName): mixed
	{
		return $this->getPool($poolName)->get();
	}

	public function release(string $poolName, mixed $resource): void
	{
		$this->getPool($poolName)->release($resource);
	}

	/** @return array<string, mixed> */
	public function stats(?string $poolName = null): array
	{
		if ($poolName !== null) {
			return $this->getPool($poolName)->stats();
		}

		/** @var array<string, mixed> $stats */
		$stats = [];

		foreach ($this->pools as $name => $pool) {
			$stats[$name] = $pool->stats();
		}

		return $stats;
	}

	public function closeAll(): void
	{
		foreach ($this->pools as $pool) {
			$pool->close();
		}

		$this->pools = [];
	}

	private function getPool(string $name): ResourcePool
	{
		if (!isset($this->pools[$name])) {
			throw new RuntimeException("The pool [{$name}] does not exist or has not been registered.");
		}

		return $this->pools[$name];
	}
}
