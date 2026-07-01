<?php

declare(strict_types=1);

namespace Rokke\Runtime\Contracts;

/**
 * Information about the Runtime environment.
 */
interface EnvironmentInterface
{
	public function isProduction(): bool;

	public function isDevelopment(): bool;

	public function workerId(): ?int;

	public function processId(): int;

	public function hostname(): string;

	public function runtimeName(): string;
}
