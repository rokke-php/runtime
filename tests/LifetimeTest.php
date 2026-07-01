<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Lifetime;

final class LifetimeTest extends TestCase
{
	public function testAllCasesAreDefined(): void
	{
		$cases = Lifetime::cases();

		$this->assertCount(4, $cases);
	}

	public function testSingletonCaseExists(): void
	{
		$this->assertContains(Lifetime::Singleton, Lifetime::cases());
	}

	public function testScopedCaseExists(): void
	{
		$this->assertContains(Lifetime::Scoped, Lifetime::cases());
	}

	public function testTransientCaseExists(): void
	{
		$this->assertContains(Lifetime::Transient, Lifetime::cases());
	}

	public function testPooledCaseExists(): void
	{
		$this->assertContains(Lifetime::Pooled, Lifetime::cases());
	}

	public function testCasesAreEqual(): void
	{
		$this->assertSame(Lifetime::Singleton, Lifetime::Singleton);
		$this->assertNotSame(Lifetime::Singleton, Lifetime::Transient);
	}
}
