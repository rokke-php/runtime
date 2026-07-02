<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Compiled\Results;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Compiled\Results\ScalarResultInstruction;

final class ScalarResultInstructionTest extends TestCase
{
	public function testResolveReturnsValueIntact(): void
	{
		$instr = new ScalarResultInstruction('string');

		$this->assertSame('hello', $instr->resolve('hello'));
	}

	public function testExposesScalarType(): void
	{
		$instr = new ScalarResultInstruction('int');

		$this->assertSame('int', $instr->scalarType);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('scalarTypeProvider')]
	public function testSupportedScalarTypes(string $type, mixed $value): void
	{
		$instr = new ScalarResultInstruction($type);

		$this->assertSame($value, $instr->resolve($value));
		$this->assertSame($type, $instr->scalarType);
	}

	/** @return array<string, array{string, mixed}> */
	public static function scalarTypeProvider(): array
	{
		return [
			'string' => ['string', 'hello'],
			'int'    => ['int', 42],
			'float'  => ['float', 3.14],
			'bool'   => ['bool', true],
			'array'  => ['array', ['a', 'b']],
		];
	}

	public function testResolveDoesNotTransformValue(): void
	{
		$instr = new ScalarResultInstruction('array');
		$data  = ['key' => 'value', 'nested' => [1, 2, 3]];

		$this->assertSame($data, $instr->resolve($data));
	}
}
