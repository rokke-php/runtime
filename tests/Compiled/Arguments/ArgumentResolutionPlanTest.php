<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Compiled\Arguments;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Build\CompiledFactory;
use Rokke\Runtime\Compiled\Arguments\ArgumentResolutionPlan;
use Rokke\Runtime\Compiled\Arguments\ContextArgumentInstruction;
use Rokke\Runtime\Compiled\Arguments\FactoryArgumentInstruction;
use Rokke\Runtime\Contracts\OperationContextInterface;

final class ArgumentResolutionPlanTest extends TestCase
{
	public function testEmptyPlanReturnsNoArguments(): void
	{
		$plan = new ArgumentResolutionPlan([]);
		$ctx  = $this->createStub(OperationContextInterface::class);

		$this->assertSame([], $plan->resolveAll($ctx));
	}

	public function testSingleContextInstructionResolvesToContext(): void
	{
		$ctx  = $this->createStub(OperationContextInterface::class);
		$plan = new ArgumentResolutionPlan([new ContextArgumentInstruction()]);

		$this->assertSame([$ctx], $plan->resolveAll($ctx));
	}

	public function testSingleFactoryInstructionResolvesToCreatedInstance(): void
	{
		$instance = new \stdClass();
		$factory  = new CompiledFactory(static fn (): object => $instance);
		$plan     = new ArgumentResolutionPlan([new FactoryArgumentInstruction($factory)]);
		$ctx      = $this->createStub(OperationContextInterface::class);

		$this->assertSame([$instance], $plan->resolveAll($ctx));
	}

	public function testMixedInstructionsPreserveOrder(): void
	{
		$dep = new \stdClass();
		$ctx = $this->createStub(OperationContextInterface::class);

		$plan = new ArgumentResolutionPlan([
			new FactoryArgumentInstruction(new CompiledFactory(static fn (): object => $dep)),
			new ContextArgumentInstruction(),
		]);

		[$resolvedDep, $resolvedCtx] = $plan->resolveAll($ctx);

		$this->assertSame($dep, $resolvedDep);
		$this->assertSame($ctx, $resolvedCtx);
	}

	public function testInstructionsPropertyIsAccessible(): void
	{
		$instr = new ContextArgumentInstruction();
		$plan  = new ArgumentResolutionPlan([$instr]);

		$this->assertCount(1, $plan->instructions);
		$this->assertSame($instr, $plan->instructions[0]);
	}
}
