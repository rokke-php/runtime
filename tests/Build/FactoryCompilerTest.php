<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Build;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Build\CompiledFactory;
use Rokke\Runtime\Build\FactoryCompiler;
use Rokke\Runtime\Build\ServiceDescriptor;

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

	/** @return callable(class-string): never */
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
		$this->assertSame(NoDepService::class, $factory->implementation);
		$this->assertSame([], $factory->dependencies);
	}

	public function testResolvesOneDependencyAndRecordsItsId(): void
	{
		$factory = $this->compiler->compile(
			$this->descriptorFor(OneDependencyService::class),
			static fn (string $t): int => match ($t) {
				NoDepService::class => 7,
				default             => throw new \RuntimeException("Unexpected: {$t}"),
			},
		);

		$this->assertSame(OneDependencyService::class, $factory->implementation);
		$this->assertSame([7], $factory->dependencies);
	}

	public function testResolvesTwoDependenciesInOrder(): void
	{
		$factory = $this->compiler->compile(
			$this->descriptorFor(TwoDependencyService::class),
			static fn (string $t): int => match ($t) {
				NoDepService::class        => 0,
				OneDependencyService::class => 1,
				default                    => throw new \RuntimeException("Unexpected: {$t}"),
			},
		);

		$this->assertSame([0, 1], $factory->dependencies);
	}

	public function testResolverIsCalledOncePerParameterType(): void
	{
		$calls   = [];
		$resolver = static function (string $type) use (&$calls): int {
			$calls[] = $type;
			return 0;
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

	public function testThrowsWhenResolverThrows(): void
	{
		$this->expectException(\RuntimeException::class);

		$this->compiler->compile(
			$this->descriptorFor(OneDependencyService::class),
			static fn (string $t): never => throw new \RuntimeException("No factory for {$t}"),
		);
	}
}
