<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

final class FactoryRepository
{
	private int $nextId = 0;

	/** @var array<int, CompiledFactory> */
	private array $byId = [];

	/** @var array<string, int> */
	private array $aliasToId = [];

	/** @var array<string, ServiceDescriptor> */
	private array $aliasMap = [];

	/** @var array<string, true> */
	private array $compiling = [];

	private function __construct(private readonly FactoryCompiler $compiler) {}

	public static function empty(): self
	{
		return new self(new FactoryCompiler());
	}

	/** @param list<CompiledFactory> $descriptors */
	public static function fromDescriptors(array $descriptors): self
	{
		$repo = new self(new FactoryCompiler());

		foreach ($descriptors as $id => $factory) {
			$repo->byId[$id]                           = $factory;
			$repo->aliasToId[$factory->implementation] = $id;
		}

		return $repo;
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
	private function resolve(string $alias): int
	{
		if (isset($this->aliasToId[$alias])) {
			return $this->aliasToId[$alias];
		}

		if (isset($this->compiling[$alias])) {
			throw new \RuntimeException(
				"Circular dependency detected while compiling '{$alias}'.",
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
		$id         = $this->nextId++;

		$this->byId[$id] = $factory;

		foreach ($descriptor->aliases as $resolvedAlias) {
			$this->aliasToId[$resolvedAlias] = $id;
			unset($this->compiling[$resolvedAlias]);
		}

		return $id;
	}

	public function create(int $id): object
	{
		$factory = $this->byId[$id]
			?? throw new \RuntimeException("No factory registered for ID {$id}.");

		$args = array_map($this->create(...), $factory->dependencies);

		/** @var object */
		return new $factory->implementation(...$args);
	}

	public function id(string $alias): ?int
	{
		return $this->aliasToId[$alias] ?? null;
	}

	public function get(string $alias): ?CompiledFactory
	{
		$id = $this->aliasToId[$alias] ?? null;

		return $id !== null ? $this->byId[$id] : null;
	}

	public function has(string $alias): bool
	{
		return isset($this->aliasToId[$alias]);
	}

	/** @return list<CompiledFactory> ordered by int ID */
	public function descriptors(): array
	{
		ksort($this->byId);
		return array_values($this->byId);
	}
}
