<?php

declare(strict_types=1);

namespace Rokke\Runtime\Module;

use Rokke\Contracts\Module\ModuleBuilderInterface;
use Rokke\Contracts\Module\ModuleInterface;
use Rokke\Runtime\Contracts\ModuleSystemInterface;

/**
 * Collects registered modules and drives the build phase.
 * Registration order is preserved; modules have no lifecycle methods.
 */
final class ModuleSystem implements ModuleSystemInterface
{
	/** @var list<ModuleInterface> */
	private array $modules = [];

	public function register(ModuleInterface $module): void
	{
		$this->modules[] = $module;
	}

	/** @return list<ModuleInterface> */
	public function all(): array
	{
		return $this->modules;
	}

	public function buildAll(ModuleBuilderInterface $builder): void
	{
		foreach ($this->modules as $module) {
			$module->register($builder);
		}
	}
}
