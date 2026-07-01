<?php

declare(strict_types=1);

namespace Rokke\Runtime\Contracts;

/**
 * Pure data model representing the full application state before compilation.
 * Used by the RuntimeBuilder and by static analysis tools (graph, inspect).
 */
interface ApplicationGraphInterface
{
	/** @return array<mixed> */
	public function getOperations(): array;

	/** @return array<mixed> */
	public function getServices(): array;

	/** @return array<mixed> */
	public function getModules(): array;
}
