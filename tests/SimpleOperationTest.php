<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Contracts\OperationInterface;
use Rokke\Runtime\SimpleOperation;

final class SimpleOperationTest extends TestCase
{
	public function testImplementsOperationInterface(): void
	{
		$this->assertInstanceOf(OperationInterface::class, new SimpleOperation('op.id'));
	}

	public function testIdReturnsConstructorValue(): void
	{
		$op = new SimpleOperation('users.show');

		$this->assertSame('users.show', $op->id());
	}

	public function testNameDefaultsToId(): void
	{
		$op = new SimpleOperation('users.show');

		$this->assertSame('users.show', $op->name());
	}

	public function testNameCanBeOverridden(): void
	{
		$op = new SimpleOperation('users.show', 'Show User');

		$this->assertSame('Show User', $op->name());
	}

	public function testMetadataReturnsDefaultWhenKeyAbsent(): void
	{
		$op = new SimpleOperation('op');

		$this->assertNull($op->metadata('missing'));
		$this->assertSame('fallback', $op->metadata('missing', 'fallback'));
	}
}
