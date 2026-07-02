<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests;

use PHPUnit\Framework\TestCase;
use Rokke\Contracts\Module\ModuleBuilderInterface;
use Rokke\Contracts\Module\ModuleInterface;
use Rokke\Runtime\ApplicationKernel;
use Rokke\Runtime\Build\OperationCapability;
use Rokke\Runtime\Contracts\OperationContextInterface;

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class KernelServiceFixture {}

final class GreetModule implements ModuleInterface
{
	public function register(ModuleBuilderInterface $builder): void
	{
		$builder->addCapability(new OperationCapability(
			id: 'greet',
			name: 'Greet',
			handler: static fn (): string => 'Hello Rokke',
		));
	}
}

final class MultiOpModule implements ModuleInterface
{
	public function register(ModuleBuilderInterface $builder): void
	{
		$builder->addCapability(new OperationCapability('op.a', 'A', static fn (): string => 'result-a'));
		$builder->addCapability(new OperationCapability('op.b', 'B', static fn (): string => 'result-b'));
	}
}

// ── Tests ─────────────────────────────────────────────────────────────────────

final class ApplicationKernelTest extends TestCase
{
	public function testRunBeforeBuildThrows(): void
	{
		$kernel = new ApplicationKernel();

		$this->expectException(\RuntimeException::class);

		$kernel->run('anything');
	}

	public function testRunUnknownOperationThrows(): void
	{
		$kernel = new ApplicationKernel();
		$kernel->build();

		$this->expectException(\RuntimeException::class);

		$kernel->run('does.not.exist');
	}

	public function testRunExecutesRegisteredOperation(): void
	{
		$kernel = new ApplicationKernel();
		$kernel->register(new GreetModule());
		$kernel->build();

		$result = $kernel->run('greet');

		$this->assertSame('Hello Rokke', $result);
	}

	public function testRunExecutesCorrectOperationAmongMultiple(): void
	{
		$kernel = new ApplicationKernel();
		$kernel->register(new MultiOpModule());
		$kernel->build();

		$this->assertSame('result-a', $kernel->run('op.a'));
		$this->assertSame('result-b', $kernel->run('op.b'));
	}

	public function testBuildSucceedsWhenModuleRegistersService(): void
	{
		$kernel = new ApplicationKernel();
		$kernel->register(new class () implements ModuleInterface {
			public function register(ModuleBuilderInterface $builder): void
			{
				$builder->service(KernelServiceFixture::class);
			}
		});

		$kernel->build();

		$this->expectException(\RuntimeException::class);
		$kernel->run('no.operation');
	}

	public function testHandlerReceivesInjectedService(): void
	{
		$kernel = new ApplicationKernel();
		$kernel->register(new class () implements ModuleInterface {
			public function register(ModuleBuilderInterface $builder): void
			{
				$builder->service(KernelServiceFixture::class);
				$builder->addCapability(new OperationCapability(
					id: 'inject',
					name: 'Inject',
					handler: static function (KernelServiceFixture $svc): string {
						return 'got:' . $svc::class;
					},
				));
			}
		});
		$kernel->build();

		$this->assertSame('got:' . KernelServiceFixture::class, $kernel->run('inject'));
	}

	public function testHandlerWithBothServiceAndContextReceivesBoth(): void
	{
		$kernel = new ApplicationKernel();
		$kernel->register(new class () implements ModuleInterface {
			public function register(ModuleBuilderInterface $builder): void
			{
				$builder->service(KernelServiceFixture::class);
				$builder->addCapability(new OperationCapability(
					id: 'both',
					name: 'Both',
					handler: static function (KernelServiceFixture $svc, OperationContextInterface $ctx): string {
						return 'svc:' . $svc::class . '|ctx';
					},
				));
			}
		});
		$kernel->build();

		$this->assertSame('svc:' . KernelServiceFixture::class . '|ctx', $kernel->run('both'));
	}

	public function testRegisterAccumulatesModulesBeforeBuild(): void
	{
		$kernel = new ApplicationKernel();

		$modA = new class () implements ModuleInterface {
			public function register(ModuleBuilderInterface $builder): void
			{
				$builder->addCapability(new OperationCapability('mod-a', 'A', static fn (): string => 'a'));
			}
		};

		$modB = new class () implements ModuleInterface {
			public function register(ModuleBuilderInterface $builder): void
			{
				$builder->addCapability(new OperationCapability('mod-b', 'B', static fn (): string => 'b'));
			}
		};

		$kernel->register($modA);
		$kernel->register($modB);
		$kernel->build();

		$this->assertSame('a', $kernel->run('mod-a'));
		$this->assertSame('b', $kernel->run('mod-b'));
	}
}
