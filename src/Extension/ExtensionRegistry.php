<?php

declare(strict_types=1);

namespace Rokke\Runtime\Extension;

use Rokke\Contracts\Extension\ExtensionBuildInterface;
use Rokke\Contracts\Extension\ExtensionInterface;
use Rokke\Runtime\Build\ExtensionBuildPassInterface;

/**
 * Collects registered extensions and drives the build phase.
 * Registration order is preserved; extensions have no lifecycle methods.
 */
final class ExtensionRegistry
{
	/** @var list<ExtensionInterface> */
	private array $extensions = [];

	public function register(ExtensionInterface $extension): void
	{
		$this->extensions[] = $extension;
	}

	/** @return list<ExtensionInterface> */
	public function all(): array
	{
		return $this->extensions;
	}

	public function buildAll(ExtensionBuilder $builder): void
	{
		foreach ($this->extensions as $extension) {
			$extension->register($builder);
		}
	}

	/** @return list<ExtensionBuildPassInterface> */
	public function getBuildPasses(): array
	{
		$passes = [];

		foreach ($this->extensions as $extension) {
			if ($extension instanceof ExtensionBuildInterface) {
				foreach ($extension->buildPasses() as $pass) {
					$passes[] = $pass;
				}
			}
		}

		return $passes;
	}
}
