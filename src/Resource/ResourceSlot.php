<?php

declare(strict_types=1);

namespace Rokke\Runtime\Resource;

/**
 * Internal envelope stored inside the pool's Channel.
 * Carries the resource alongside its creation timestamp for age-based eviction.
 *
 * @internal Used exclusively by ResourcePool.
 */
final class ResourceSlot
{
	public function __construct(
		public readonly mixed $resource,
		public readonly float $createdAt,
	) {}
}
