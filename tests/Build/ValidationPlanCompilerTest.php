<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Build;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Attribute\Max;
use Rokke\Runtime\Attribute\Min;
use Rokke\Runtime\Attribute\NotBlank;
use Rokke\Runtime\Build\MaxValidationSourceCompiler;
use Rokke\Runtime\Build\MinValidationSourceCompiler;
use Rokke\Runtime\Build\NotBlankValidationSourceCompiler;
use Rokke\Runtime\Build\ValidationPlanCompiler;
use Rokke\Runtime\Compiled\ValidationPlan;
use Rokke\Runtime\Exception\ValidationException;

// ── Handler fixtures ──────────────────────────────────────────────────────────

final class ValNoArgsHandler
{
	public function __invoke(): string
	{
		return 'ok';
	}
}

final class ValPlainStringHandler
{
	public function __invoke(string $name): string
	{
		return $name;
	}
}

final class ValNotBlankHandler
{
	public function __invoke(#[NotBlank] string $name): string
	{
		return $name;
	}
}

final class ValMinHandler
{
	public function __invoke(#[Min(5)] int $value): int
	{
		return $value;
	}
}

final class ValMaxHandler
{
	public function __invoke(#[Max(10)] int $value): int
	{
		return $value;
	}
}

final class ValMultiAttrHandler
{
	public function __invoke(#[NotBlank] #[Min(1)] #[Max(99)] string $score): string
	{
		return $score;
	}
}

final class ValMultiParamHandler
{
	public function __invoke(#[NotBlank] string $name, #[Min(0)] int $age): string
	{
		return "{$name}:{$age}";
	}
}

// ── Tests ─────────────────────────────────────────────────────────────────────

final class ValidationPlanCompilerTest extends TestCase
{
	private ValidationPlanCompiler $compiler;

	protected function setUp(): void
	{
		$this->compiler = new ValidationPlanCompiler([
			new NotBlankValidationSourceCompiler(),
			new MinValidationSourceCompiler(),
			new MaxValidationSourceCompiler(),
		]);
	}

	public function testEmptyHandlerProducesEmptyPlan(): void
	{
		$plan = $this->compiler->compile(ValNoArgsHandler::class);

		$this->assertInstanceOf(ValidationPlan::class, $plan);
		$this->assertTrue($plan->isEmpty());
	}

	public function testParamWithNoAttributeProducesEmptyPlan(): void
	{
		$plan = $this->compiler->compile(ValPlainStringHandler::class);

		$this->assertTrue($plan->isEmpty());
	}

	public function testNotBlankPassesForNonEmptyString(): void
	{
		$plan = $this->compiler->compile(ValNotBlankHandler::class);

		$this->assertFalse($plan->isEmpty());
		$plan->validate(['Fernando']);
	}

	public function testNotBlankThrowsForEmptyString(): void
	{
		$plan = $this->compiler->compile(ValNotBlankHandler::class);

		$this->expectException(ValidationException::class);
		$plan->validate(['']);
	}

	public function testNotBlankThrowsForWhitespaceOnlyString(): void
	{
		$plan = $this->compiler->compile(ValNotBlankHandler::class);

		$this->expectException(ValidationException::class);
		$plan->validate(['   ']);
	}

	public function testMinPassesAtThreshold(): void
	{
		$plan = $this->compiler->compile(ValMinHandler::class);

		$this->assertFalse($plan->isEmpty());
		$plan->validate([5]);
	}

	public function testMinThrowsBelowThreshold(): void
	{
		$plan = $this->compiler->compile(ValMinHandler::class);

		$this->expectException(ValidationException::class);
		$plan->validate([4]);
	}

	public function testMaxPassesAtThreshold(): void
	{
		$plan = $this->compiler->compile(ValMaxHandler::class);

		$this->assertFalse($plan->isEmpty());
		$plan->validate([10]);
	}

	public function testMaxThrowsAboveThreshold(): void
	{
		$plan = $this->compiler->compile(ValMaxHandler::class);

		$this->expectException(ValidationException::class);
		$plan->validate([11]);
	}

	public function testMultipleAttributesOnSameParam(): void
	{
		$plan = $this->compiler->compile(ValMultiAttrHandler::class);

		$this->assertFalse($plan->isEmpty());
		$plan->validate(['42']);
	}

	public function testMultipleAttributesFirstFailureThrows(): void
	{
		$plan = $this->compiler->compile(ValMultiAttrHandler::class);

		$this->expectException(ValidationException::class);
		$plan->validate(['']);
	}

	public function testMultipleParamsEachValidated(): void
	{
		$plan = $this->compiler->compile(ValMultiParamHandler::class);

		$this->assertFalse($plan->isEmpty());
		$plan->validate(['Alice', 30]);
	}

	public function testMultipleParamsSecondParamFailureThrows(): void
	{
		$plan = $this->compiler->compile(ValMultiParamHandler::class);

		$this->expectException(ValidationException::class);
		$plan->validate(['Alice', -1]);
	}

	public function testValidationExceptionCarriesParamName(): void
	{
		$plan = $this->compiler->compile(ValNotBlankHandler::class);

		try {
			$plan->validate(['']);
			$this->fail('Expected ValidationException');
		} catch (ValidationException $e) {
			$this->assertSame('name', $e->param);
		}
	}
}
