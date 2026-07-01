<?php

declare(strict_types=1);

namespace Rokke\Runtime\Contracts;

/**
 * A managed external resource with a defined lifecycle.
 * Implementations include database pools, cache clients, and broker connections.
 */
interface ResourceInterface
{
	public function name(): string;

	public function boot(): void;

	public function health(): bool;

	public function shutdown(): void;
}
