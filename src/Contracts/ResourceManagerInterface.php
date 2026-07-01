<?php

declare(strict_types=1);

namespace Rokke\Runtime\Contracts;

/**
 * Manages the lifecycle of all registered resources across the platform.
 * Delegates to each ResourceInterface in registration order on boot and shutdown.
 */
interface ResourceManagerInterface
{
	public function register(ResourceInterface $resource): void;

	public function bootAll(): void;

	public function healthCheck(): bool;

	public function shutdownAll(): void;
}
