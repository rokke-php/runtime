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

// ── Fixture handlers ──────────────────────────────────────────────────────────

function noArgsHandler(): string
{
	return 'ok';
}
function plainStringHandler(string $name): string
{
	return $name;
}
function notBlankHandler(#[NotBlank] string $name): string
{
	return $name;
}
function minHandler(#[Min(5)] int $value): int
{
	return $value;
}
function maxHandler(#[Max(10)] int $value): int
{
	return $value;
}
function multiAttrHandler(#[NotBlank] #[Min(1)] #[Max(99)] string $score): string
{
	return $score;
}
function multiParamHandler(#[NotBlank] string $name, #[Min(0)] int $age): string
{
	return "{$name}:{$age}";
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
		$plan = $this->compiler->compile('Rokke\Runtime\Tests\Build\noArgsHandler');

		$this->assertInstanceOf(ValidationPlan::class, $plan);
		$this->assertTrue($plan->isEmpty());
	}

	public function testParamWithNoAttributeProducesEmptyPlan(): void
	{
		$plan = $this->compiler->compile('Rokke\Runtime\Tests\Build\plainStringHandler');

		$this->assertTrue($plan->isEmpty());
	}

	public function testNotBlankPassesForNonEmptyString(): void
	{
		$plan = $this->compiler->compile('Rokke\Runtime\Tests\Build\notBlankHandler');

		$this->assertFalse($plan->isEmpty());
		$plan->validate(['Fernando']);
		// no exception = pass
	}

	public function testNotBlankThrowsForEmptyString(): void
	{
		$plan = $this->compiler->compile('Rokke\Runtime\Tests\Build\notBlankHandler');

		$this->expectException(ValidationException::class);
		$plan->validate(['']);
	}

	public function testNotBlankThrowsForWhitespaceOnlyString(): void
	{
		$plan = $this->compiler->compile('Rokke\Runtime\Tests\Build\notBlankHandler');

		$this->expectException(ValidationException::class);
		$plan->validate(['   ']);
	}

	public function testMinPassesAtThreshold(): void
	{
		$plan = $this->compiler->compile('Rokke\Runtime\Tests\Build\minHandler');

		$this->assertFalse($plan->isEmpty());
		$plan->validate([5]);
	}

	public function testMinThrowsBelowThreshold(): void
	{
		$plan = $this->compiler->compile('Rokke\Runtime\Tests\Build\minHandler');

		$this->expectException(ValidationException::class);
		$plan->validate([4]);
	}

	public function testMaxPassesAtThreshold(): void
	{
		$plan = $this->compiler->compile('Rokke\Runtime\Tests\Build\maxHandler');

		$this->assertFalse($plan->isEmpty());
		$plan->validate([10]);
	}

	public function testMaxThrowsAboveThreshold(): void
	{
		$plan = $this->compiler->compile('Rokke\Runtime\Tests\Build\maxHandler');

		$this->expectException(ValidationException::class);
		$plan->validate([11]);
	}

	public function testMultipleAttributesOnSameParam(): void
	{
		$plan = $this->compiler->compile('Rokke\Runtime\Tests\Build\multiAttrHandler');

		$this->assertFalse($plan->isEmpty());
		$plan->validate(['42']);
	}

	public function testMultipleAttributesFirstFailureThrows(): void
	{
		$plan = $this->compiler->compile('Rokke\Runtime\Tests\Build\multiAttrHandler');

		$this->expectException(ValidationException::class);
		$plan->validate(['']);
	}

	public function testMultipleParamsEachValidated(): void
	{
		$plan = $this->compiler->compile('Rokke\Runtime\Tests\Build\multiParamHandler');

		$this->assertFalse($plan->isEmpty());
		$plan->validate(['Alice', 30]);
	}

	public function testMultipleParamsSecondParamFailureThrows(): void
	{
		$plan = $this->compiler->compile('Rokke\Runtime\Tests\Build\multiParamHandler');

		$this->expectException(ValidationException::class);
		$plan->validate(['Alice', -1]);
	}

	public function testValidationExceptionCarriesParamName(): void
	{
		$plan = $this->compiler->compile('Rokke\Runtime\Tests\Build\notBlankHandler');

		try {
			$plan->validate(['']);
			$this->fail('Expected ValidationException');
		} catch (ValidationException $e) {
			$this->assertSame('name', $e->param);
		}
	}
}
