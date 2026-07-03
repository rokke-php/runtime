<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Compiled;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Build\CompiledFactory;
use Rokke\Runtime\Build\FactoryCompiler;
use Rokke\Runtime\Build\FactoryRepository;
use Rokke\Runtime\Build\ServiceDescriptor;
use Rokke\Runtime\Compiled\ArtifactRepository;
use Rokke\Runtime\Compiled\CompiledOperation;
use Rokke\Runtime\Compiled\CompiledRuntime;
use Rokke\Runtime\Compiled\OperationRepository;

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class CompiledRuntimeServiceFixture {}
final class CompiledRuntimeArtifactFixture {}

// ── Tests ─────────────────────────────────────────────────────────────────────

final class CompiledRuntimeTest extends TestCase
{
	// ── operations field ─────────────────────────────────────────────────────

	public function testOperationsDefaultsToEmptyRepositoryWhenNotProvided(): void
	{
		$runtime = new CompiledRuntime([], [], [], []);

		$this->assertInstanceOf(OperationRepository::class, $runtime->operations);
		$this->assertFalse($runtime->operations->has('any'));
	}

	public function testOperationsExposesPassedRepository(): void
	{
		$op   = new CompiledOperation('greet', 0, 0, 0, 0);
		$repo = OperationRepository::build([$op]);

		$runtime = new CompiledRuntime([], [], [], [], $repo);

		$this->assertSame($repo, $runtime->operations);
		$this->assertSame($op, $runtime->operations->find('greet'));
	}

	public function testMultipleOperationsAreIndependentlyAddressable(): void
	{
		$opA  = new CompiledOperation('a', 0, 0, 0, 0);
		$opB  = new CompiledOperation('b', 1, 1, 1, 1);
		$repo = OperationRepository::build([$opA, $opB]);

		$runtime = new CompiledRuntime([], [], [], [], $repo);

		$this->assertSame($opA, $runtime->operations->find('a'));
		$this->assertSame($opB, $runtime->operations->find('b'));
		$this->assertNull($runtime->operations->find('c'));
	}

	// ── handlers field ───────────────────────────────────────────────────────

	public function testHandlersAreAccessibleByIndex(): void
	{
		$handler = fn (): string => 'ok';
		$runtime = new CompiledRuntime([], [0 => $handler], [], []);

		$this->assertSame($handler, $runtime->handlers[0]);
	}

	// ── factories field ──────────────────────────────────────────────────────

	public function testFactoriesDefaultsToEmptyRepositoryWhenNotProvided(): void
	{
		$runtime = new CompiledRuntime([], [], [], []);

		$this->assertInstanceOf(FactoryRepository::class, $runtime->factories);
		$this->assertNull($runtime->getService(CompiledRuntimeServiceFixture::class));
	}

	public function testGetServiceReturnsNullForUnknownAlias(): void
	{
		$runtime = new CompiledRuntime([], [], [], []);

		$this->assertNull($runtime->getService(CompiledRuntimeServiceFixture::class));
	}

	public function testGetServiceDelegatesToFactoryRepository(): void
	{
		$descriptor = new ServiceDescriptor(
			CompiledRuntimeServiceFixture::class,
			CompiledRuntimeServiceFixture::class,
			[CompiledRuntimeServiceFixture::class],
		);
		$repo    = FactoryRepository::build([$descriptor], new FactoryCompiler());
		$runtime = new CompiledRuntime([], [], [], [], null, $repo);

		$factory = $runtime->getService(CompiledRuntimeServiceFixture::class);
		$this->assertInstanceOf(CompiledFactory::class, $factory);
		assert($factory instanceof CompiledFactory);
		$this->assertInstanceOf(CompiledRuntimeServiceFixture::class, $factory->create());
	}

	public function testExplicitFactoryRepositoryIsStoredOnFactoriesField(): void
	{
		$repo    = FactoryRepository::build([], new FactoryCompiler());
		$runtime = new CompiledRuntime([], [], [], [], null, $repo);

		$this->assertSame($repo, $runtime->factories);
	}

	// ── artifacts field ──────────────────────────────────────────────────────

	public function testArtifactsDefaultsToEmptyRepositoryWhenNotProvided(): void
	{
		$runtime = new CompiledRuntime([], [], [], []);

		$this->assertInstanceOf(ArtifactRepository::class, $runtime->artifacts);
		$this->assertFalse($runtime->artifacts->has(CompiledRuntimeArtifactFixture::class));
	}

	public function testArtifactsExposesPassedRepository(): void
	{
		$artifact = new CompiledRuntimeArtifactFixture();
		$repo     = ArtifactRepository::build([CompiledRuntimeArtifactFixture::class => $artifact]);
		$runtime  = new CompiledRuntime([], [], [], [], null, null, $repo);

		$this->assertSame($repo, $runtime->artifacts);
		$this->assertSame($artifact, $runtime->artifacts->get(CompiledRuntimeArtifactFixture::class));
	}
}
