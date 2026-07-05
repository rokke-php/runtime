<?php

declare(strict_types=1);

namespace Rokke\Runtime\Resource;

/**
 * Immutable configuration for a managed resource pool.
 *
 * @see ResourceValidatorInterface  optional health check applied on every acquire
 */
final readonly class PoolConfig
{
	/**
	 * @param string                        $name           Unique pool identifier
	 * @param int                           $min            Resources pre-warmed on pool creation
	 * @param int                           $max            Hard upper bound on live resources
	 * @param int                           $acquireTimeout Milliseconds to wait when pool is full (0 = wait forever)
	 * @param int                           $maxAge         Seconds before a resource is considered stale and evicted
	 *                                                      (0 = no age limit)
	 * @param ResourceValidatorInterface|null $validator    Optional health check called before each reuse
	 */
	public function __construct(
		public string $name,
		public int $min = 0,
		public int $max = 10,
		public int $acquireTimeout = 3000,
		public int $maxAge = 0,
		public ?ResourceValidatorInterface $validator = null,
	) {}
}
