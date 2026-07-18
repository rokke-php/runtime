<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Engine;

use PHPUnit\Framework\TestCase;
use Rokke\Contracts\Execution\ExecutionInterceptorInterface;
use Rokke\Runtime\Build\ApplicationModel;
use Rokke\Runtime\Build\CompiledFactory;
use Rokke\Runtime\Build\FactoryRepository;
use Rokke\Runtime\Build\OperationDefinition;
use Rokke\Runtime\Builder\DefaultRuntimeBuilder;
use Rokke\Runtime\Compiled\Arguments\ArgumentResolutionPlan;
use Rokke\Runtime\Compiled\CompiledExecutionPipeline;
use Rokke\Runtime\Compiled\CompiledInterceptorPipeline;
use Rokke\Runtime\Compiled\CompiledOperation;
use Rokke\Runtime\Compiled\CompiledRuntime;
use Rokke\Runtime\Compiled\OperationRepository;
use Rokke\Runtime\Compiled\Results\ResultResolutionPlan;
use Rokke\Runtime\Compiled\Results\ScalarResultInstruction;
use Rokke\Runtime\Contracts\OperationContextInterface;
use Rokke\Runtime\Contracts\OperationInterface;
use Rokke\Runtime\Engine\ExecutionEngine;

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class EngineHelloHandler
{
	public function __invoke(): string { return 'hello'; }
}

final class EngineCoreHandler
{
	public function __invoke(): string { return 'core'; }
}

final class EngineLogHandler
{
	/** @var list<string> */
	public static array $log = [];

	public function __invoke(): string
	{
		self::$log[] = 'handler';
		return 'result';
	}
}

final class EngineThrowingHandler
{
	public function __invoke(): never
	{
		throw new \RuntimeException('handler blew up');
	}
}

final class EngineTrackingHandler
{
	public static bool $called = false;

	public function __invoke(): string
	{
		self::$called = true;
		return 'should not run';
	}
}

// ── Test ──────────────────────────────────────────────────────────────────────

final class ExecutionEngineTest extends TestCase
{
	protected function setUp(): void
	{
		EngineLogHandler::$log     = [];
		EngineTrackingHandler::$called = false;
	}

	private function makeCtx(): OperationContextInterface
	{
		return $this->createStub(OperationContextInterface::class);
	}

	private function makeOp(string $id = 'op'): OperationInterface
	{
		$op = $this->createStub(OperationInterface::class);
		$op->method('id')->willReturn($id);

		return $op;
	}

	/** @param class-string $handlerClass */
	private function makeRuntime(string $opId = 'op', string $handlerClass = EngineCoreHandler::class): CompiledRuntime
	{
		$op = new CompiledOperation($opId, 0, 0, 0, 0);

		$execPipeline = new CompiledExecutionPipeline(
			factories: FactoryRepository::fromDescriptors([new CompiledFactory($handlerClass)]),
			argumentPlans: [0 => new ArgumentResolutionPlan([])],
			resultPlans: [0 => new ResultResolutionPlan(new ScalarResultInstruction('string'))],
			behaviorPipelines: [],
			validationPlans: [],
		);

		return new CompiledRuntime(
			executionPipeline: $execPipeline,
			interceptorPipeline: CompiledInterceptorPipeline::empty(),
			operations: OperationRepository::build([$op]),
		);
	}

	// ── Core dispatch ─────────────────────────────────────────────────────────

	public function testDispatchesOperationToExecutionPipeline(): void
	{
		$engine = new ExecutionEngine($this->makeRuntime());

		$result = $engine->execute($this->makeOp(), $this->makeCtx());

		$this->assertSame('core', $result);
	}

	public function testThrowsForUnknownOperationId(): void
	{
		$engine = new ExecutionEngine($this->makeRuntime('known'));

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage("No compiled operation found for id 'unknown'.");

		$engine->execute($this->makeOp('unknown'), $this->makeCtx());
	}

	// ── InterceptorPipeline wraps ExecutionPipeline ───────────────────────────

