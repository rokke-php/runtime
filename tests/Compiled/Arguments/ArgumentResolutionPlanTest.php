<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Compiled\Arguments;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Build\CompiledFactory;
use Rokke\Runtime\Build\FactoryRepository;
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

		$this->assertSame([], $plan->resolveAll($ctx, FactoryRepository::fromDescriptors([])));
	}

	public function testSingleContextInstructionResolvesToContext(): void
	{
		$ctx  = $this->createStub(OperationContextInterface::class);
		$plan = new ArgumentResolutionPlan([new ContextArgumentInstruction()]);

		$this->assertSame([$ctx], $plan->resolveAll($ctx, FactoryRepository::fromDescriptors([])));
	}

	public function testSingleFactoryInstructionResolvesToCreatedInstance(): void
	{
		$factories = FactoryRepository::fromDescriptors([new CompiledFactory(\stdClass::class)]);
		$plan      = new ArgumentResolutionPlan([new FactoryArgumentInstruction(0)]);
		$ctx       = $this->createStub(OperationContextInterface::class);
		$result    = $plan->resolveAll($ctx, $factories);

		$this->assertCount(1, $result);
		$this->assertInstanceOf(\stdClass::class, $result[0]);
	}

	public function testMixedInstructionsPreserveOrder(): void
	{
		$factories = FactoryRepository::fromDescriptors([new CompiledFactory(\stdClass::class)]);
		$ctx       = $this->createStub(OperationContextInterface::class);

		$plan = new ArgumentResolutionPlan([
			new FactoryArgumentInstruction(0),
			new ContextArgumentInstruction(),
		]);

		[$resolvedDep, $resolvedCtx] = $plan->resolveAll($ctx, $factories);

		$this->assertInstanceOf(\stdClass::class, $resolvedDep);
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
