<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Resource;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Resource\PoolConfig;
use Rokke\Runtime\Resource\ResourceValidatorInterface;

final class PoolConfigTest extends TestCase
{
	public function testDefaultValues(): void
	{
		$config = new PoolConfig('db');

		$this->assertSame('db', $config->name);
		$this->assertSame(0, $config->min);
		$this->assertSame(10, $config->max);
		$this->assertSame(3000, $config->acquireTimeout);
		$this->assertSame(0, $config->maxAge);
		$this->assertNull($config->validator);
	}

	public function testCustomValues(): void
	{
		$validator = $this->createStub(ResourceValidatorInterface::class);

		$config = new PoolConfig(
			name: 'redis',
			min: 2,
			max: 20,
			acquireTimeout: 5000,
			maxAge: 3600,
			validator: $validator,
		);

		$this->assertSame('redis', $config->name);
		$this->assertSame(2, $config->min);
		$this->assertSame(20, $config->max);
		$this->assertSame(5000, $config->acquireTimeout);
		$this->assertSame(3600, $config->maxAge);
		$this->assertSame($validator, $config->validator);
	}
}
