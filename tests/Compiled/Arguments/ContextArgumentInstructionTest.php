<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Compiled\Arguments;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Build\FactoryRepository;
use Rokke\Runtime\Compiled\Arguments\ContextArgumentInstruction;
use Rokke\Runtime\Contracts\OperationContextInterface;

final class ContextArgumentInstructionTest extends TestCase
{
	private function emptyFactories(): FactoryRepository
	{
		return FactoryRepository::fromDescriptors([]);
	}

	public function testResolveReturnsContext(): void
	{
		$ctx   = $this->createStub(OperationContextInterface::class);
		$instr = new ContextArgumentInstruction();

		$this->assertSame($ctx, $instr->resolve($ctx, $this->emptyFactories()));
	}

	public function testResolveReturnsSameInstanceEachTime(): void
	{
		$ctx   = $this->createStub(OperationContextInterface::class);
		$instr = new ContextArgumentInstruction();
		$f     = $this->emptyFactories();

		$this->assertSame($instr->resolve($ctx, $f), $instr->resolve($ctx, $f));
	}

	public function testResolveDifferentContextsReturnRespectiveContext(): void
	{
		$ctx1  = $this->createStub(OperationContextInterface::class);
		$ctx2  = $this->createStub(OperationContextInterface::class);
		$instr = new ContextArgumentInstruction();
		$f     = $this->emptyFactories();

		$this->assertSame($ctx1, $instr->resolve($ctx1, $f));
		$this->assertSame($ctx2, $instr->resolve($ctx2, $f));
	}
}
