<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

/**
 * Implemented by extensions that have a compilation phase.
 * Called by DefaultRuntimeBuilder after the ApplicationModel is assembled.
 * Returns compiled configuration artifacts; does NOT mutate any shared state.
 *
 * Lives in rokke/runtime (not contracts) because its parameter type ApplicationModel
 * belongs to runtime. Placing it in contracts would create a circular dependency.
 */
interface ExtensionBuildPassInterface
{
	/** @return list<object> compiled configuration artifacts */
	public function process(ApplicationModel $model): array;
}
