<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Compiled\Results;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Compiled\Results\NeverResultInstruction;

final class NeverResultInstructionTest extends TestCase
{
	public function testResolveIsUnreachableAndThrowsLogicException(): void
	{
		$instr = new NeverResultInstruction();

		$this->expectException(\LogicException::class);

		$instr->resolve(null);
	}
}
