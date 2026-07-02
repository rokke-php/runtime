<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Compiled\Results;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Compiled\Results\ObjectResultInstruction;
use Rokke\Runtime\Compiled\Results\ResultResolutionPlan;
use Rokke\Runtime\Compiled\Results\ScalarResultInstruction;
use Rokke\Runtime\Compiled\Results\VoidResultInstruction;

final class ResultResolutionPlanFixture {}

final class ResultResolutionPlanTest extends TestCase
{
	public function testResolveDelegatesToInstruction(): void
	{
		$plan = new ResultResolutionPlan(new ScalarResultInstruction('string'));

		$this->assertSame('hello', $plan->resolve('hello'));
	}

	public function testResolveWithVoidInstructionReturnsNull(): void
	{
		$plan = new ResultResolutionPlan(new VoidResultInstruction());

		$this->assertNull($plan->resolve(null));
	}

	public function testResolveWithObjectInstructionPassesThrough(): void
	{
		$instance = new ResultResolutionPlanFixture();
		$plan     = new ResultResolutionPlan(new ObjectResultInstruction(ResultResolutionPlanFixture::class));

		$this->assertSame($instance, $plan->resolve($instance));
	}

	public function testInstructionPropertyIsAccessible(): void
	{
		$instr = new ScalarResultInstruction('int');
		$plan  = new ResultResolutionPlan($instr);

		$this->assertSame($instr, $plan->instruction);
	}
}
