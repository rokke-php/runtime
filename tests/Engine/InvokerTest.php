<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Engine;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Compiled\CompiledOperation;
use Rokke\Runtime\Compiled\CompiledRuntime;
use Rokke\Runtime\Contracts\OperationContextInterface;
use Rokke\Runtime\Contracts\OperationInterface;
use Rokke\Runtime\Engine\Invoker;

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
		$runtime = new CompiledRuntime([], [], [], [], ['op.a' => $op]);
		$invoker = new Invoker($runtime);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Handler #99 not found');

		$invoker->invoke($this->makeOperation('op.a'), $this->makeContext());
	}

	public function testInvokesHandlerAndReturnsResult(): void
	{
		$handler = fn (OperationContextInterface $ctx): string => 'hello';

		$op      = new CompiledOperation(0, 0, 0, 0);
		$runtime = new CompiledRuntime([], [0 => $handler], [], [], ['op.a' => $op]);
		$invoker = new Invoker($runtime);

		$result = $invoker->invoke($this->makeOperation('op.a'), $this->makeContext());

		$this->assertSame('hello', $result);
	}

	public function testHandlerReceivesContext(): void
	{
		$ctx      = $this->makeContext();
		$received = null;

		$handler = function (OperationContextInterface $c) use (&$received): void {
			$received = $c;
		};

		$op      = new CompiledOperation(0, 0, 0, 0);
		$runtime = new CompiledRuntime([], [0 => $handler], [], [], ['op.a' => $op]);
		$invoker = new Invoker($runtime);

		$invoker->invoke($this->makeOperation('op.a'), $ctx);

		$this->assertSame($ctx, $received);
	}

	public function testHandlerIsResolvedByCompiledHandlerId(): void
	{
		$wrong  = fn (): string => 'wrong';
		$right  = fn (): string => 'right';

		$op      = new CompiledOperation(0, 1, 0, 0);
		$runtime = new CompiledRuntime([], [0 => $wrong, 1 => $right], [], [], ['op.a' => $op]);
		$invoker = new Invoker($runtime);

		$result = $invoker->invoke($this->makeOperation('op.a'), $this->makeContext());

		$this->assertSame('right', $result);
	}
}
