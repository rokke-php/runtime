<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Build;

use PHPUnit\Framework\TestCase;
use ReflectionNamedType;
use ReflectionParameter;
use Rokke\Runtime\Build\ArgumentPlanCompiler;
use Rokke\Runtime\Build\ArgumentSourceCompilerInterface;
use Rokke\Runtime\Build\FactoryCompiler;
use Rokke\Runtime\Build\FactoryRepository;
use Rokke\Runtime\Build\ServiceDescriptor;
use Rokke\Runtime\Compiled\Arguments\ArgumentInstructionInterface;
use Rokke\Runtime\Compiled\Arguments\ContextArgumentInstruction;
use Rokke\Runtime\Compiled\Arguments\FactoryArgumentInstruction;
use Rokke\Runtime\Contracts\OperationContextInterface;

// ── Service fixtures ──────────────────────────────────────────────────────────

final class PlanCompilerDep {}

final class PlanCompilerService
{
	public function __construct(public readonly PlanCompilerDep $dep) {}
}

// ── Handler fixtures ──────────────────────────────────────────────────────────

final class ArgNoParamHandler
{
	public function __invoke(): string
	{
		return 'ok';
	}
}

final class ArgContextParamHandler
{
	public function __invoke(OperationContextInterface $ctx): string
	{
		return 'ok';
	}
}

final class ArgDepParamHandler
{
	public function __invoke(PlanCompilerDep $dep): string
	{
		return 'ok';
	}
}

final class ArgDepThenCtxHandler
{
	public function __invoke(PlanCompilerDep $dep, OperationContextInterface $ctx): string
	{
		return 'ok';
	}
}

final class ArgCtxThenDepHandler
{
	public function __invoke(OperationContextInterface $ctx, PlanCompilerDep $dep): string
	{
		return 'ok';
	}
}

final class ArgTypelessParamHandler
{
	public function __invoke($x): string
	{
		return 'ok';
	}
}

final class ArgBuiltinParamHandler
{
	public function __invoke(string $name): string
	{
		return $name;
	}
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
		$plan = $this->compiler->compile(ArgNoParamHandler::class, $this->emptyRepo);

