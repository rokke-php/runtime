<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Engine;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Build\CompiledFactory;
use Rokke\Runtime\Compiled\Arguments\ArgumentResolutionPlan;
use Rokke\Runtime\Compiled\Arguments\ContextArgumentInstruction;
use Rokke\Runtime\Compiled\Arguments\FactoryArgumentInstruction;
use Rokke\Runtime\Compiled\CompiledInterceptorChain;
use Rokke\Runtime\Compiled\CompiledOperation;
use Rokke\Runtime\Compiled\CompiledRuntime;
use Rokke\Runtime\Compiled\OperationRepository;
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
		$runtime = new CompiledRuntime([], [], [], []);
		$invoker = new Invoker($runtime);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage("No compiled operation found for id 'missing'.");

		$invoker->invoke($this->makeOperation('missing'), $this->makeContext());
	}

	public function testThrowsWhenHandlerIdNotInRuntime(): void
	{
		$op      = new CompiledOperation('op.a', 0, 99, 0, 0);
		$runtime = new CompiledRuntime(
			[],
			[],
			[0 => $this->emptyArgPlan()],
			[0 => $this->stringResultPlan()],
			OperationRepository::build([$op]),
		);
		$invoker = new Invoker($runtime);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Handler #99 not found');

		$invoker->invoke($this->makeOperation('op.a'), $this->makeContext());
	}

	public function testThrowsWhenArgumentPlanNotFound(): void
	{
		$handler = fn (): string => 'ok';
		$op      = new CompiledOperation('op.a', 0, 0, 99, 0);
		$runtime = new CompiledRuntime(
			[],
			[0 => $handler],
			[],
			[0 => $this->stringResultPlan()],
			OperationRepository::build([$op]),
		);
		$invoker = new Invoker($runtime);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('ArgumentResolutionPlan #99 not found');

		$invoker->invoke($this->makeOperation('op.a'), $this->makeContext());
	}

	public function testThrowsWhenResultPlanNotFound(): void
	{
		$handler = fn (): string => 'ok';
		$op      = new CompiledOperation('op.a', 0, 0, 0, 99);
		$runtime = new CompiledRuntime(
			[],
			[0 => $handler],
			[0 => $this->emptyArgPlan()],
			[],
			OperationRepository::build([$op]),
		);
		$invoker = new Invoker($runtime);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('ResultResolutionPlan #99 not found');

		$invoker->invoke($this->makeOperation('op.a'), $this->makeContext());
	}

	public function testInvokesZeroArgHandlerAndReturnsResult(): void
	{
		$handler = fn (): string => 'hello';
		$op      = new CompiledOperation('op.a', 0, 0, 0, 0);
		$runtime = new CompiledRuntime(
			[],
			[0 => $handler],
			[0 => $this->emptyArgPlan()],
			[0 => $this->stringResultPlan()],
			OperationRepository::build([$op]),
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

		$op      = new CompiledOperation('op.a', 0, 0, 0, 0);
		$runtime = new CompiledRuntime(
			[],
			[0 => $handler],
			[0 => $this->contextArgPlan()],
			[0 => $this->voidResultPlan()],
			OperationRepository::build([$op]),
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

		$op      = new CompiledOperation('op.a', 0, 0, 0, 0);
		$runtime = new CompiledRuntime(
			[],
			[0 => $handler],
			[0 => $argPlan],
			[0 => $this->voidResultPlan()],
			OperationRepository::build([$op]),
		);
		$invoker = new Invoker($runtime);

		$invoker->invoke($this->makeOperation('op.a'), $this->makeContext());

		$this->assertSame($instance, $received);
	}

	public function testHandlerIsResolvedByCompiledHandlerId(): void
	{
		$wrong = fn (): string => 'wrong';
		$right = fn (): string => 'right';

		$op      = new CompiledOperation('op.a', 0, 1, 1, 1);
		$runtime = new CompiledRuntime(
			[],
			[0 => $wrong, 1 => $right],
			[0 => $this->emptyArgPlan(), 1 => $this->emptyArgPlan()],
			[0 => $this->stringResultPlan(), 1 => $this->stringResultPlan()],
			OperationRepository::build([$op]),
		);
		$invoker = new Invoker($runtime);

		$result = $invoker->invoke($this->makeOperation('op.a'), $this->makeContext());

		$this->assertSame('right', $result);
	}

	public function testResultPlanIsApplied(): void
	{
		$handler    = fn (): string => 'raw';
		$op         = new CompiledOperation('op.a', 0, 0, 0, 0);
		$resultPlan = new ResultResolutionPlan(new ScalarResultInstruction('string'));
		$runtime    = new CompiledRuntime(
			[],
			[0 => $handler],
			[0 => $this->emptyArgPlan()],
			[0 => $resultPlan],
			OperationRepository::build([$op]),
		);
		$invoker = new Invoker($runtime);

		$result = $invoker->invoke($this->makeOperation('op.a'), $this->makeContext());

		$this->assertSame('raw', $result);
	}

	// ── Interceptor chain tests ───────────────────────────────────────────────

	private function makeRuntimeWithChain(CompiledInterceptorChain $chain): CompiledRuntime
	{
		$handler = fn (): string => 'core';
		$op      = new CompiledOperation('op.a', 0, 0, 0, 0, interceptorChainId: 0);

		return new CompiledRuntime(
			[],
			[0 => $handler],
			[0 => $this->emptyArgPlan()],
			[0 => $this->stringResultPlan()],
			OperationRepository::build([$op]),
			interceptorChains: [0 => $chain],
		);
	}

	public function testEmptyInterceptorChainRunsHandlerDirectly(): void
	{
		$runtime = $this->makeRuntimeWithChain(CompiledInterceptorChain::empty());
		$invoker = new Invoker($runtime);

		$result = $invoker->invoke($this->makeOperation('op.a'), $this->makeContext());

		$this->assertSame('core', $result);
	}

	public function testSingleInterceptorFiresAroundHandler(): void
	{
		$fired = false;

		$stage = function (OperationInterface $op, OperationContextInterface $ctx, array $args, callable $next) use (&$fired): mixed {
			$fired = true;

			return $next($args);
		};

		$chain   = new CompiledInterceptorChain([$stage]);
		$runtime = $this->makeRuntimeWithChain($chain);
		$invoker = new Invoker($runtime);

		$result = $invoker->invoke($this->makeOperation('op.a'), $this->makeContext());

		$this->assertTrue($fired);
		$this->assertSame('core', $result);
	}

	public function testInterceptorCanModifyArgs(): void
	{
		$handler = fn (string $name): string => "hello:{$name}";
		$op      = new CompiledOperation('op.b', 0, 0, 0, 0, interceptorChainId: 0);

		$argInstruction = new class () implements \Rokke\Runtime\Compiled\Arguments\ArgumentInstructionInterface {
			public function resolve(\Rokke\Runtime\Contracts\OperationContextInterface $ctx): string
			{
				return 'original';
			}
		};

		$runtime = new CompiledRuntime(
			[],
			[0 => $handler],
			[0 => new ArgumentResolutionPlan([$argInstruction])],
			[0 => $this->stringResultPlan()],
			OperationRepository::build([$op]),
			interceptorChains: [0 => new CompiledInterceptorChain([
				fn (OperationInterface $op, OperationContextInterface $ctx, array $args, callable $next): mixed =>
					$next(array_map(fn (mixed $a): string => 'modified', $args)),
			])],
		);

		$invoker = new Invoker($runtime);
		$result  = $invoker->invoke($this->makeOperation('op.b'), $this->makeContext());

		$this->assertSame('hello:modified', $result);
	}

	public function testInterceptorCanShortCircuit(): void
	{
		$handlerCalled = false;
		$handler       = function () use (&$handlerCalled): string {
			$handlerCalled = true;

			return 'core';
		};

		$op      = new CompiledOperation('op.a', 0, 0, 0, 0, interceptorChainId: 0);
		$runtime = new CompiledRuntime(
			[],
			[0 => $handler],
			[0 => $this->emptyArgPlan()],
			[0 => $this->stringResultPlan()],
			OperationRepository::build([$op]),
			interceptorChains: [0 => new CompiledInterceptorChain([
				fn (OperationInterface $op, OperationContextInterface $ctx, array $args, callable $next): string => 'short-circuit',
			])],
		);

		$invoker = new Invoker($runtime);
		$result  = $invoker->invoke($this->makeOperation('op.a'), $this->makeContext());

		$this->assertFalse($handlerCalled);
		$this->assertSame('short-circuit', $result);
	}

	public function testInterceptorsRunOutermostFirst(): void
	{
		$order = [];

		$first = function (OperationInterface $op, OperationContextInterface $ctx, array $args, callable $next) use (&$order): mixed {
			$order[] = 'first-before';
			$result  = $next($args);
			$order[] = 'first-after';

			return $result;
		};

		$second = function (OperationInterface $op, OperationContextInterface $ctx, array $args, callable $next) use (&$order): mixed {
			$order[] = 'second-before';
			$result  = $next($args);
			$order[] = 'second-after';

			return $result;
		};

		$chain   = new CompiledInterceptorChain([$first, $second]);
		$runtime = $this->makeRuntimeWithChain($chain);
		$invoker = new Invoker($runtime);

		$invoker->invoke($this->makeOperation('op.a'), $this->makeContext());

		$this->assertSame(['first-before', 'second-before', 'second-after', 'first-after'], $order);
	}

	// ── Validation plan tests ─────────────────────────────────────────────────

	public function testValidationPlanRunsBeforeHandler(): void
	{
		$handlerCalled = false;
		$handler       = function () use (&$handlerCalled): string {
			$handlerCalled = true;

			return 'core';
		};

		$validated   = null;
		$fakePlan    = new \Rokke\Runtime\Compiled\ValidationPlan([
			new \Rokke\Runtime\Compiled\ParameterValidationPlan(
				index: 0,
				name: 'unused',
				instructions: [
					new class () implements \Rokke\Runtime\Build\ValidationInstructionInterface {
						public mixed $received = null;

						public function validate(mixed $value, string $paramName): void
						{
							$this->received = $value;
						}
					},
				],
			),
		]);

		$op      = new CompiledOperation('op.a', 0, 0, 0, 0, validationPlanId: 0);
		$runtime = new CompiledRuntime(
			[],
			[0 => $handler],
			[0 => $this->emptyArgPlan()],
			[0 => $this->stringResultPlan()],
			OperationRepository::build([$op]),
			validationPlans: [0 => $fakePlan],
		);

		$invoker = new Invoker($runtime);
		$invoker->invoke($this->makeOperation('op.a'), $this->makeContext());

		$this->assertTrue($handlerCalled);
	}

	public function testValidationExceptionPreventsHandlerCall(): void
	{
		$handlerCalled = false;
		$handler       = function () use (&$handlerCalled): string {
			$handlerCalled = true;

			return 'core';
		};

		$throwingInstruction = new class () implements \Rokke\Runtime\Build\ValidationInstructionInterface {
			public function validate(mixed $value, string $paramName): void
			{
				throw new \Rokke\Runtime\Exception\ValidationException($paramName, 'must not be blank');
			}
		};

		$plan    = new \Rokke\Runtime\Compiled\ValidationPlan([
			new \Rokke\Runtime\Compiled\ParameterValidationPlan(0, 'name', [$throwingInstruction]),
		]);
		$op      = new CompiledOperation('op.a', 0, 0, 0, 0, validationPlanId: 0);
		$runtime = new CompiledRuntime(
			[],
			[0 => $handler],
			[0 => $this->emptyArgPlan()],
			[0 => $this->stringResultPlan()],
			OperationRepository::build([$op]),
			validationPlans: [0 => $plan],
		);

		$invoker = new Invoker($runtime);

		$this->expectException(\Rokke\Runtime\Exception\ValidationException::class);
		$invoker->invoke($this->makeOperation('op.a'), $this->makeContext());

		$this->assertFalse($handlerCalled);
	}
}
