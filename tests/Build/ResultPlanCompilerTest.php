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

// ── Fixtures ──────────────────────────────────────────────────────────────────

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
		$plan = $this->compiler->compile(static fn (): string => 'ok');

		$this->assertInstanceOf(ScalarResultInstruction::class, $plan->instruction);
		assert($plan->instruction instanceof ScalarResultInstruction);
		$this->assertSame('string', $plan->instruction->scalarType);
	}

	public function testIntReturnTypeProducesScalarInstruction(): void
	{
		$plan = $this->compiler->compile(static fn (): int => 0);

		$this->assertInstanceOf(ScalarResultInstruction::class, $plan->instruction);
		assert($plan->instruction instanceof ScalarResultInstruction);
		$this->assertSame('int', $plan->instruction->scalarType);
	}

	public function testFloatReturnTypeProducesScalarInstruction(): void
	{
		$plan = $this->compiler->compile(static fn (): float => 0.0);

		$this->assertInstanceOf(ScalarResultInstruction::class, $plan->instruction);
		assert($plan->instruction instanceof ScalarResultInstruction);
		$this->assertSame('float', $plan->instruction->scalarType);
	}

	public function testBoolReturnTypeProducesScalarInstruction(): void
	{
		$plan = $this->compiler->compile(static fn (): bool => true);

		$this->assertInstanceOf(ScalarResultInstruction::class, $plan->instruction);
		assert($plan->instruction instanceof ScalarResultInstruction);
		$this->assertSame('bool', $plan->instruction->scalarType);
	}

	public function testArrayReturnTypeProducesScalarInstruction(): void
	{
		$plan = $this->compiler->compile(static fn (): array => []);

		$this->assertInstanceOf(ScalarResultInstruction::class, $plan->instruction);
		assert($plan->instruction instanceof ScalarResultInstruction);
		$this->assertSame('array', $plan->instruction->scalarType);
	}

	// ── Object types ──────────────────────────────────────────────────────────

	public function testNamedClassReturnTypeProducesObjectInstruction(): void
	{
		$plan = $this->compiler->compile(static fn (): ResultPlanDto => new ResultPlanDto());

		$this->assertInstanceOf(ObjectResultInstruction::class, $plan->instruction);
		assert($plan->instruction instanceof ObjectResultInstruction);
		$this->assertSame(ResultPlanDto::class, $plan->instruction->contract);
	}

	public function testSelfReturnTypeProducesObjectInstruction(): void
	{
		$plan = $this->compiler->compile(static fn (): \stdClass => new \stdClass());

		$this->assertInstanceOf(ObjectResultInstruction::class, $plan->instruction);
	}

	// ── Special types ─────────────────────────────────────────────────────────

	public function testVoidReturnTypeProducesVoidInstruction(): void
	{
		$plan = $this->compiler->compile(static function (): void {});

		$this->assertInstanceOf(VoidResultInstruction::class, $plan->instruction);
	}

	public function testNeverReturnTypeProducesNeverInstruction(): void
	{
		$plan = $this->compiler->compile(static function (): never {
			throw new \RuntimeException();
		});

		$this->assertInstanceOf(NeverResultInstruction::class, $plan->instruction);
	}

	// ── Build failures ────────────────────────────────────────────────────────

	public function testNoReturnTypeThrowsAtCompileTime(): void
	{
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Output Contract');

		$this->compiler->compile(static fn () => 'no type');
	}

	public function testMixedReturnTypeThrowsAtCompileTime(): void
	{
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage("'mixed'");

		$this->compiler->compile(static fn (): mixed => null);
	}

	public function testUnionReturnTypeThrowsAtCompileTime(): void
	{
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Union');

		$fn = static function (): string|int {
			/** @var string|int */
			return 'x';
		};
		$this->compiler->compile($fn);
	}

	// ── Custom sources ────────────────────────────────────────────────────────

	public function testCustomSourceOverridesObjectInstruction(): void
	{
		$compiler = new ResultPlanCompiler([new StubResultSourceCompiler()]);
		$dto      = new ResultPlanDto();

		$plan = $compiler->compile(static fn (): ResultPlanDto => $dto);

		$this->assertInstanceOf(StubResultInstruction::class, $plan->instruction);
		$this->assertSame('stub', $plan->resolve($dto));
	}

	public function testCustomSourceReturningNullFallsThroughToBuiltin(): void
	{
		$compiler = new ResultPlanCompiler([new StubResultSourceCompiler()]);

		$plan = $compiler->compile(static fn (): string => 'ok');

		$this->assertInstanceOf(ScalarResultInstruction::class, $plan->instruction);
	}

	public function testDefaultCompilerStillWorksWithNoSources(): void
	{
		$plan = $this->compiler->compile(static fn (): ResultPlanDto => new ResultPlanDto());

		$this->assertInstanceOf(ObjectResultInstruction::class, $plan->instruction);
	}

	// ── Plan resolution ───────────────────────────────────────────────────────

	public function testCompiledPlanResolvesValue(): void
	{
		$plan = $this->compiler->compile(static fn (): string => 'ok');

		$this->assertSame('result', $plan->resolve('result'));
	}

	public function testCompiledPlanResolvesObject(): void
	{
		$dto  = new ResultPlanDto();
		$plan = $this->compiler->compile(static fn (): ResultPlanDto => $dto);

		$this->assertSame($dto, $plan->resolve($dto));
	}
}
