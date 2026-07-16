<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Build;

use PHPUnit\Framework\TestCase;
use ReflectionNamedType;
use Rokke\Runtime\Build\ResultPlanCompiler;
use Rokke\Runtime\Build\ResultSourceCompilerInterface;
use Rokke\Runtime\Compiled\Results\NeverResultInstruction;
use Rokke\Runtime\Compiled\Results\ObjectResultInstruction;
use Rokke\Runtime\Compiled\Results\ResultInstructionInterface;
use Rokke\Runtime\Compiled\Results\ScalarResultInstruction;
use Rokke\Runtime\Compiled\Results\VoidResultInstruction;

// ── Value fixtures ─────────────────────────────────────────────────────────────

final class ResultPlanDto {}

final class StubResultInstruction implements ResultInstructionInterface
{
	public function resolve(mixed $value): mixed
	{
		return 'stub';
	}
}

final class StubResultSourceCompiler implements ResultSourceCompilerInterface
{
	public function compile(ReflectionNamedType $type): ?ResultInstructionInterface
	{
		if ($type->getName() === ResultPlanDto::class) {
			return new StubResultInstruction();
		}

		return null;
	}
}

// ── Handler fixtures ──────────────────────────────────────────────────────────

final class ResultStringHandler
{
	public function __invoke(): string
	{
		return 'ok';
	}
}

final class ResultIntHandler
{
	public function __invoke(): int
	{
		return 0;
	}
}

final class ResultFloatHandler
{
	public function __invoke(): float
	{
		return 0.0;
	}
}

final class ResultBoolHandler
{
	public function __invoke(): bool
	{
		return true;
	}
}

final class ResultArrayHandler
{
	public function __invoke(): array
	{
		return [];
	}
}

final class ResultDtoHandler
{
	public function __invoke(): ResultPlanDto
	{
		return new ResultPlanDto();
	}
}

final class ResultStdClassHandler
{
	public function __invoke(): \stdClass
	{
		return new \stdClass();
	}
}

final class ResultVoidHandler
{
	public function __invoke(): void {}
}

final class ResultNeverHandler
{
	public function __invoke(): never
	{
		throw new \RuntimeException();
	}
}

final class ResultNoTypeHandler
{
	public function __invoke()
	{
		return 'no type';
	}
}

final class ResultMixedHandler
{
	public function __invoke(): mixed
	{
		return null;
	}
}

final class ResultUnionHandler
{
	public function __invoke(): string|int
	{
		return 'x';
	}
}

// ── Tests ─────────────────────────────────────────────────────────────────────

final class ResultPlanCompilerTest extends TestCase
{
	private ResultPlanCompiler $compiler;

	protected function setUp(): void
	{
		$this->compiler = new ResultPlanCompiler();
	}

	// ── Scalar types ──────────────────────────────────────────────────────────

	public function testStringReturnTypeProducesScalarInstruction(): void
	{
		$plan = $this->compiler->compile(ResultStringHandler::class);

		$this->assertInstanceOf(ScalarResultInstruction::class, $plan->instruction);
		assert($plan->instruction instanceof ScalarResultInstruction);
		$this->assertSame('string', $plan->instruction->scalarType);
	}

	public function testIntReturnTypeProducesScalarInstruction(): void
	{
		$plan = $this->compiler->compile(ResultIntHandler::class);

		$this->assertInstanceOf(ScalarResultInstruction::class, $plan->instruction);
		assert($plan->instruction instanceof ScalarResultInstruction);
		$this->assertSame('int', $plan->instruction->scalarType);
	}

	public function testFloatReturnTypeProducesScalarInstruction(): void
	{
		$plan = $this->compiler->compile(ResultFloatHandler::class);

		$this->assertInstanceOf(ScalarResultInstruction::class, $plan->instruction);
		assert($plan->instruction instanceof ScalarResultInstruction);
		$this->assertSame('float', $plan->instruction->scalarType);
	}

