<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Build;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Build\CompiledFactory;
use Rokke\Runtime\Build\FactoryCompiler;
use Rokke\Runtime\Build\FactoryRepository;
use Rokke\Runtime\Build\ServiceDescriptor;

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class RepoNoDeps {}

final class RepoOneDep
{
	public function __construct(public readonly RepoNoDeps $dep) {}
}

final class RepoTwoDeps
{
	public function __construct(
		public readonly RepoNoDeps $first,
		public readonly RepoOneDep $second,
	) {}
}

interface RepoContract {}
final class RepoImpl implements RepoContract {}

// ── Tests ─────────────────────────────────────────────────────────────────────

final class FactoryRepositoryTest extends TestCase
{
	private FactoryCompiler $compiler;

	protected function setUp(): void
	{
		$this->compiler = new FactoryCompiler();
	}

	/** @param class-string $class */
	private function selfDescriptor(string $class): ServiceDescriptor
	{
		return new ServiceDescriptor($class, $class, [$class]);
	}

	public function testBuildWithNoDescriptorsProducesEmptyRepository(): void
	{
		$repo = FactoryRepository::build([], $this->compiler);

		$this->assertNull($repo->get(RepoNoDeps::class));
	}

	public function testGetReturnsNullForUnregisteredAlias(): void
	{
		$repo = FactoryRepository::build([$this->selfDescriptor(RepoNoDeps::class)], $this->compiler);

		$this->assertNull($repo->get(RepoOneDep::class));
	}

	public function testHasReturnsFalseForUnregisteredAlias(): void
	{
		$repo = FactoryRepository::build([], $this->compiler);

		$this->assertFalse($repo->has(RepoNoDeps::class));
	}

	public function testHasReturnsTrueForRegisteredAlias(): void
	{
		$repo = FactoryRepository::build([$this->selfDescriptor(RepoNoDeps::class)], $this->compiler);

		$this->assertTrue($repo->has(RepoNoDeps::class));
	}

	public function testGetReturnsCompiledFactoryForRegisteredClass(): void
	{
		$repo = FactoryRepository::build([$this->selfDescriptor(RepoNoDeps::class)], $this->compiler);

		$this->assertInstanceOf(CompiledFactory::class, $repo->get(RepoNoDeps::class));
	}

	public function testFactoryCreatesCorrectType(): void
	{
		$repo    = FactoryRepository::build([$this->selfDescriptor(RepoNoDeps::class)], $this->compiler);
		$factory = $repo->get(RepoNoDeps::class);

		$this->assertInstanceOf(RepoNoDeps::class, $factory?->create());
	}

	public function testBothAliasesReturnSameFactoryInstance(): void
	{
		$descriptor = new ServiceDescriptor(
			RepoContract::class,
			RepoImpl::class,
			[RepoContract::class, RepoImpl::class],
		);

		$repo = FactoryRepository::build([$descriptor], $this->compiler);

		$this->assertSame($repo->get(RepoContract::class), $repo->get(RepoImpl::class));
	}

	public function testResolvesDependenciesTransitively(): void
	{
		$descriptors = [
			$this->selfDescriptor(RepoNoDeps::class),
			$this->selfDescriptor(RepoOneDep::class),
		];

		$repo    = FactoryRepository::build($descriptors, $this->compiler);
		$factory = $repo->get(RepoOneDep::class);

		$this->assertInstanceOf(RepoOneDep::class, $factory?->create());
	}

	public function testResolvesTransitiveDependencies(): void
	{
		$descriptors = [
			$this->selfDescriptor(RepoNoDeps::class),
			$this->selfDescriptor(RepoOneDep::class),
			$this->selfDescriptor(RepoTwoDeps::class),
		];

		$repo    = FactoryRepository::build($descriptors, $this->compiler);
		$factory = $repo->get(RepoTwoDeps::class);

		$this->assertInstanceOf(RepoTwoDeps::class, $factory?->create());
	}

	public function testRegistrationOrderDoesNotMatter(): void
	{
		$descriptors = [
			$this->selfDescriptor(RepoTwoDeps::class),
			$this->selfDescriptor(RepoOneDep::class),
			$this->selfDescriptor(RepoNoDeps::class),
		];

		$repo    = FactoryRepository::build($descriptors, $this->compiler);
		$factory = $repo->get(RepoTwoDeps::class);

		$this->assertInstanceOf(RepoTwoDeps::class, $factory?->create());
	}

	public function testBuildThrowsWhenDependencyIsNotRegistered(): void
	{
		$this->expectException(\RuntimeException::class);

		FactoryRepository::build([$this->selfDescriptor(RepoOneDep::class)], $this->compiler);
	}
}
