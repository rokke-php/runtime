<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Compiled\Results;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Compiled\Results\VoidResultInstruction;

final class VoidResultInstructionTest extends TestCase
{
	public function testResolveReturnsNull(): void
	{
		$instr = new VoidResultInstruction();

		$this->assertNull($instr->resolve(null));
	}

	public function testResolveIgnoresInput(): void
	{
		$instr = new VoidResultInstruction();

		$this->assertNull($instr->resolve('anything'));
		$this->assertNull($instr->resolve(42));
		$this->assertNull($instr->resolve(new \stdClass()));
	}
}
