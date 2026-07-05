<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Engine;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Compiled\Arguments\ArgumentResolutionPlan;
use Rokke\Runtime\Compiled\CompiledOperation;
use Rokke\Runtime\Compiled\CompiledPipeline;
use Rokke\Runtime\Compiled\CompiledRuntime;
use Rokke\Runtime\Compiled\OperationRepository;
use Rokke\Runtime\Compiled\Results\ResultResolutionPlan;
use Rokke\Runtime\Compiled\Results\ScalarResultInstruction;
use Rokke\Runtime\Contracts\InvokerInterface;
use Rokke\Runtime\Contracts\OperationContextInterface;
use Rokke\Runtime\Contracts\OperationInterface;
use Rokke\Runtime\Engine\ExecutionEngine;
use Rokke\Runtime\Engine\Invoker;

final class ExecutionEngineTest extends TestCase
{
	private function makeOperation(): OperationInterface
	{
		return $this->createStub(OperationInterface::class);
	}

	private function makeContext(): OperationContextInterface
	{
		return $this->createStub(OperationContextInterface::class);
	}

	public function testCallsInvokerDirectlyWithNoMiddlewares(): void
	{
		$invoker = $this->createMock(InvokerInterface::class);
		$invoker->expects($this->once())->method('invoke')->willReturn('direct');

		$engine = new ExecutionEngine($invoker);
		$result = $engine->execute($this->makeOperation(), $this->makeContext());

		$this->assertSame('direct', $result);
	}

	public function testSingleMiddlewareWrapsInvokerAndCallsNext(): void
	{
		$invoker = $this->createStub(InvokerInterface::class);
		$invoker->method('invoke')->willReturn('core');

		$called     = false;
		$middleware = function (OperationInterface $op, OperationContextInterface $ctx, callable $next) use (&$called): mixed {
			$called = true;

			return $next();
		};

		$engine = new ExecutionEngine($invoker, [$middleware]);
		$result = $engine->execute($this->makeOperation(), $this->makeContext());

		$this->assertTrue($called);
		$this->assertSame('core', $result);
	}

	public function testMiddlewaresRunOutermostFirst(): void
	{
		$order   = [];
		$invoker = $this->createStub(InvokerInterface::class);
		$invoker->method('invoke')->willReturn('done');

		$first = function (OperationInterface $op, OperationContextInterface $ctx, callable $next) use (&$order): mixed {
			$order[] = 'first';
			$result  = $next();
			$order[] = 'first-after';

			return $result;
		};

		$second = function (OperationInterface $op, OperationContextInterface $ctx, callable $next) use (&$order): mixed {
			$order[] = 'second';
			$result  = $next();
			$order[] = 'second-after';

			return $result;
		};

		$engine = new ExecutionEngine($invoker, [$first, $second]);
		$engine->execute($this->makeOperation(), $this->makeContext());

		$this->assertSame(['first', 'second', 'second-after', 'first-after'], $order);
	}

	public function testMiddlewareCanShortCircuitWithoutCallingInvoker(): void
	{
		$invoker = $this->createMock(InvokerInterface::class);
		$invoker->expects($this->never())->method('invoke');

		$middleware = fn (OperationInterface $op, OperationContextInterface $ctx, callable $next): string => 'short-circuit';

		$engine = new ExecutionEngine($invoker, [$middleware]);
		$result = $engine->execute($this->makeOperation(), $this->makeContext());

		$this->assertSame('short-circuit', $result);
	}

	public function testMiddlewareReceivesOperationAndContext(): void
	{
		$op  = $this->makeOperation();
		$ctx = $this->makeContext();

		$receivedOp  = null;
		$receivedCtx = null;

		$invoker = $this->createStub(InvokerInterface::class);
		$invoker->method('invoke')->willReturn(null);

		$middleware = function (OperationInterface $o, OperationContextInterface $c, callable $next) use (&$receivedOp, &$receivedCtx): mixed {
			$receivedOp  = $o;
			$receivedCtx = $c;

			return $next();
		};

		$engine = new ExecutionEngine($invoker, [$middleware]);
		$engine->execute($op, $ctx);

		$this->assertSame($op, $receivedOp);
		$this->assertSame($ctx, $receivedCtx);
	}

