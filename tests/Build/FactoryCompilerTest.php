<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Build;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Build\CompiledFactory;
use Rokke\Runtime\Build\FactoryCompiler;
use Rokke\Runtime\Build\ServiceDescriptor;

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class NoDepService {}

final class OneDependencyService
{
	public function __construct(public readonly NoDepService $dep) {}
}

final class TwoDependencyService
{
	public function __construct(
		public readonly NoDepService $first,
		public readonly OneDependencyService $second,
	) {}
}

final class TypelessParamService
{
	// @phpstan-ignore-next-line
	public function __construct($dep) {}
}

final class PrimitiveParamService
{
	public function __construct(public readonly string $value) {}
}

// ── Tests ─────────────────────────────────────────────────────────────────────

final class FactoryCompilerTest extends TestCase
{
	private FactoryCompiler $compiler;

	protected function setUp(): void
	{
		$this->compiler = new FactoryCompiler();
	}

	/** @param class-string $class */
	private function descriptorFor(string $class): ServiceDescriptor
	{
		return new ServiceDescriptor($class, $class, [$class]);
	}

	private function factoryFor(object $instance): CompiledFactory
	{
		return new CompiledFactory(static fn (): object => $instance);
	}

	/** @return callable(class-string): CompiledFactory */
	private function noResolver(): callable
	{
		return static function (string $type): never {
			throw new \RuntimeException("Resolver should not be called, but was called with: {$type}");
		};
	}

	public function testCompilesClassWithNoConstructor(): void
	{
		$factory = $this->compiler->compile($this->descriptorFor(NoDepService::class), $this->noResolver());

		$this->assertInstanceOf(CompiledFactory::class, $factory);
	}

	public function testCreatesInstanceOfCorrectTypeWhenNoDeps(): void
	{
		$factory = $this->compiler->compile($this->descriptorFor(NoDepService::class), $this->noResolver());

		$this->assertInstanceOf(NoDepService::class, $factory->create());
	}

	public function testEachCallToCreateReturnsNewInstance(): void
	{
		$factory = $this->compiler->compile($this->descriptorFor(NoDepService::class), $this->noResolver());

		$a = $factory->create();
		$b = $factory->create();

		$this->assertNotSame($a, $b);
	}

	public function testResolvesOneDependency(): void
	{
		$depInstance = new NoDepService();
		$depFactory  = $this->factoryFor($depInstance);

		$resolver = static function (string $type) use ($depFactory): CompiledFactory {
			if ($type === NoDepService::class) {
				return $depFactory;
			}

			throw new \RuntimeException("No factory for {$type}");
		};

		$factory = $this->compiler->compile(
			$this->descriptorFor(OneDependencyService::class),
			$resolver,
		);

		/** @var OneDependencyService $instance */
		$instance = $factory->create();
		$this->assertInstanceOf(OneDependencyService::class, $instance);
		$this->assertSame($depInstance, $instance->dep);
	}

	public function testResolvesTwoDependenciesInOrder(): void
	{
		$noDep    = new NoDepService();
		$oneDep   = new OneDependencyService($noDep);

		$resolver = function (string $type) use ($noDep, $oneDep): CompiledFactory {
			return match ($type) {
				NoDepService::class        => $this->factoryFor($noDep),
				OneDependencyService::class => $this->factoryFor($oneDep),
				default                    => throw new \RuntimeException("No factory for {$type}"),
			};
		};

		$factory = $this->compiler->compile(
			$this->descriptorFor(TwoDependencyService::class),
			$resolver,
		);

		/** @var TwoDependencyService $instance */
		$instance = $factory->create();
		$this->assertSame($noDep, $instance->first);
		$this->assertSame($oneDep, $instance->second);
	}

	public function testResolverIsCalledOncePerUniqueParameterType(): void
	{
		$calls = [];

		$resolver = function (string $type) use (&$calls): CompiledFactory {
			$calls[] = $type;

			return $this->factoryFor(new NoDepService());
		};

		$this->compiler->compile($this->descriptorFor(OneDependencyService::class), $resolver);

		$this->assertSame([NoDepService::class], $calls);
	}

	public function testThrowsAtCompileTimeWhenParameterHasNoType(): void
	{
		$this->expectException(\RuntimeException::class);

		$this->compiler->compile($this->descriptorFor(TypelessParamService::class), $this->noResolver());
	}

	public function testThrowsAtCompileTimeWhenParameterHasBuiltinType(): void
	{
		$this->expectException(\RuntimeException::class);

		$this->compiler->compile($this->descriptorFor(PrimitiveParamService::class), $this->noResolver());
	}

	public function testThrowsAtCompileTimeWhenResolverThrowsForMissingDependency(): void
	{
		$resolver = static function (string $type): never {
			throw new \RuntimeException("No factory for {$type}");
		};

		$this->expectException(\RuntimeException::class);

		$this->compiler->compile($this->descriptorFor(OneDependencyService::class), $resolver);
	}
}