	public function testInterceptorWrapsEntireExecution(): void
	{
		// Both the interceptor and EngineLogHandler write to EngineLogHandler::$log
		// so the full execution order is captured in one place.
		$interceptor = new class () implements ExecutionInterceptorInterface {
			public function intercept(object $context, callable $next): mixed
			{
				EngineLogHandler::$log[] = 'before';
				$result                  = $next();
				EngineLogHandler::$log[] = 'after';

				return $result;
			}
		};

		$op = new CompiledOperation('op', 0, 0, 0, 0);

		$execPipeline = new CompiledExecutionPipeline(
			factories: FactoryRepository::fromDescriptors([new CompiledFactory(EngineLogHandler::class)]),
			argumentPlans: [0 => new ArgumentResolutionPlan([])],
			resultPlans: [0 => new ResultResolutionPlan(new ScalarResultInstruction('string'))],
			behaviorPipelines: [],
			validationPlans: [],
		);

		$runtime = new CompiledRuntime(
			executionPipeline: $execPipeline,
			interceptorPipeline: new CompiledInterceptorPipeline([$interceptor]),
			operations: OperationRepository::build([$op]),
		);

		$engine = new ExecutionEngine($runtime);
		$result = $engine->execute($this->makeOp(), $this->makeCtx());

		$this->assertSame(['before', 'handler', 'after'], EngineLogHandler::$log);
		$this->assertSame('result', $result);
	}

	public function testInterceptorObservesExceptionFromHandler(): void
	{
		$observed    = false;
		$interceptor = new class ($observed) implements ExecutionInterceptorInterface {
			public function __construct(private bool &$observed) {}

			public function intercept(object $context, callable $next): mixed
			{
				try {
					return $next();
				} catch (\Throwable) {
					$this->observed = true;
					throw new \RuntimeException('Wrapped by interceptor.');
				}
			}
		};

		$op = new CompiledOperation('op', 0, 0, 0, 0);

		$execPipeline = new CompiledExecutionPipeline(
			factories: FactoryRepository::fromDescriptors([new CompiledFactory(EngineThrowingHandler::class)]),
			argumentPlans: [0 => new ArgumentResolutionPlan([])],
			resultPlans: [0 => new ResultResolutionPlan(new ScalarResultInstruction('string'))],
			behaviorPipelines: [],
			validationPlans: [],
		);

		$runtime = new CompiledRuntime(
			executionPipeline: $execPipeline,
			interceptorPipeline: new CompiledInterceptorPipeline([$interceptor]),
			operations: OperationRepository::build([$op]),
		);

		$engine = new ExecutionEngine($runtime);

		try {
			$engine->execute($this->makeOp(), $this->makeCtx());
		} catch (\RuntimeException $e) {
			$this->assertSame('Wrapped by interceptor.', $e->getMessage());
			$this->assertTrue($observed);

			return;
		}

		$this->fail('Expected RuntimeException was not thrown.');
	}

	public function testInterceptorBlockingPreventsHandlerExecution(): void
	{
		$blocking = new class () implements ExecutionInterceptorInterface {
			public function intercept(object $context, callable $next): mixed
			{
				throw new \RuntimeException('Interceptor blocked execution.');
			}
		};

		$op = new CompiledOperation('op', 0, 0, 0, 0);

		$execPipeline = new CompiledExecutionPipeline(
			factories: FactoryRepository::fromDescriptors([new CompiledFactory(EngineTrackingHandler::class)]),
			argumentPlans: [0 => new ArgumentResolutionPlan([])],
			resultPlans: [0 => new ResultResolutionPlan(new ScalarResultInstruction('string'))],
			behaviorPipelines: [],
			validationPlans: [],
		);

		$runtime = new CompiledRuntime(
			executionPipeline: $execPipeline,
			interceptorPipeline: new CompiledInterceptorPipeline([$blocking]),
			operations: OperationRepository::build([$op]),
		);

		$engine = new ExecutionEngine($runtime);

		try {
			$engine->execute($this->makeOp(), $this->makeCtx());
		} catch (\RuntimeException) {
		}

		$this->assertFalse(EngineTrackingHandler::$called);
	}

	// ── End-to-end via builder ────────────────────────────────────────────────

	public function testBuilderProducesWorkingEngine(): void
	{
		$model = new ApplicationModel();
		$model->add(new OperationDefinition('greet', 'Greet', EngineHelloHandler::class));

		$engine = (new DefaultRuntimeBuilder())->build($model);
		$op     = $this->makeOp('greet');
		$result = $engine->execute($op, $this->makeCtx());

		$this->assertSame('hello', $result);
	}
}
