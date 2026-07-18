<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Build;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Build\CompiledFactory;
use Rokke\Runtime\Build\FactoryCompiler;
use Rokke\Runtime\Build\FactoryRepository;
use Rokke\Runtime\Build\ServiceDescriptor;

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

	// ── id / has / get ───────────────────────────────────────────────────────

	public function testBuildWithNoDescriptorsProducesEmptyRepository(): void
	{
		$repo = FactoryRepository::build([], $this->compiler);

		$this->assertNull($repo->id(RepoNoDeps::class));
	}

	public function testIdReturnsNullForUnregisteredAlias(): void
	{
		$repo = FactoryRepository::build([$this->selfDescriptor(RepoNoDeps::class)], $this->compiler);

		$this->assertNull($repo->id(RepoOneDep::class));
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

	public function testIdReturnsIntForRegisteredClass(): void
	{
		$repo = FactoryRepository::build([$this->selfDescriptor(RepoNoDeps::class)], $this->compiler);

		$this->assertIsInt($repo->id(RepoNoDeps::class));
	}

	public function testGetReturnsCompiledFactoryDescriptor(): void
	{
		$repo = FactoryRepository::build([$this->selfDescriptor(RepoNoDeps::class)], $this->compiler);

		$factory = $repo->get(RepoNoDeps::class);
		$this->assertInstanceOf(CompiledFactory::class, $factory);
		$this->assertSame(RepoNoDeps::class, $factory->implementation);
	}

	// ── create ───────────────────────────────────────────────────────────────

	public function testCreateInstantiatesCorrectType(): void
	{
		$repo = FactoryRepository::build([$this->selfDescriptor(RepoNoDeps::class)], $this->compiler);
		$id   = $repo->id(RepoNoDeps::class);

		$this->assertNotNull($id);
		$this->assertInstanceOf(RepoNoDeps::class, $repo->create($id));
	}

	public function testCreateProducesNewInstanceEachCall(): void
	{
		$repo = FactoryRepository::build([$this->selfDescriptor(RepoNoDeps::class)], $this->compiler);
		$id   = $repo->id(RepoNoDeps::class);
		$this->assertNotNull($id);

		$this->assertNotSame($repo->create($id), $repo->create($id));
	}

	public function testCreateResolvesDependenciesTransitively(): void
	{
		$repo = FactoryRepository::build([
			$this->selfDescriptor(RepoNoDeps::class),
			$this->selfDescriptor(RepoOneDep::class),
		], $this->compiler);

		$id = $repo->id(RepoOneDep::class);
		$this->assertNotNull($id);

		$instance = $repo->create($id);
		$this->assertInstanceOf(RepoOneDep::class, $instance);
		$this->assertInstanceOf(RepoNoDeps::class, $instance->dep);
	}

	public function testCreateThrowsForUnknownId(): void
	{
		$repo = FactoryRepository::build([], $this->compiler);

		$this->expectException(\RuntimeException::class);
		$repo->create(99);
	}

	// ── both aliases share same ID ───────────────────────────────────────────

	public function testBothAliasesReturnSameId(): void
	{
		$descriptor = new ServiceDescriptor(
			RepoContract::class,
			RepoImpl::class,
			[RepoContract::class, RepoImpl::class],
		);

		$repo = FactoryRepository::build([$descriptor], $this->compiler);

		$this->assertSame($repo->id(RepoContract::class), $repo->id(RepoImpl::class));
	}

	// ── descriptors / fromDescriptors ────────────────────────────────────────

	public function testDescriptorsReturnsOrderedList(): void
	{
		$repo = FactoryRepository::build([
			$this->selfDescriptor(RepoNoDeps::class),
			$this->selfDescriptor(RepoOneDep::class),
		], $this->compiler);

		$descs = $repo->descriptors();
		$this->assertCount(2, $descs);
		$this->assertContainsOnlyInstancesOf(CompiledFactory::class, $descs);
	}

	public function testFromDescriptorsRoundTrip(): void
	{
		$original = FactoryRepository::build([
			$this->selfDescriptor(RepoNoDeps::class),
			$this->selfDescriptor(RepoOneDep::class),
		], $this->compiler);

		$loaded = FactoryRepository::fromDescriptors($original->descriptors());

		$id = $loaded->id(RepoOneDep::class);
		$this->assertNotNull($id);
		$this->assertInstanceOf(RepoOneDep::class, $loaded->create($id));
	}

	public function testRegistrationOrderDoesNotMatter(): void
	{
		$repo = FactoryRepository::build([
			$this->selfDescriptor(RepoTwoDeps::class),
			$this->selfDescriptor(RepoOneDep::class),
			$this->selfDescriptor(RepoNoDeps::class),
		], $this->compiler);

		$id = $repo->id(RepoTwoDeps::class);
		$this->assertNotNull($id);
		$this->assertInstanceOf(RepoTwoDeps::class, $repo->create($id));
	}

	public function testBuildThrowsWhenDependencyIsNotRegistered(): void
	{
		$this->expectException(\RuntimeException::class);

		FactoryRepository::build([$this->selfDescriptor(RepoOneDep::class)], $this->compiler);
	}

	public function testFromDescriptorsPreservesInterfaceAliases(): void
	{
		$descriptor = new ServiceDescriptor(
			RepoContract::class,
			RepoImpl::class,
			[RepoContract::class, RepoImpl::class],
		);

		$original = FactoryRepository::build([$descriptor], $this->compiler);
		$loaded   = FactoryRepository::fromDescriptors($original->descriptors());

		$this->assertNotNull($loaded->id(RepoContract::class), 'Interface alias must survive round-trip');
		$this->assertNotNull($loaded->id(RepoImpl::class), 'Implementation alias must survive round-trip');
		$this->assertSame($loaded->id(RepoContract::class), $loaded->id(RepoImpl::class));
	}
}
