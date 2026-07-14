<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests;

use PHPUnit\Framework\TestCase;
use Rokke\Contracts\Extension\ExtensionBuilderInterface;
use Rokke\Contracts\Extension\ExtensionInterface;
use Rokke\Contracts\Module\CapabilityInterface;
use Rokke\Contracts\Module\DiscoveryProviderInterface;
use Rokke\Runtime\ApplicationKernel;
use Rokke\Runtime\Build\OperationCapability;
use Rokke\Runtime\Contracts\OperationContextInterface;

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class KernelServiceFixture {}

final class GreetExtension implements ExtensionInterface
{
	public function register(ExtensionBuilderInterface $builder): void
	{
		$builder->addCapability(new OperationCapability(
			id: 'greet',
			name: 'Greet',
			handler: static fn (): string => 'Hello Rokke',
		));
	}
}

final class MultiOpExtension implements ExtensionInterface
{
	public function register(ExtensionBuilderInterface $builder): void
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
		$kernel->register(new GreetExtension());
		$kernel->build();

		$result = $kernel->run('greet');

		$this->assertSame('Hello Rokke', $result);
	}

	public function testRunExecutesCorrectOperationAmongMultiple(): void
	{
		$kernel = new ApplicationKernel();
		$kernel->register(new MultiOpExtension());
		$kernel->build();

		$this->assertSame('result-a', $kernel->run('op.a'));
		$this->assertSame('result-b', $kernel->run('op.b'));
	}

	public function testBuildSucceedsWhenExtensionRegistersService(): void
	{
		$kernel = new ApplicationKernel();
		$kernel->register(new class () implements ExtensionInterface {
			public function register(ExtensionBuilderInterface $builder): void
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
		$kernel->register(new class () implements ExtensionInterface {
			public function register(ExtensionBuilderInterface $builder): void
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
		$kernel->register(new class () implements ExtensionInterface {
			public function register(ExtensionBuilderInterface $builder): void
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

	public function testBuildThrowsForHandlerWithNoReturnType(): void
	{
		$kernel = new ApplicationKernel();
		$kernel->register(new class () implements ExtensionInterface {
			public function register(ExtensionBuilderInterface $builder): void
			{
				$builder->addCapability(new OperationCapability(
					id: 'untyped',
					name: 'Untyped',
					handler: static fn () => 'no type',
				));
			}
		});

		$this->expectException(\RuntimeException::class);
		$kernel->build();
	}

	public function testVoidHandlerReturnsNull(): void
	{
		$kernel = new ApplicationKernel();
		$kernel->register(new class () implements ExtensionInterface {
			public function register(ExtensionBuilderInterface $builder): void
			{
				$builder->addCapability(new OperationCapability(
					id: 'void',
					name: 'Void',
					handler: static function (): void {},
				));
			}
		});
		$kernel->build();

		$this->assertNull($kernel->run('void'));
	}

	public function testHandlerReturningDtoPassesThroughUnmodified(): void
	{
		$expected = new KernelServiceFixture();
		$kernel   = new ApplicationKernel();
		$kernel->register(new class ($expected) implements ExtensionInterface {
			public function __construct(private readonly KernelServiceFixture $dto) {}

			public function register(ExtensionBuilderInterface $builder): void
			{
				$dto = $this->dto;
				$builder->addCapability(new OperationCapability(
					id: 'dto',
					name: 'DTO',
					handler: static function () use ($dto): KernelServiceFixture {
						return $dto;
					},
				));
			}
		});
		$kernel->build();

		$this->assertSame($expected, $kernel->run('dto'));
	}

	public function testDiscoveredCapabilitiesAreCompiledAndRunnable(): void
	{
		$kernel = new ApplicationKernel();
		$kernel->register(new class () implements ExtensionInterface {
			public function register(ExtensionBuilderInterface $builder): void
			{
				$builder->addDiscoveryProvider(new class () implements DiscoveryProviderInterface {
					/** @return list<CapabilityInterface> */
					public function discover(): array
					{
						return [
							new OperationCapability('discovered.op', 'Discovered', static fn (): string => 'from-discovery'),
						];
					}
				});
			}
		});
		$kernel->build();

		$this->assertSame('from-discovery', $kernel->run('discovered.op'));
	}

	public function testDiscoveredAndExplicitCapabilitiesCoexist(): void
	{
		$kernel = new ApplicationKernel();
		$kernel->register(new class () implements ExtensionInterface {
			public function register(ExtensionBuilderInterface $builder): void
			{
				$builder->addCapability(new OperationCapability('explicit.op', 'Explicit', static fn (): string => 'explicit'));
				$builder->addDiscoveryProvider(new class () implements DiscoveryProviderInterface {
					/** @return list<CapabilityInterface> */
					public function discover(): array
					{
						return [
							new OperationCapability('auto.op', 'Auto', static fn (): string => 'auto'),
						];
					}
				});
			}
		});
		$kernel->build();

		$this->assertSame('explicit', $kernel->run('explicit.op'));
		$this->assertSame('auto', $kernel->run('auto.op'));
	}

	public function testRegisterAccumulatesExtensionsBeforeBuild(): void
	{
		$kernel = new ApplicationKernel();

		$extA = new class () implements ExtensionInterface {
			public function register(ExtensionBuilderInterface $builder): void
			{
				$builder->addCapability(new OperationCapability('ext-a', 'A', static fn (): string => 'a'));
			}
		};

		$extB = new class () implements ExtensionInterface {
			public function register(ExtensionBuilderInterface $builder): void
			{
				$builder->addCapability(new OperationCapability('ext-b', 'B', static fn (): string => 'b'));
			}
		};

		$kernel->register($extA);
		$kernel->register($extB);
		$kernel->build();

		$this->assertSame('a', $kernel->run('ext-a'));
		$this->assertSame('b', $kernel->run('ext-b'));
	}
}
