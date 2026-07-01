<?php

declare(strict_types=1);

namespace Rokke\Runtime\Contracts;

use Rokke\Contracts\Module\ModuleBuilderInterface;
use Rokke\Contracts\Module\ModuleInterface;

/**
 * Collects module registrations and delegates capability declarations
 * to the ApplicationGraph during the build phase.
 */
interface ModuleSystemInterface
{
	public function register(ModuleInterface $module): void;

	/** @return list<ModuleInterface> */
	public function all(): array;

	public function buildAll(ModuleBuilderInterface $builder): void;
}
