<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Build;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Build\ArgumentPlanCompiler;
use Rokke\Runtime\Build\FactoryCompiler;
use Rokke\Runtime\Build\FactoryRepository;
use Rokke\Runtime\Build\ServiceDescriptor;
use Rokke\Runtime\Compiled\Arguments\ContextArgumentInstruction;
use Rokke\Runtime\Compiled\Arguments\FactoryArgumentInstruction;
use Rokke\Runtime\Contracts\OperationContextInterface;

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class PlanCompilerDep {}

final class PlanCompilerService
{
	public function __construct(public readonly PlanCompilerDep $dep) {}
}

// ── Tests ─────────────────────────────────────────────────────────────────────

final class ArgumentPlanCompilerTest extends TestCase
{
	private FactoryRepository $emptyRepo;
	private FactoryRepository $repoWithDep;
	private ArgumentPlanCompiler $compiler;

	protected function setUp(): void
	{
		$this->emptyRepo   = FactoryRepository::empty();
		$this->repoWithDep = FactoryRepository::build(
			[new ServiceDescriptor(PlanCompilerDep::class, PlanCompilerDep::class, [PlanCompilerDep::class])],
			new FactoryCompiler(),
		);
		$this->compiler = new ArgumentPlanCompiler();
	}

	public function testNoParametersProducesEmptyPlan(): void
	{
		$plan = $this->compiler->compile(static fn (): string => 'ok', $this->emptyRepo);

		$this->assertCount(0, $plan->instructions);
	}

	public function testContextParameterProducesContextInstruction(): void
	{
		$plan = $this->compiler->compile(
			static fn (OperationContextInterface $ctx): string => 'ok',
			$this->emptyRepo,
		);

		$this->assertCount(1, $plan->instructions);
		$this->assertInstanceOf(ContextArgumentInstruction::class, $plan->instructions[0]);
	}

	public function testServiceParameterProducesFactoryInstruction(): void
	{
		$plan = $this->compiler->compile(
			static fn (PlanCompilerDep $dep): string => 'ok',
			$this->repoWithDep,
		);

		$this->assertCount(1, $plan->instructions);
		$this->assertInstanceOf(FactoryArgumentInstruction::class, $plan->instructions[0]);
	}

	public function testMixedParametersPreserveOrder(): void
	{
		$plan = $this->compiler->compile(
			static fn (PlanCompilerDep $dep, OperationContextInterface $ctx): string => 'ok',
			$this->repoWithDep,
		);

		$this->assertCount(2, $plan->instructions);
		$this->assertInstanceOf(FactoryArgumentInstruction::class, $plan->instructions[0]);
		$this->assertInstanceOf(ContextArgumentInstruction::class, $plan->instructions[1]);
	}

	public function testContextFirstThenServicePreservesOrder(): void
	{
		$plan = $this->compiler->compile(
			static fn (OperationContextInterface $ctx, PlanCompilerDep $dep): string => 'ok',
			$this->repoWithDep,
		);

		$this->assertCount(2, $plan->instructions);
		$this->assertInstanceOf(ContextArgumentInstruction::class, $plan->instructions[0]);
		$this->assertInstanceOf(FactoryArgumentInstruction::class, $plan->instructions[1]);
	}

	public function testUnregisteredServiceTypeThrowsAtCompileTime(): void
	{
		$this->expectException(\RuntimeException::class);

		$this->compiler->compile(
			static fn (PlanCompilerDep $dep): string => 'ok',
			$this->emptyRepo,
		);
	}

	public function testTypelessParameterThrowsAtCompileTime(): void
	{
		$this->expectException(\RuntimeException::class);

		$this->compiler->compile(
			static fn ($x): string => 'ok',
			$this->emptyRepo,
		);
	}

	public function testBuiltinTypeParameterThrowsAtCompileTime(): void
	{
		$this->expectException(\RuntimeException::class);

		$this->compiler->compile(
			static fn (string $name): string => 'ok',
			$this->emptyRepo,
		);
	}

	public function testFactoryInstructionResolvesCorrectDependency(): void
	{
		$plan = $this->compiler->compile(
			static fn (PlanCompilerDep $dep): string => 'ok',
			$this->repoWithDep,
		);

		$instr = $plan->instructions[0];
		$this->assertInstanceOf(FactoryArgumentInstruction::class, $instr);
		assert($instr instanceof FactoryArgumentInstruction);

		$ctx      = $this->createStub(OperationContextInterface::class);
		$resolved = $instr->resolve($ctx);
		$this->assertInstanceOf(PlanCompilerDep::class, $resolved);
	}
}
