<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Compiled;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Build\CompiledFactory;
use Rokke\Runtime\Build\FactoryCompiler;
use Rokke\Runtime\Build\FactoryRepository;
use Rokke\Runtime\Build\ServiceDescriptor;
use Rokke\Runtime\Compiled\ArtifactRepository;
use Rokke\Runtime\Compiled\CompiledExecutionPipeline;
use Rokke\Runtime\Compiled\CompiledInterceptorPipeline;
use Rokke\Runtime\Compiled\CompiledOperation;
use Rokke\Runtime\Compiled\CompiledRuntime;
use Rokke\Runtime\Compiled\OperationRepository;

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class CompiledRuntimeServiceFixture {}
final class CompiledRuntimeArtifactFixture {}

// ── Tests ─────────────────────────────────────────────────────────────────────

final class CompiledRuntimeTest extends TestCase
{
	private function emptyPipeline(): CompiledExecutionPipeline
	{
		return new CompiledExecutionPipeline(
			handlers: [],
			argumentPlans: [],
			resultPlans: [],
			behaviorPipelines: [],
			validationPlans: [],
		);
	}

	private function makeRuntime(
		?OperationRepository $operations = null,
		?FactoryRepository $factories = null,
		?ArtifactRepository $artifacts = null,
	): CompiledRuntime {
		return new CompiledRuntime(
			executionPipeline: $this->emptyPipeline(),
			interceptorPipeline: CompiledInterceptorPipeline::empty(),
			operations: $operations,
			factories: $factories,
			artifacts: $artifacts,
		);
	}

	// ── Structure ────────────────────────────────────────────────────────────

	public function testExecutionPipelineIsStoredAndAccessible(): void
	{
		$pipeline = $this->emptyPipeline();
		$runtime  = new CompiledRuntime($pipeline, CompiledInterceptorPipeline::empty());

		$this->assertSame($pipeline, $runtime->executionPipeline);
	}

	public function testInterceptorPipelineIsStoredAndAccessible(): void
	{
		$interceptors = CompiledInterceptorPipeline::empty();
		$runtime      = new CompiledRuntime($this->emptyPipeline(), $interceptors);

		$this->assertSame($interceptors, $runtime->interceptorPipeline);
	}

	// ── operations ───────────────────────────────────────────────────────────

	public function testOperationsDefaultsToEmptyRepositoryWhenNotProvided(): void
	{
		$runtime = $this->makeRuntime();

		$this->assertInstanceOf(OperationRepository::class, $runtime->operations);
		$this->assertFalse($runtime->operations->has('any'));
	}

	public function testOperationsExposesPassedRepository(): void
	{
		$op   = new CompiledOperation('greet', 0, 0, 0, 0);
		$repo = OperationRepository::build([$op]);

		$runtime = $this->makeRuntime(operations: $repo);

		$this->assertSame($repo, $runtime->operations);
		$this->assertSame($op, $runtime->operations->find('greet'));
	}

	public function testMultipleOperationsAreIndependentlyAddressable(): void
	{
		$opA  = new CompiledOperation('a', 0, 0, 0, 0);
		$opB  = new CompiledOperation('b', 1, 1, 1, 1);
		$repo = OperationRepository::build([$opA, $opB]);

		$runtime = $this->makeRuntime(operations: $repo);

		$this->assertSame($opA, $runtime->operations->find('a'));
		$this->assertSame($opB, $runtime->operations->find('b'));
		$this->assertNull($runtime->operations->find('c'));
	}

	// ── factories ────────────────────────────────────────────────────────────

	public function testFactoriesDefaultsToEmptyRepositoryWhenNotProvided(): void
	{
		$runtime = $this->makeRuntime();

		$this->assertInstanceOf(FactoryRepository::class, $runtime->factories);
		$this->assertNull($runtime->getService(CompiledRuntimeServiceFixture::class));
	}

	public function testGetServiceReturnsNullForUnknownAlias(): void
	{
		$this->assertNull($this->makeRuntime()->getService(CompiledRuntimeServiceFixture::class));
	}

	public function testGetServiceDelegatesToFactoryRepository(): void
	{
		$descriptor = new ServiceDescriptor(
			CompiledRuntimeServiceFixture::class,
			CompiledRuntimeServiceFixture::class,
			[CompiledRuntimeServiceFixture::class],
		);
		$repo    = FactoryRepository::build([$descriptor], new FactoryCompiler());
		$runtime = $this->makeRuntime(factories: $repo);

		$factory = $runtime->getService(CompiledRuntimeServiceFixture::class);
		$this->assertInstanceOf(CompiledFactory::class, $factory);
		assert($factory instanceof CompiledFactory);
		$this->assertInstanceOf(CompiledRuntimeServiceFixture::class, $factory->create());
	}

	public function testExplicitFactoryRepositoryIsStoredOnFactoriesField(): void
	{
		$repo    = FactoryRepository::build([], new FactoryCompiler());
		$runtime = $this->makeRuntime(factories: $repo);

		$this->assertSame($repo, $runtime->factories);
	}

	// ── artifacts ────────────────────────────────────────────────────────────

	public function testArtifactsDefaultsToEmptyRepositoryWhenNotProvided(): void
	{
		$runtime = $this->makeRuntime();

		$this->assertInstanceOf(ArtifactRepository::class, $runtime->artifacts);
		$this->assertFalse($runtime->artifacts->has(CompiledRuntimeArtifactFixture::class));
	}

	public function testArtifactsExposesPassedRepository(): void
	{
		$artifact = new CompiledRuntimeArtifactFixture();
		$repo     = ArtifactRepository::build([CompiledRuntimeArtifactFixture::class => $artifact]);
		$runtime  = $this->makeRuntime(artifacts: $repo);

		$this->assertSame($repo, $runtime->artifacts);
		$this->assertSame($artifact, $runtime->artifacts->get(CompiledRuntimeArtifactFixture::class));
	}
}
