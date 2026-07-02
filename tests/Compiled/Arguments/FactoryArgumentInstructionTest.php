<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Compiled\Arguments;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Build\CompiledFactory;
use Rokke\Runtime\Compiled\Arguments\FactoryArgumentInstruction;
use Rokke\Runtime\Contracts\OperationContextInterface;

final class FactoryArgumentInstructionTest extends TestCase
{
	public function testResolveDelegatesToCompiledFactory(): void
	{
		$instance = new \stdClass();
		$factory  = new CompiledFactory(static fn (): object => $instance);
		$instr    = new FactoryArgumentInstruction($factory);
		$ctx      = $this->createStub(OperationContextInterface::class);

		$this->assertSame($instance, $instr->resolve($ctx));
	}

	public function testResolveIgnoresContext(): void
	{
		$a   = new \stdClass();
		$b   = new \stdClass();
		$idx = 0;
		$factory = new CompiledFactory(static function () use ($a, $b, &$idx): object {
			return $idx++ === 0 ? $a : $b;
		});
		$instr = new FactoryArgumentInstruction($factory);

		$ctx1 = $this->createStub(OperationContextInterface::class);
		$ctx2 = $this->createStub(OperationContextInterface::class);

		$this->assertSame($a, $instr->resolve($ctx1));
		$this->assertSame($b, $instr->resolve($ctx2));
	}

	public function testResolveCallsCreateEachTime(): void
	{
		$calls   = 0;
		$factory = new CompiledFactory(static function () use (&$calls): object {
			$calls++;
			return new \stdClass();
		});
		$instr = new FactoryArgumentInstruction($factory);
		$ctx   = $this->createStub(OperationContextInterface::class);

		$instr->resolve($ctx);
		$instr->resolve($ctx);

		$this->assertSame(2, $calls);
	}
}
