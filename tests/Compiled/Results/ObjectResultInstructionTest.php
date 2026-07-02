<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Compiled\Results;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Compiled\Results\ObjectResultInstruction;

final class ObjectResultInstructionFixture {}

final class ObjectResultInstructionTest extends TestCase
{
	public function testResolveReturnsObjectIntact(): void
	{
		$instance = new ObjectResultInstructionFixture();
		$instr    = new ObjectResultInstruction(ObjectResultInstructionFixture::class);

		$this->assertSame($instance, $instr->resolve($instance));
	}

	public function testExposesContract(): void
	{
		$instr = new ObjectResultInstruction(ObjectResultInstructionFixture::class);

		$this->assertSame(ObjectResultInstructionFixture::class, $instr->contract);
	}

	public function testResolveDoesNotCloneOrWrapObject(): void
	{
		$instance = new \stdClass();
		$instr    = new ObjectResultInstruction(\stdClass::class);

		$resolved = $instr->resolve($instance);

		$this->assertSame($instance, $resolved);
	}
}
