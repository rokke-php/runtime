<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Builder;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Application;
use Rokke\Runtime\Builder\ApplicationBuilder;
use Rokke\Runtime\Contracts\HostInterface;

final class ApplicationBuilderTest extends TestCase
{
	public function testDefaultHostIsLoopback(): void
	{
		$ref       = new \ReflectionMethod(ApplicationBuilder::class, 'create');
		$hostParam = $ref->getParameters()[0];

		$this->assertSame('127.0.0.1', $hostParam->getDefaultValue());
	}

	public function testDefaultPortIs8000(): void
	{
		$ref       = new \ReflectionMethod(ApplicationBuilder::class, 'create');
		$portParam = $ref->getParameters()[1];

		$this->assertSame(8000, $portParam->getDefaultValue());
	}

	public function testCreateReturnsApplicationInstance(): void
	{
		if (!extension_loaded('swoole')) {
			$this->markTestSkipped('Swoole extension is required.');
		}

		$app = ApplicationBuilder::create();

		$this->assertInstanceOf(Application::class, $app);
	}

	public function testCreateBindsHostInterfaceInContainer(): void
	{
		if (!extension_loaded('swoole')) {
			$this->markTestSkipped('Swoole extension is required.');
		}

		$app  = ApplicationBuilder::create();
		$host = $app->container()->get(HostInterface::class);

		$this->assertInstanceOf(HostInterface::class, $host);
	}

	public function testCreateWithCustomHostAndPort(): void
	{
		if (!extension_loaded('swoole')) {
			$this->markTestSkipped('Swoole extension is required.');
		}

		$app = ApplicationBuilder::create('127.0.0.1', 9000);

		$this->assertInstanceOf(Application::class, $app);
	}
}
