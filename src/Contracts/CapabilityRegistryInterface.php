<?php

declare(strict_types=1);

namespace Rokke\Runtime\Contracts;

/**
 * Instead of using extension_loaded, queries for logical runtime capabilities.
 */
interface CapabilityRegistryInterface
{
	public function supports(string $capability): bool;

	public function register(string $capability): void;

	public function remove(string $capability): void;
}
