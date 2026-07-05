<?php

declare(strict_types=1);

namespace Rokke\Runtime\Resource;

/**
 * Point-in-time snapshot of a resource pool's state and counters.
 */
final readonly class PoolStats
{
	public function __construct(
		public string $name,
		public int $max,
		public int $min,
		public int $currentTotal,
		public int $idle,
		public int $waitingCoroutines,
		public int $acquired,
		public int $created,
		public int $errors,
		public int $validationFails,
		public int $evicted,
	) {}

	/** Resources currently held by consumers (not yet released). */
	public function active(): int
	{
		return max(0, $this->currentTotal - $this->idle);
	}
}
