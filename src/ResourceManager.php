<?php

declare(strict_types=1);

namespace Rokke\Runtime;

use Rokke\Runtime\Contracts\PoolManagerInterface;
use Rokke\Runtime\Resource\PoolConfig;
use Rokke\Runtime\Resource\PoolStats;
use RuntimeException;

final class ResourceManager implements PoolManagerInterface
{
	/** @var array<string, ResourcePool> */
	private array $pools = [];

	public function register(PoolConfig $config, callable $factory): void
	{
		if (isset($this->pools[$config->name])) {
			throw new RuntimeException("The pool [{$config->name}] is already registered.");
		}

		$this->pools[$config->name] = new ResourcePool($config, $factory);
	}

	public function acquire(string $poolName): mixed
	{
		return $this->getPool($poolName)->get();
	}

	public function release(string $poolName, mixed $resource): void
	{
		$this->getPool($poolName)->release($resource);
	}

	public function stats(string $poolName): PoolStats
	{
		return $this->getPool($poolName)->stats();
	}

	/** @return array<string, PoolStats> */
	public function allStats(): array
	{
		$stats = [];

		foreach ($this->pools as $name => $pool) {
			$stats[$name] = $pool->stats();
		}

		return $stats;
	}

	public function drain(string $poolName, float $timeout = 30.0): void
	{
		$this->getPool($poolName)->drain($timeout);
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