		$this->assertCount(0, $plan->instructions);
	}

	public function testContextParameterProducesContextInstruction(): void
	{
		$plan = $this->compiler->compile(ArgContextParamHandler::class, $this->emptyRepo);

		$this->assertCount(1, $plan->instructions);
		$this->assertInstanceOf(ContextArgumentInstruction::class, $plan->instructions[0]);
	}

	public function testServiceParameterProducesFactoryInstruction(): void
	{
		$plan = $this->compiler->compile(ArgDepParamHandler::class, $this->repoWithDep);

		$this->assertCount(1, $plan->instructions);
		$this->assertInstanceOf(FactoryArgumentInstruction::class, $plan->instructions[0]);
	}

	public function testMixedParametersPreserveOrder(): void
	{
		$plan = $this->compiler->compile(ArgDepThenCtxHandler::class, $this->repoWithDep);

		$this->assertCount(2, $plan->instructions);
		$this->assertInstanceOf(FactoryArgumentInstruction::class, $plan->instructions[0]);
		$this->assertInstanceOf(ContextArgumentInstruction::class, $plan->instructions[1]);
	}

	public function testContextFirstThenServicePreservesOrder(): void
	{
		$plan = $this->compiler->compile(ArgCtxThenDepHandler::class, $this->repoWithDep);

		$this->assertCount(2, $plan->instructions);
		$this->assertInstanceOf(ContextArgumentInstruction::class, $plan->instructions[0]);
		$this->assertInstanceOf(FactoryArgumentInstruction::class, $plan->instructions[1]);
	}

	public function testUnregisteredServiceTypeThrowsAtCompileTime(): void
	{
		$this->expectException(\RuntimeException::class);

		$this->compiler->compile(ArgDepParamHandler::class, $this->emptyRepo);
	}

	public function testTypelessParameterThrowsAtCompileTime(): void
	{
		$this->expectException(\RuntimeException::class);

		$this->compiler->compile(ArgTypelessParamHandler::class, $this->emptyRepo);
	}

	public function testBuiltinTypeParameterThrowsAtCompileTime(): void
	{
		$this->expectException(\RuntimeException::class);

		$this->compiler->compile(ArgBuiltinParamHandler::class, $this->emptyRepo);
	}

	public function testCustomSourceCanResolveBuiltinType(): void
	{
		$instruction = new class () implements ArgumentInstructionInterface {
			public function resolve(OperationContextInterface $context): mixed
			{
				return 'custom';
			}
		};

		$source = new class ($instruction) implements ArgumentSourceCompilerInterface {
			public function __construct(private readonly ArgumentInstructionInterface $instr) {}

			public function compile(ReflectionParameter $param, FactoryRepository $factories): ?ArgumentInstructionInterface
			{
				$type = $param->getType();

				return $type instanceof ReflectionNamedType && $type->getName() === 'string'
					? $this->instr
					: null;
			}
		};

		$compiler = new ArgumentPlanCompiler([$source]);
		$plan     = $compiler->compile(ArgBuiltinParamHandler::class, $this->emptyRepo);

		$this->assertCount(1, $plan->instructions);
		$this->assertSame($instruction, $plan->instructions[0]);
	}

	public function testCustomSourceReturningNullFallsThroughToDefaults(): void
	{
		$source = new class () implements ArgumentSourceCompilerInterface {
			public function compile(ReflectionParameter $param, FactoryRepository $factories): ?ArgumentInstructionInterface
			{
				return null;
			}
		};

		$compiler = new ArgumentPlanCompiler([$source]);
		$plan     = $compiler->compile(ArgContextParamHandler::class, $this->emptyRepo);

		$this->assertCount(1, $plan->instructions);
		$this->assertInstanceOf(ContextArgumentInstruction::class, $plan->instructions[0]);
	}

	public function testFirstMatchingSourceWins(): void
	{
		$instrA = new class () implements ArgumentInstructionInterface {
			public function resolve(OperationContextInterface $context): mixed
			{
				return 'a';
			}
		};
		$instrB = new class () implements ArgumentInstructionInterface {
			public function resolve(OperationContextInterface $context): mixed
			{
				return 'b';
			}
		};

		$sourceA = new class ($instrA) implements ArgumentSourceCompilerInterface {
			public function __construct(private readonly ArgumentInstructionInterface $instr) {}

			public function compile(ReflectionParameter $param, FactoryRepository $factories): ?ArgumentInstructionInterface
			{
				return $this->instr;
			}
		};
		$sourceB = new class ($instrB) implements ArgumentSourceCompilerInterface {
			public function __construct(private readonly ArgumentInstructionInterface $instr) {}

			public function compile(ReflectionParameter $param, FactoryRepository $factories): ?ArgumentInstructionInterface
			{
				return $this->instr;
			}
		};

		$compiler = new ArgumentPlanCompiler([$sourceA, $sourceB]);
		$plan     = $compiler->compile(ArgContextParamHandler::class, $this->emptyRepo);

		$this->assertSame($instrA, $plan->instructions[0]);
	}

	public function testFactoryInstructionResolvesCorrectDependency(): void
	{
		$plan = $this->compiler->compile(ArgDepParamHandler::class, $this->repoWithDep);

		$instr = $plan->instructions[0];
		$this->assertInstanceOf(FactoryArgumentInstruction::class, $instr);
		assert($instr instanceof FactoryArgumentInstruction);

		$ctx      = $this->createStub(OperationContextInterface::class);
		$resolved = $instr->resolve($ctx);
		$this->assertInstanceOf(PlanCompilerDep::class, $resolved);
	}
}
