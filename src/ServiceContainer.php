<?php

declare(strict_types=1);

namespace Rokke\Runtime;

use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use Rokke\Contracts\Container\ServiceContainerInterface;
use Rokke\Runtime\Contracts\ContextManagerInterface;
use Rokke\Runtime\Contracts\PoolManagerInterface;
use RuntimeException;

final class ServiceContainer implements ServiceContainerInterface
{
	/** @var array<string, array{lifetime: Lifetime, concrete: mixed}> */
	private array $bindings = [];

	/** @var array<string, mixed> */
	private array $instances = [];

	/** @var array<string, string> */
	private array $aliases = [];

	public function __construct(
		private readonly ?ContextManagerInterface $contextManager = null,
		private readonly ?PoolManagerInterface $resourceManager = null
	) {
		$this->instances[self::class]                     = $this;
		$this->instances[ServiceContainerInterface::class] = $this;
	}

	public function singleton(string $id, mixed $concrete = null): void
	{
		$this->bindings[$id] = ['lifetime' => Lifetime::Singleton, 'concrete' => $concrete ?? $id];
	}

	public function scoped(string $id, mixed $concrete = null): void
	{
		$this->bindings[$id] = ['lifetime' => Lifetime::Scoped, 'concrete' => $concrete ?? $id];
	}

	public function transient(string $id, mixed $concrete = null): void
	{
		$this->bindings[$id] = ['lifetime' => Lifetime::Transient, 'concrete' => $concrete ?? $id];
	}

	public function pooled(string $id, callable $factory, int $min, int $max): void
	{
		if ($this->resourceManager === null) {
			throw new RuntimeException('ResourceManager not configured in the container.');
		}

		$poolName = "pool_{$id}";
		$this->resourceManager->registerPool($poolName, $factory, $min, $max, 5000);
		$this->bindings[$id] = ['lifetime' => Lifetime::Pooled, 'concrete' => $poolName];
	}

	public function alias(string $alias, string $abstract): void
	{
		$this->aliases[$alias] = $abstract;
	}

	public function has(string $id): bool
	{
		$id = $this->resolveAlias($id);

		return isset($this->bindings[$id]) || isset($this->instances[$id]);
	}

	public function get(string $id): mixed
	{
		return $this->make($id);
	}

	/** @param array<string, mixed> $parameters */
	public function make(string $id, array $parameters = []): mixed
	{
		$id = $this->resolveAlias($id);

		if (isset($this->instances[$id])) {
			return $this->instances[$id];
		}

		$binding = $this->bindings[$id] ?? ['lifetime' => Lifetime::Transient, 'concrete' => $id];

		return match ($binding['lifetime']) {
			Lifetime::Singleton => $this->resolveSingleton($id, $binding['concrete'], $parameters),
			Lifetime::Scoped    => $this->resolveScoped($id, $binding['concrete'], $parameters),
			Lifetime::Transient => $this->resolve($binding['concrete'], $parameters),
			Lifetime::Pooled    => $this->resolvePooled($id, is_string($binding['concrete']) ? $binding['concrete'] : (string) $id),
		};
	}

	/** @param array<string, mixed> $parameters */
	private function resolveSingleton(string $id, mixed $concrete, array $parameters): mixed
	{
		$instance              = $this->resolve($concrete, $parameters);
		$this->instances[$id]  = $instance;

		return $instance;
	}

	/** @param array<string, mixed> $parameters */
	private function resolveScoped(string $id, mixed $concrete, array $parameters): mixed
	{
		if ($this->contextManager === null) {
			throw new RuntimeException("ContextManager not configured. Cannot resolve scoped service: {$id}");
		}

		$context    = $this->contextManager->current();
		$contextKey = "__scoped_{$id}";

		if ($context->has($contextKey)) {
			return $context->get($contextKey);
		}

		$instance = $this->resolve($concrete, $parameters);
		$context->set($contextKey, $instance);

		return $instance;
	}

	private function resolvePooled(string $id, string $poolName): mixed
	{
		if ($this->resourceManager === null) {
			throw new RuntimeException('ResourceManager not configured.');
		}

		$resource = $this->resourceManager->acquire($poolName);

		if ($this->contextManager !== null && $this->contextManager->isInCoroutine()) {
			$context         = $this->contextManager->current();
			$resourceManager = $this->resourceManager;
			$context->onDestroy(static function () use ($resourceManager, $poolName, $resource): void {
				$resourceManager->release($poolName, $resource);
			});
		}

		return $resource;
	}

	/** @param array<string, mixed> $parameters */
	private function resolve(mixed $concrete, array $parameters): mixed
	{
		if (is_callable($concrete)) {
			return $concrete($this, $parameters);
		}

		if (is_string($concrete) && (class_exists($concrete) || interface_exists($concrete))) {
			return $this->autowire($concrete, $parameters);
		}

		return $concrete;
	}

	/** @param array<string, mixed> $parameters */
	private function autowire(string $class, array $parameters): mixed
	{
		try {
			/** @var class-string $class */
			$reflector = new ReflectionClass($class);

			if (!$reflector->isInstantiable()) {
				throw new RuntimeException("The class [{$class}] is not instantiable.");
			}

			$constructor = $reflector->getConstructor();

			if ($constructor === null) {
				return new $class();
			}

			$dependencies = [];

			foreach ($constructor->getParameters() as $param) {
				$name = $param->getName();

				if (array_key_exists($name, $parameters)) {
					$dependencies[] = $parameters[$name];
					continue;
				}

				$type = $param->getType();

				if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
					$dependencies[] = $this->make($type->getName());
					continue;
				}

				if ($param->isDefaultValueAvailable()) {
					$dependencies[] = $param->getDefaultValue();
					continue;
				}

				throw new RuntimeException("Cannot resolve dependency [{$name}] for the class [{$class}]");
			}

			return $reflector->newInstanceArgs($dependencies);

		} catch (ReflectionException $e) {
			throw new RuntimeException("Error resolving class [{$class}]: " . $e->getMessage());
		}
	}

	private function resolveAlias(string $id): string
	{
		return $this->aliases[$id] ?? $id;
	}
}
