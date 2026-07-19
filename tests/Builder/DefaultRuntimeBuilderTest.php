<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Builder;

use PHPUnit\Framework\TestCase;
use Rokke\Contracts\Configuration\ConfigurationDescriptorInterface;
use Rokke\Runtime\Build\ApplicationModel;
use Rokke\Runtime\Build\ExtensionBuildPassInterface;
use Rokke\Runtime\Build\OperationDefinition;
use Rokke\Runtime\Build\ServiceDescriptor;
use Rokke\Runtime\Builder\DefaultRuntimeBuilder;
use Rokke\Runtime\Compiled\CompiledConfigurationRepository;
use Rokke\Runtime\Contracts\OperationContextInterface;
use Rokke\Runtime\Contracts\OperationInterface;
use Rokke\Runtime\Contracts\RuntimeInterface;
use Rokke\Runtime\Engine\ExecutionEngine;

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class BuilderConfigDescriptor implements ConfigurationDescriptorInterface {}

final class BuilderCompiledConfig
{
    public function __construct(public readonly string $value) {}
}

final class BuilderTestDep {}

final class BuilderTestService
{
	public function __construct(public readonly BuilderTestDep $dep) {}
}

final class BuilderHelloHandler
{
	public function __invoke(OperationContextInterface $ctx): string
	{
		return 'hello';
	}
}

final class BuilderContextCaptureHandler
{
	public static ?OperationContextInterface $received = null;

	public function __invoke(OperationContextInterface $ctx): void
	{
		self::$received = $ctx;
	}
}

final class BuilderResultAHandler
{
	public function __invoke(): string
	{
		return 'result-a';
	}
}

final class BuilderResultBHandler
{
	public function __invoke(): string
	{
		return 'result-b';
	}
}

// ── Tests ─────────────────────────────────────────────────────────────────────

final class DefaultRuntimeBuilderTest extends TestCase
{
	private DefaultRuntimeBuilder $builder;

	protected function setUp(): void
	{
		$this->builder                         = new DefaultRuntimeBuilder();
		BuilderContextCaptureHandler::$received = null;
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
			handler: BuilderHelloHandler::class,
		));

		$runtime = $this->builder->build($model);
		$result  = $runtime->execute($this->makeOperation('greet'), $this->makeContext());

		$this->assertSame('hello', $result);
	}

	public function testBuiltRuntimePassesContextToHandler(): void
	{
		$ctx = $this->makeContext();

		$model = new ApplicationModel();
		$model->add(new OperationDefinition(
			id: 'capture',
			name: 'Capture',
			handler: BuilderContextCaptureHandler::class,
		));

		$runtime = $this->builder->build($model);
		$runtime->execute($this->makeOperation('capture'), $ctx);

		$this->assertSame($ctx, BuilderContextCaptureHandler::$received);
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
		$model->add(new OperationDefinition('greet', 'Greet', BuilderHelloHandler::class));
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
		$model->add(new OperationDefinition('op.a', 'A', BuilderResultAHandler::class));
		$model->add(new OperationDefinition('op.b', 'B', BuilderResultBHandler::class));

		$runtime = $this->builder->build($model);

		$this->assertSame('result-a', $runtime->execute($this->makeOperation('op.a'), $this->makeContext()));
		$this->assertSame('result-b', $runtime->execute($this->makeOperation('op.b'), $this->makeContext()));
	}

	public function testBuiltRuntimeHasEmptyConfigurationsWhenNoPassesProvided(): void
	{
		$model   = new ApplicationModel();
		$runtime = $this->builder->build($model);

		assert($runtime instanceof ExecutionEngine);
		$compiled = $runtime->compiledRuntime();

		$this->assertInstanceOf(CompiledConfigurationRepository::class, $compiled->configurations());
		$this->assertSame([], $compiled->configurations()->all());
	}

	public function testBuiltRuntimeRunsBuildPassAndStoresResult(): void
	{
		$compiledConfig = new BuilderCompiledConfig('hello');

		$pass = new class ($compiledConfig) implements ExtensionBuildPassInterface {
			public function __construct(private readonly object $result) {}

			public function process(ApplicationModel $model): array
			{
				return [$this->result];
			}
		};

		$model = new ApplicationModel();
		$model->add(new BuilderConfigDescriptor());

		$runtime = $this->builder->build($model, [$pass]);

		assert($runtime instanceof ExecutionEngine);
		$compiled = $runtime->compiledRuntime();

		$this->assertTrue($compiled->configurations()->has(BuilderCompiledConfig::class));
		$this->assertSame($compiledConfig, $compiled->configurations()->get(BuilderCompiledConfig::class));
	}

	public function testBuildPassReceivesApplicationModelWithDescriptors(): void
	{
		$descriptor = new BuilderConfigDescriptor();
		$pass       = new class () implements ExtensionBuildPassInterface {
			public ?ApplicationModel $captured = null;

			public function process(ApplicationModel $model): array
			{
				$this->captured = $model;
				return [];
			}
		};

		$model = new ApplicationModel();
		$model->add($descriptor);

		$this->builder->build($model, [$pass]);

		$this->assertNotNull($pass->captured);
		$this->assertSame([$descriptor], $pass->captured->definitions(BuilderConfigDescriptor::class));
	}
}
