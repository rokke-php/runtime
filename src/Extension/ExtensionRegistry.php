<?php

declare(strict_types=1);

namespace Rokke\Runtime\Extension;

use Rokke\Contracts\Extension\ExtensionInterface;

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
}
