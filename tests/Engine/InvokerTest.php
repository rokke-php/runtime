<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Engine;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Build\CompiledFactory;
use Rokke\Runtime\Compiled\Arguments\ArgumentResolutionPlan;
use Rokke\Runtime\Compiled\Arguments\ContextArgumentInstruction;
use Rokke\Runtime\Compiled\Arguments\FactoryArgumentInstruction;
use Rokke\Runtime\Compiled\CompiledOperation;
use Rokke\Runtime\Compiled\CompiledRuntime;
use Rokke\Runtime\Compiled\Results\ResultResolutionPlan;
use Rokke\Runtime\Compiled\Results\ScalarResultInstruction;
use Rokke\Runtime\Compiled\Results\VoidResultInstruction;
use Rokke\Runtime\Contracts\OperationContextInterface;
use Rokke\Runtime\Contracts\OperationInterface;
use Rokke\Runtime\Engine\Invoker;

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class InvokerServiceFixture {}

// ── Tests ─────────────────────────────────────────────────────────────────────

final class InvokerTest extends TestCase
{
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

	private function emptyArgPlan(): ArgumentResolutionPlan
	{
		return new ArgumentResolutionPlan([]);
	}

	private function contextArgPlan(): ArgumentResolutionPlan
	{
		return new ArgumentResolutionPlan([new ContextArgumentInstruction()]);
	}

	private function stringResultPlan(): ResultResolutionPlan
	{
		return new ResultResolutionPlan(new ScalarResultInstruction('string'));
	}

	private function voidResultPlan(): ResultResolutionPlan
	{
		return new ResultResolutionPlan(new VoidResultInstruction());
	}

	public function testThrowsWhenOperationIdNotFound(): void
	{
		$runtime = new CompiledRuntime([], [], [], [], []);
		$invoker = new Invoker($runtime);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage("No compiled operation found for id 'missing'.");

		$invoker->invoke($this->makeOperation('missing'), $this->makeContext());
	}

	public function testThrowsWhenHandlerIdNotInRuntime(): void
	{
		$op      = new CompiledOperation(0, 99, 0, 0);
		$runtime = new CompiledRuntime(
			[],
			[],
			[0 => $this->emptyArgPlan()],
			[0 => $this->stringResultPlan()],
			['op.a' => $op],
		);
		$invoker = new Invoker($runtime);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Handler #99 not found');

		$invoker->invoke($this->makeOperation('op.a'), $this->makeContext());
	}

	public function testThrowsWhenArgumentPlanNotFound(): void
	{
		$handler = fn (): string => 'ok';
		$op      = new CompiledOperation(0, 0, 99, 0);
		$runtime = new CompiledRuntime(
			[],
			[0 => $handler],
			[],
			[0 => $this->stringResultPlan()],
			['op.a' => $op],
		);
		$invoker = new Invoker($runtime);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('ArgumentResolutionPlan #99 not found');

		$invoker->invoke($this->makeOperation('op.a'), $this->makeContext());
	}

	public function testThrowsWhenResultPlanNotFound(): void
	{
		$handler = fn (): string => 'ok';
		$op      = new CompiledOperation(0, 0, 0, 99);
		$runtime = new CompiledRuntime(
			[],
			[0 => $handler],
			[0 => $this->emptyArgPlan()],
			[],
			['op.a' => $op],
		);
		$invoker = new Invoker($runtime);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('ResultResolutionPlan #99 not found');

		$invoker->invoke($this->makeOperation('op.a'), $this->makeContext());
	}

	public function testInvokesZeroArgHandlerAndReturnsResult(): void
	{
		$handler = fn (): string => 'hello';
		$op      = new CompiledOperation(0, 0, 0, 0);
		$runtime = new CompiledRuntime(
			[],
			[0 => $handler],
			[0 => $this->emptyArgPlan()],
			[0 => $this->stringResultPlan()],
			['op.a' => $op],
		);
		$invoker = new Invoker($runtime);

		$result = $invoker->invoke($this->makeOperation('op.a'), $this->makeContext());

		$this->assertSame('hello', $result);
	}

	public function testHandlerReceivesContextViaContextInstruction(): void
	{
		$ctx      = $this->makeContext();
		$received = null;

		$handler = function (OperationContextInterface $c) use (&$received): void {
			$received = $c;
		};

		$op      = new CompiledOperation(0, 0, 0, 0);
		$runtime = new CompiledRuntime(
			[],
			[0 => $handler],
			[0 => $this->contextArgPlan()],
			[0 => $this->voidResultPlan()],
			['op.a' => $op],
		);
		$invoker = new Invoker($runtime);

		$invoker->invoke($this->makeOperation('op.a'), $ctx);

		$this->assertSame($ctx, $received);
	}

	public function testHandlerReceivesServiceViaFactoryInstruction(): void
	{
		$instance = new InvokerServiceFixture();
		$factory  = new CompiledFactory(static fn (): object => $instance);
		$argPlan  = new ArgumentResolutionPlan([new FactoryArgumentInstruction($factory)]);
		$received = null;

		$handler = function (InvokerServiceFixture $svc) use (&$received): void {
			$received = $svc;
		};

		$op      = new CompiledOperation(0, 0, 0, 0);
		$runtime = new CompiledRuntime(
			[],
			[0 => $handler],
			[0 => $argPlan],
			[0 => $this->voidResultPlan()],
			['op.a' => $op],
		);
		$invoker = new Invoker($runtime);

		$invoker->invoke($this->makeOperation('op.a'), $this->makeContext());

		$this->assertSame($instance, $received);
	}

	public function testHandlerIsResolvedByCompiledHandlerId(): void
	{
		$wrong = fn (): string => 'wrong';
		$right = fn (): string => 'right';

		$op      = new CompiledOperation(0, 1, 1, 1);
		$runtime = new CompiledRuntime(
			[],
			[0 => $wrong, 1 => $right],
			[0 => $this->emptyArgPlan(), 1 => $this->emptyArgPlan()],
			[0 => $this->stringResultPlan(), 1 => $this->stringResultPlan()],
			['op.a' => $op],
		);
		$invoker = new Invoker($runtime);

		$result = $invoker->invoke($this->makeOperation('op.a'), $this->makeContext());

		$this->assertSame('right', $result);
	}

	public function testResultPlanIsApplied(): void
	{
		$handler    = fn (): string => 'raw';
		$op         = new CompiledOperation(0, 0, 0, 0);
		$resultPlan = new ResultResolutionPlan(new ScalarResultInstruction('string'));
		$runtime    = new CompiledRuntime(
			[],
			[0 => $handler],
			[0 => $this->emptyArgPlan()],
			[0 => $resultPlan],
			['op.a' => $op],
		);
		$invoker = new Invoker($runtime);

		$result = $invoker->invoke($this->makeOperation('op.a'), $this->makeContext());

		$this->assertSame('raw', $result);
	}
}
