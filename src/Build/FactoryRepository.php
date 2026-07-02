<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

final class FactoryRepository
{
	/** @var array<string, CompiledFactory> */
	private array $factories = [];

	/** @var array<string, ServiceDescriptor> */
	private array $aliasMap = [];

	/** @var array<string, true> */
	private array $compiling = [];

	private function __construct(private readonly FactoryCompiler $compiler) {}

	public static function empty(): self
	{
		return new self(new FactoryCompiler());
	}

	/** @param list<ServiceDescriptor> $descriptors */
	public static function build(array $descriptors, FactoryCompiler $compiler): self
	{
		$repo = new self($compiler);

		foreach ($descriptors as $descriptor) {
			foreach ($descriptor->aliases as $alias) {
				$repo->aliasMap[$alias] = $descriptor;
			}
		}

		foreach ($descriptors as $descriptor) {
			$repo->resolve($descriptor->contract);
		}

		return $repo;
	}

	/** @param class-string $alias */
	private function resolve(string $alias): CompiledFactory
	{
		if (isset($this->factories[$alias])) {
			return $this->factories[$alias];
		}

		if (isset($this->compiling[$alias])) {
			throw new \RuntimeException(
				"Circular dependency detected while compiling '{$alias}'. " .
				'Check your service registrations for circular constructor dependencies.',
			);
		}

		if (!isset($this->aliasMap[$alias])) {
			throw new \RuntimeException(
				"No service registered for type '{$alias}'. " .
				'Register it via $builder->service() before using it as a dependency.',
			);
		}

		$this->compiling[$alias] = true;

		$descriptor = $this->aliasMap[$alias];
		$factory    = $this->compiler->compile($descriptor, $this->resolve(...));

		foreach ($descriptor->aliases as $resolvedAlias) {
			$this->factories[$resolvedAlias] = $factory;
			unset($this->compiling[$resolvedAlias]);
		}

		return $factory;
	}

	public function get(string $alias): ?CompiledFactory
	{
		return $this->factories[$alias] ?? null;
	}

	public function has(string $alias): bool
	{
		return isset($this->factories[$alias]);
	}
}
