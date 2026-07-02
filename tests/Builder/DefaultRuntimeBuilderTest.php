<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Builder;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Build\ApplicationModel;
use Rokke\Runtime\Build\OperationDefinition;
use Rokke\Runtime\Build\ServiceDescriptor;
use Rokke\Runtime\Builder\DefaultRuntimeBuilder;

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class BuilderTestDep {}

final class BuilderTestService
{
	public function __construct(public readonly BuilderTestDep $dep) {}
}
use Rokke\Runtime\Contracts\OperationContextInterface;
use Rokke\Runtime\Contracts\OperationInterface;
use Rokke\Runtime\Contracts\RuntimeInterface;

final class DefaultRuntimeBuilderTest extends TestCase
{
	private DefaultRuntimeBuilder $builder;

	protected function setUp(): void
	{
		$this->builder = new DefaultRuntimeBuilder();
	}

	private function makeOperation(string $id): OperationInterface
	{
		$op = $this->createStub(OperationInterface::class);
		$op->method('id')->willReturn($id);

		return $op;
	}

	private function makeContext(): OperationContextInterface
	{
		return $this->createStub(OperationContextInterface::class);
	}

	public function testBuildReturnsRuntimeInterface(): void
	{
		$runtime = $this->builder->build(new ApplicationModel());

		$this->assertInstanceOf(RuntimeInterface::class, $runtime);
	}

	public function testBuiltRuntimeExecutesRegisteredOperation(): void
	{
		$model = new ApplicationModel();
		$model->add(new OperationDefinition(
			id: 'greet',
			name: 'Greet',
			handler: static fn (OperationContextInterface $ctx): string => 'hello',
		));

		$runtime = $this->builder->build($model);
		$result  = $runtime->execute($this->makeOperation('greet'), $this->makeContext());

		$this->assertSame('hello', $result);
	}

	public function testBuiltRuntimePassesContextToHandler(): void
	{
		$ctx      = $this->makeContext();
		$received = null;

		$model = new ApplicationModel();
		$model->add(new OperationDefinition(
			id: 'capture',
			name: 'Capture',
			handler: static function (OperationContextInterface $c) use (&$received): void {
				$received = $c;
			},
		));

		$runtime = $this->builder->build($model);
		$runtime->execute($this->makeOperation('capture'), $ctx);

		$this->assertSame($ctx, $received);
	}

	public function testBuiltRuntimeThrowsForUnknownOperation(): void
	{
		$runtime = $this->builder->build(new ApplicationModel());

		$this->expectException(\RuntimeException::class);

		$runtime->execute($this->makeOperation('unknown'), $this->makeContext());
	}

	public function testBuildWithServiceDescriptorsDoesNotThrow(): void
	{
		$model = new ApplicationModel();
		$model->add(new ServiceDescriptor(DefaultRuntimeBuilder::class, DefaultRuntimeBuilder::class, [DefaultRuntimeBuilder::class]));

		$runtime = $this->builder->build($model);

		$this->assertInstanceOf(RuntimeInterface::class, $runtime);
	}

	public function testServicesAndOperationsCoexistInBuiltRuntime(): void
	{
		$model = new ApplicationModel();
		$model->add(new OperationDefinition('greet', 'Greet', static fn (): string => 'hello'));
		$model->add(new ServiceDescriptor(DefaultRuntimeBuilder::class, DefaultRuntimeBuilder::class, [DefaultRuntimeBuilder::class]));

		$runtime = $this->builder->build($model);
		$result  = $runtime->execute($this->makeOperation('greet'), $this->makeContext());

		$this->assertSame('hello', $result);
	}

	public function testBuildThrowsWhenServiceDependencyIsNotRegistered(): void
	{
		$model = new ApplicationModel();
		$model->add(new ServiceDescriptor(
			BuilderTestService::class,
			BuilderTestService::class,
			[BuilderTestService::class],
		));

		$this->expectException(\RuntimeException::class);

		$this->builder->build($model);
	}

	public function testBuildSucceedsWhenAllDependenciesAreRegistered(): void
	{
		$model = new ApplicationModel();
		$model->add(new ServiceDescriptor(BuilderTestDep::class, BuilderTestDep::class, [BuilderTestDep::class]));
		$model->add(new ServiceDescriptor(BuilderTestService::class, BuilderTestService::class, [BuilderTestService::class]));

		$runtime = $this->builder->build($model);

		$this->assertInstanceOf(RuntimeInterface::class, $runtime);
	}

	public function testMultipleOperationsAreIndependentlyExecutable(): void
	{
		$model = new ApplicationModel();
		$model->add(new OperationDefinition('op.a', 'A', static fn (): string => 'result-a'));
		$model->add(new OperationDefinition('op.b', 'B', static fn (): string => 'result-b'));

		$runtime = $this->builder->build($model);

		$this->assertSame('result-a', $runtime->execute($this->makeOperation('op.a'), $this->makeContext()));
		$this->assertSame('result-b', $runtime->execute($this->makeOperation('op.b'), $this->makeContext()));
	}
}
