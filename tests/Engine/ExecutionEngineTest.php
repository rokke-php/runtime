<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Engine;

use PHPUnit\Framework\TestCase;
use Rokke\Contracts\Execution\ExecutionInterceptorInterface;
use Rokke\Runtime\Build\ApplicationModel;
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

final class ExecutionEngineTest extends TestCase
{
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

	private function makeRuntime(string $opId = 'op', ?callable $handler = null): CompiledRuntime
	{
		$handler ??= static fn (): string => 'core';

		$op = new CompiledOperation($opId, 0, 0, 0, 0);

		$execPipeline = new CompiledExecutionPipeline(
			handlers: [0 => $handler],
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
		$log         = [];
		$interceptor = new class ($log) implements ExecutionInterceptorInterface {
			/** @param list<string> $log */
			public function __construct(private array &$log) {}

			public function intercept(object $context, callable $next): mixed
			{
				$this->log[] = 'before';
				$result       = $next();
				$this->log[] = 'after';

				return $result;
			}
		};

		$op = new CompiledOperation('op', 0, 0, 0, 0);

		$execPipeline = new CompiledExecutionPipeline(
			handlers: [0 => static function () use (&$log): string {
				$log[] = 'handler';
				return 'result';
			}],
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

		$this->assertSame(['before', 'handler', 'after'], $log);
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
			handlers: [0 => static fn (): never => throw new \RuntimeException('handler blew up')],
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
		$handlerCalled = false;

		$blocking = new class () implements ExecutionInterceptorInterface {
			public function intercept(object $context, callable $next): mixed
			{
				throw new \RuntimeException('Interceptor blocked execution.');
			}
		};

		$op = new CompiledOperation('op', 0, 0, 0, 0);

		$execPipeline = new CompiledExecutionPipeline(
			handlers: [0 => static function () use (&$handlerCalled): string {
				$handlerCalled = true;

				return 'should not run';
			}],
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

		$this->assertFalse($handlerCalled);
	}

	// ── End-to-end via builder ────────────────────────────────────────────────

	public function testBuilderProducesWorkingEngine(): void
	{
		$model = new ApplicationModel();
		$model->add(new OperationDefinition('greet', 'Greet', static fn (): string => 'hello'));

		$engine = (new DefaultRuntimeBuilder())->build($model);
		$op     = $this->makeOp('greet');
		$result = $engine->execute($op, $this->makeCtx());

		$this->assertSame('hello', $result);
	}
}