	public function testMiddlewareExceptionPropagates(): void
	{
		$invoker    = $this->createStub(InvokerInterface::class);
		$middleware = fn (OperationInterface $op, OperationContextInterface $ctx, callable $next): never =>
			throw new \RuntimeException('middleware blew up');

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('middleware blew up');

		$engine = new ExecutionEngine($invoker, [$middleware]);
		$engine->execute($this->makeOperation(), $this->makeContext());
	}

	public function testReturnValueFlowsThroughAllMiddlewares(): void
	{
		$invoker = $this->createStub(InvokerInterface::class);
		$invoker->method('invoke')->willReturn(42);

		$passthrough = fn (OperationInterface $op, OperationContextInterface $ctx, callable $next): mixed => $next();

		$engine = new ExecutionEngine($invoker, [$passthrough, $passthrough]);
		$result = $engine->execute($this->makeOperation(), $this->makeContext());

		$this->assertSame(42, $result);
	}

	// ── Compiled pipeline path ────────────────────────────────────────────────

	/** @param list<callable(OperationInterface, OperationContextInterface, callable): mixed> $stages */
	private function makeCompiledRuntime(string $opId, array $stages): CompiledRuntime
	{
		$op = new CompiledOperation(
			id: $opId,
			pipelineId: 0,
			handlerId: 0,
			argumentPlanId: 0,
			resultPlanId: 0,
		);

		$handler      = static fn (): string => 'core';
		$argPlan      = new ArgumentResolutionPlan([]);
		$resultPlan   = new ResultResolutionPlan(new ScalarResultInstruction('string'));
		$pipeline     = new CompiledPipeline($stages);
		$operations   = OperationRepository::build([$op]);

		return new CompiledRuntime(
			pipelines: [0 => $pipeline],
			handlers: [0 => $handler],
			argumentPlans: [0 => $argPlan],
			resultPlans: [0 => $resultPlan],
			operations: $operations,
		);
	}

	private function makeRealOperation(string $id): OperationInterface
	{
		$op = $this->createStub(OperationInterface::class);
		$op->method('id')->willReturn($id);

		return $op;
	}

	public function testCompiledPipelineStagesRunAroundInvoker(): void
	{
		$order   = [];
		$stage   = function (OperationInterface $op, OperationContextInterface $ctx, callable $next) use (&$order): mixed {
			$order[] = 'before';
			$result  = $next();
			$order[] = 'after';

			return $result;
		};

		$runtime = $this->makeCompiledRuntime('greet', [$stage]);
		$engine  = new ExecutionEngine(new Invoker($runtime), runtime: $runtime);

		$engine->execute($this->makeRealOperation('greet'), $this->makeContext());

		$this->assertSame(['before', 'after'], $order);
	}

	public function testCompiledPipelineEmptyRunsCoreDirectly(): void
	{
		$runtime = $this->makeCompiledRuntime('greet', []);
		$engine  = new ExecutionEngine(new Invoker($runtime), runtime: $runtime);

		$result = $engine->execute($this->makeRealOperation('greet'), $this->makeContext());

		$this->assertSame('core', $result);
	}

	public function testCompiledPipelineStagesRunOutermostFirst(): void
	{
		$order = [];

		$first = function (OperationInterface $op, OperationContextInterface $ctx, callable $next) use (&$order): mixed {
			$order[] = 'first';
			$result  = $next();
			$order[] = 'first-after';

			return $result;
		};

		$second = function (OperationInterface $op, OperationContextInterface $ctx, callable $next) use (&$order): mixed {
			$order[] = 'second';
			$result  = $next();
			$order[] = 'second-after';

			return $result;
		};

		$runtime = $this->makeCompiledRuntime('greet', [$first, $second]);
		$engine  = new ExecutionEngine(new Invoker($runtime), runtime: $runtime);

		$engine->execute($this->makeRealOperation('greet'), $this->makeContext());

		$this->assertSame(['first', 'second', 'second-after', 'first-after'], $order);
	}

	public function testGlobalMiddlewaresUsedWhenNoRuntime(): void
	{
		$ran     = false;
		$invoker = $this->createStub(InvokerInterface::class);
		$invoker->method('invoke')->willReturn('ok');

		$mw     = function (OperationInterface $op, OperationContextInterface $ctx, callable $next) use (&$ran): mixed {
			$ran = true;

			return $next();
		};

		$engine = new ExecutionEngine($invoker, [$mw]);
		$engine->execute($this->makeOperation(), $this->makeContext());

		$this->assertTrue($ran);
	}
}
