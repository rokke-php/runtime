<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Resource;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Resource\PoolStats;

final class PoolStatsTest extends TestCase
{
	public function testPropertiesAreAccessible(): void
	{
		$stats = new PoolStats(
			name: 'db',
			max: 10,
			min: 2,
			currentTotal: 5,
			idle: 3,
			waitingCoroutines: 1,
			acquired: 100,
			created: 5,
			errors: 2,
			validationFails: 3,
			evicted: 4,
		);

		$this->assertSame('db', $stats->name);
		$this->assertSame(10, $stats->max);
		$this->assertSame(2, $stats->min);
		$this->assertSame(5, $stats->currentTotal);
		$this->assertSame(3, $stats->idle);
		$this->assertSame(1, $stats->waitingCoroutines);
		$this->assertSame(100, $stats->acquired);
		$this->assertSame(5, $stats->created);
		$this->assertSame(2, $stats->errors);
		$this->assertSame(3, $stats->validationFails);
		$this->assertSame(4, $stats->evicted);
	}

	public function testActiveCountDerivedFromTotalMinusIdle(): void
	{
		$stats = new PoolStats('db', 10, 0, 7, 4, 0, 0, 7, 0, 0, 0);

		$this->assertSame(3, $stats->active());
	}

	public function testActiveNeverNegative(): void
	{
		$stats = new PoolStats('db', 10, 0, 0, 0, 0, 0, 0, 0, 0, 0);

		$this->assertSame(0, $stats->active());
	}
}
