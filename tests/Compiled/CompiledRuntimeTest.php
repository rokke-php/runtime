<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Compiled;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Build\CompiledFactory;
use Rokke\Runtime\Build\FactoryCompiler;
use Rokke\Runtime\Build\FactoryRepository;
use Rokke\Runtime\Build\ServiceDescriptor;
use Rokke\Runtime\Compiled\CompiledOperation;
use Rokke\Runtime\Compiled\CompiledRuntime;

// ── Fixture ───────────────────────────────────────────────────────────────────

final class CompiledRuntimeServiceFixture {}

// ── Tests ─────────────────────────────────────────────────────────────────────

final class CompiledRuntimeTest extends TestCase
{
	public function testGetOperationReturnsNullForUnknownId(): void
	{
		$runtime = new CompiledRuntime([], [], [], [], []);

		$this->assertNull($runtime->getOperation('unknown'));
	}

	public function testGetOperationReturnsCompiledOperationForKnownId(): void
	{
		$op      = new CompiledOperation(0, 1, 2, 3);
		$runtime = new CompiledRuntime([], [], [], [], ['users.create' => $op]);

		$this->assertSame($op, $runtime->getOperation('users.create'));
	}

	public function testGetOperationReturnsNullForDifferentId(): void
	{
		$op      = new CompiledOperation(0, 1, 2, 3);
		$runtime = new CompiledRuntime([], [], [], [], ['users.create' => $op]);

		$this->assertNull($runtime->getOperation('users.delete'));
	}

	public function testMultipleOperationsAreIndependentlyAddressable(): void
	{
		$opA     = new CompiledOperation(0, 0, 0, 0);
		$opB     = new CompiledOperation(1, 1, 1, 1);
		$runtime = new CompiledRuntime([], [], [], [], [
			'a' => $opA,
			'b' => $opB,
		]);

		$this->assertSame($opA, $runtime->getOperation('a'));
		$this->assertSame($opB, $runtime->getOperation('b'));
	}

	public function testHandlersAreAccessibleByIndex(): void
	{
		$handler = fn (): string => 'ok';
		$runtime = new CompiledRuntime([], [0 => $handler], [], [], []);

		$this->assertSame($handler, $runtime->handlers[0]);
	}

	public function testFactoriesDefaultsToEmptyRepositoryWhenNotProvided(): void
	{
		$runtime = new CompiledRuntime([], [], [], [], []);

		$this->assertInstanceOf(FactoryRepository::class, $runtime->factories);
		$this->assertNull($runtime->getService(CompiledRuntimeServiceFixture::class));
	}

	public function testGetServiceReturnsNullForUnknownAlias(): void
	{
		$runtime = new CompiledRuntime([], [], [], [], []);

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
		$runtime = new CompiledRuntime([], [], [], [], [], $repo);

		$factory = $runtime->getService(CompiledRuntimeServiceFixture::class);
		$this->assertInstanceOf(CompiledFactory::class, $factory);
		assert($factory instanceof CompiledFactory);
		$this->assertInstanceOf(CompiledRuntimeServiceFixture::class, $factory->create());
	}

	public function testExplicitFactoryRepositoryIsStoredOnFactoriesField(): void
	{
		$repo    = FactoryRepository::build([], new FactoryCompiler());
		$runtime = new CompiledRuntime([], [], [], [], [], $repo);

		$this->assertSame($repo, $runtime->factories);
	}
}
