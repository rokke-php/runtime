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

// ── Shared fixture types ───────────────────────────────────────────────────────

final class KernelServiceFixture {}

// ── Handler fixtures ──────────────────────────────────────────────────────────

final class KernelGreetHandler
{
	public function __invoke(): string
	{
		return 'Hello Rokke';
	}
}

final class KernelOpAHandler
{
	public function __invoke(): string
	{
		return 'result-a';
	}
}

final class KernelOpBHandler
{
	public function __invoke(): string
	{
		return 'result-b';
	}
}

final class KernelServiceInjectHandler
{
	public function __invoke(KernelServiceFixture $svc): string
	{
		return 'got:' . $svc::class;
	}
}

final class KernelServiceAndContextHandler
{
	public function __invoke(KernelServiceFixture $svc, OperationContextInterface $ctx): string
	{
		return 'svc:' . $svc::class . '|ctx';
	}
}

final class KernelUntypedHandler
{
	public function __invoke()
	{
		return 'no type';
	}
}

final class KernelVoidHandler
{
	public function __invoke(): void {}
}

final class KernelDtoHandler
{
	public function __invoke(): KernelServiceFixture
	{
		return new KernelServiceFixture();
	}
}

final class KernelDiscoveredHandler
{
	public function __invoke(): string
	{
		return 'from-discovery';
	}
}

final class KernelExplicitHandler
{
	public function __invoke(): string
	{
		return 'explicit';
	}
}

final class KernelAutoHandler
{
	public function __invoke(): string
	{
		return 'auto';
	}
}

final class KernelExtAHandler
{
	public function __invoke(): string
	{
		return 'a';
	}
}

final class KernelExtBHandler
{
	public function __invoke(): string
	{
		return 'b';
	}
}

// ── Extension fixtures ────────────────────────────────────────────────────────

final class GreetExtension implements ExtensionInterface
{
	public function register(ExtensionBuilderInterface $builder): void
	{
		$builder->addCapability(new OperationCapability(
			id: 'greet',
			name: 'Greet',
			handler: KernelGreetHandler::class,
		));
	}
}

final class MultiOpExtension implements ExtensionInterface
{
	public function register(ExtensionBuilderInterface $builder): void
	{
		$builder->addCapability(new OperationCapability('op.a', 'A', KernelOpAHandler::class));
		$builder->addCapability(new OperationCapability('op.b', 'B', KernelOpBHandler::class));
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
					handler: KernelServiceInjectHandler::class,
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
					handler: KernelServiceAndContextHandler::class,
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
					handler: KernelUntypedHandler::class,
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
					handler: KernelVoidHandler::class,
				));
			}
		});
		$kernel->build();

		$this->assertNull($kernel->run('void'));
	}

	public function testHandlerReturningDtoPassesThroughUnmodified(): void
	{
		$kernel = new ApplicationKernel();
		$kernel->register(new class () implements ExtensionInterface {
			public function register(ExtensionBuilderInterface $builder): void
			{
				$builder->addCapability(new OperationCapability(
					id: 'dto',
					name: 'DTO',
					handler: KernelDtoHandler::class,
				));
			}
		});
		$kernel->build();

		$this->assertInstanceOf(KernelServiceFixture::class, $kernel->run('dto'));
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
							new OperationCapability('discovered.op', 'Discovered', KernelDiscoveredHandler::class),
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
				$builder->addCapability(new OperationCapability('explicit.op', 'Explicit', KernelExplicitHandler::class));
				$builder->addDiscoveryProvider(new class () implements DiscoveryProviderInterface {
					/** @return list<CapabilityInterface> */
					public function discover(): array
					{
						return [
							new OperationCapability('auto.op', 'Auto', KernelAutoHandler::class),
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
				$builder->addCapability(new OperationCapability('ext-a', 'A', KernelExtAHandler::class));
			}
		};

		$extB = new class () implements ExtensionInterface {
			public function register(ExtensionBuilderInterface $builder): void
			{
				$builder->addCapability(new OperationCapability('ext-b', 'B', KernelExtBHandler::class));
			}
		};

		$kernel->register($extA);
		$kernel->register($extB);
		$kernel->build();

		$this->assertSame('a', $kernel->run('ext-a'));
		$this->assertSame('b', $kernel->run('ext-b'));
	}
}