	public function testBoolReturnTypeProducesScalarInstruction(): void
	{
		$plan = $this->compiler->compile(ResultBoolHandler::class);

		$this->assertInstanceOf(ScalarResultInstruction::class, $plan->instruction);
		assert($plan->instruction instanceof ScalarResultInstruction);
		$this->assertSame('bool', $plan->instruction->scalarType);
	}

	public function testArrayReturnTypeProducesScalarInstruction(): void
	{
		$plan = $this->compiler->compile(ResultArrayHandler::class);

		$this->assertInstanceOf(ScalarResultInstruction::class, $plan->instruction);
		assert($plan->instruction instanceof ScalarResultInstruction);
		$this->assertSame('array', $plan->instruction->scalarType);
	}

	// ── Object types ──────────────────────────────────────────────────────────

	public function testNamedClassReturnTypeProducesObjectInstruction(): void
	{
		$plan = $this->compiler->compile(ResultDtoHandler::class);

		$this->assertInstanceOf(ObjectResultInstruction::class, $plan->instruction);
		assert($plan->instruction instanceof ObjectResultInstruction);
		$this->assertSame(ResultPlanDto::class, $plan->instruction->contract);
	}

	public function testSelfReturnTypeProducesObjectInstruction(): void
	{
		$plan = $this->compiler->compile(ResultStdClassHandler::class);

		$this->assertInstanceOf(ObjectResultInstruction::class, $plan->instruction);
	}

	// ── Special types ─────────────────────────────────────────────────────────

	public function testVoidReturnTypeProducesVoidInstruction(): void
	{
		$plan = $this->compiler->compile(ResultVoidHandler::class);

		$this->assertInstanceOf(VoidResultInstruction::class, $plan->instruction);
	}

	public function testNeverReturnTypeProducesNeverInstruction(): void
	{
		$plan = $this->compiler->compile(ResultNeverHandler::class);

		$this->assertInstanceOf(NeverResultInstruction::class, $plan->instruction);
	}

	// ── Build failures ────────────────────────────────────────────────────────

	public function testNoReturnTypeThrowsAtCompileTime(): void
	{
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Output Contract');

		$this->compiler->compile(ResultNoTypeHandler::class);
	}

	public function testMixedReturnTypeThrowsAtCompileTime(): void
	{
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage("'mixed'");

		$this->compiler->compile(ResultMixedHandler::class);
	}

	public function testUnionReturnTypeThrowsAtCompileTime(): void
	{
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Union');

		$this->compiler->compile(ResultUnionHandler::class);
	}

	// ── Custom sources ────────────────────────────────────────────────────────

	public function testCustomSourceOverridesObjectInstruction(): void
	{
		$compiler = new ResultPlanCompiler([new StubResultSourceCompiler()]);
		$dto      = new ResultPlanDto();

		$plan = $compiler->compile(ResultDtoHandler::class);

		$this->assertInstanceOf(StubResultInstruction::class, $plan->instruction);
		$this->assertSame('stub', $plan->resolve($dto));
	}

	public function testCustomSourceReturningNullFallsThroughToBuiltin(): void
	{
		$compiler = new ResultPlanCompiler([new StubResultSourceCompiler()]);

		$plan = $compiler->compile(ResultStringHandler::class);

		$this->assertInstanceOf(ScalarResultInstruction::class, $plan->instruction);
	}

	public function testDefaultCompilerStillWorksWithNoSources(): void
	{
		$plan = $this->compiler->compile(ResultDtoHandler::class);

		$this->assertInstanceOf(ObjectResultInstruction::class, $plan->instruction);
	}

	// ── Plan resolution ───────────────────────────────────────────────────────

	public function testCompiledPlanResolvesValue(): void
	{
		$plan = $this->compiler->compile(ResultStringHandler::class);

		$this->assertSame('result', $plan->resolve('result'));
	}

	public function testCompiledPlanResolvesObject(): void
	{
		$dto  = new ResultPlanDto();
		$plan = $this->compiler->compile(ResultDtoHandler::class);

		$this->assertSame($dto, $plan->resolve($dto));
	}
}
