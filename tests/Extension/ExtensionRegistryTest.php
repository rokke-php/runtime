<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Extension;

use PHPUnit\Framework\TestCase;
use Rokke\Contracts\Extension\ExtensionBuildInterface;
use Rokke\Contracts\Extension\ExtensionBuilderInterface;
use Rokke\Contracts\Extension\ExtensionInterface;
use Rokke\Contracts\Module\CapabilityInterface;
use Rokke\Runtime\Build\ApplicationModel;
use Rokke\Runtime\Build\ExtensionBuildPassInterface;
use Rokke\Runtime\Extension\ExtensionBuilder;
use Rokke\Runtime\Extension\ExtensionRegistry;

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class RecordingExtension implements ExtensionInterface
{
	/** @var list<ExtensionBuilderInterface> */
	public array $receivedBuilders = [];

	public function register(ExtensionBuilderInterface $builder): void
	{
		$this->receivedBuilders[] = $builder;
	}
}

final class CapabilityStub implements CapabilityInterface
{
	public function __construct(private readonly string $typeName = 'stub') {}

	public function type(): string
	{
		return $this->typeName;
	}

	public function descriptor(): mixed
	{
		return null;
	}
}

// ── Tests ────────────────────────────────────────────────────────────────────

final class ExtensionRegistryTest extends TestCase
{
	private ExtensionRegistry $registry;

	protected function setUp(): void
	{
		$this->registry = new ExtensionRegistry();
	}

	public function testAllReturnsEmptyByDefault(): void
	{
		$this->assertSame([], $this->registry->all());
	}

	public function testAllReturnsRegisteredExtensions(): void
	{
		$a = new RecordingExtension();
		$b = new RecordingExtension();

		$this->registry->register($a);
		$this->registry->register($b);

		$this->assertSame([$a, $b], $this->registry->all());
	}

	public function testBuildAllCallsRegisterOnEachExtension(): void
	{
		$a = new RecordingExtension();
		$b = new RecordingExtension();

		$this->registry->register($a);
		$this->registry->register($b);

		$builder = new ExtensionBuilder();
		$this->registry->buildAll($builder);

		$this->assertCount(1, $a->receivedBuilders);
		$this->assertCount(1, $b->receivedBuilders);
	}

	public function testBuildAllPassesSameBuilderInstanceToAllExtensions(): void
	{
		$a = new RecordingExtension();
		$b = new RecordingExtension();

		$this->registry->register($a);
		$this->registry->register($b);

		$builder = new ExtensionBuilder();
		$this->registry->buildAll($builder);

		$this->assertSame($builder, $a->receivedBuilders[0]);
		$this->assertSame($builder, $b->receivedBuilders[0]);
	}

	public function testBuildAllWithNoExtensionsIsNoOp(): void
	{
		$builder = new ExtensionBuilder();
		$this->registry->buildAll($builder);

		$this->assertSame([], $builder->getCapabilities());
	}

	public function testExtensionCanAddCapabilitiesToBuilder(): void
	{
		$capability = new CapabilityStub('operation');

		$this->registry->register(new class ($capability) implements ExtensionInterface {
			public function __construct(private readonly CapabilityInterface $cap) {}

			public function register(ExtensionBuilderInterface $builder): void
			{
				$builder->addCapability($this->cap);
			}
		});

		$builder = new ExtensionBuilder();
		$this->registry->buildAll($builder);

		$this->assertSame([$capability], $builder->getCapabilities());
	}

	public function testGetBuildPassesEmptyWhenNoExtensionImplementsBuildInterface(): void
	{
		$this->registry->register(new RecordingExtension());

		$builder = new ExtensionBuilder();
		$this->registry->buildAll($builder);

		$this->assertSame([], $this->registry->getBuildPasses());
	}

	public function testGetBuildPassesCollectsFromExtensionBuildInterface(): void
	{
		$pass = new class () implements ExtensionBuildPassInterface {
			public function process(ApplicationModel $model): array { return []; }
		};

		$extension = new class ($pass) implements ExtensionInterface, ExtensionBuildInterface {
			public function __construct(private readonly ExtensionBuildPassInterface $pass) {}

			public function register(ExtensionBuilderInterface $builder): void {}

			public function buildPasses(): iterable { return [$this->pass]; }
		};

		$this->registry->register($extension);

		$this->assertSame([$pass], $this->registry->getBuildPasses());
	}

	public function testGetBuildPassesCollectsFromMultipleExtensions(): void
	{
		$passA = new class () implements ExtensionBuildPassInterface {
			public function process(ApplicationModel $model): array { return []; }
		};
		$passB = new class () implements ExtensionBuildPassInterface {
			public function process(ApplicationModel $model): array { return []; }
		};

		$extA = new class ($passA) implements ExtensionInterface, ExtensionBuildInterface {
			public function __construct(private readonly ExtensionBuildPassInterface $pass) {}
			public function register(ExtensionBuilderInterface $builder): void {}
			public function buildPasses(): iterable { return [$this->pass]; }
		};

		$extB = new class ($passB) implements ExtensionInterface, ExtensionBuildInterface {
			public function __construct(private readonly ExtensionBuildPassInterface $pass) {}
			public function register(ExtensionBuilderInterface $builder): void {}
			public function buildPasses(): iterable { return [$this->pass]; }
		};

		$this->registry->register($extA);
		$this->registry->register($extB);

		$this->assertSame([$passA, $passB], $this->registry->getBuildPasses());
	}

	public function testGetBuildPassesSkipsExtensionsThatDontImplementBuildInterface(): void
	{
		$pass = new class () implements ExtensionBuildPassInterface {
			public function process(ApplicationModel $model): array { return []; }
		};

		$withBuild = new class ($pass) implements ExtensionInterface, ExtensionBuildInterface {
			public function __construct(private readonly ExtensionBuildPassInterface $pass) {}
			public function register(ExtensionBuilderInterface $builder): void {}
			public function buildPasses(): iterable { return [$this->pass]; }
		};

		$this->registry->register(new RecordingExtension()); // no ExtensionBuildInterface
		$this->registry->register($withBuild);

		$this->assertSame([$pass], $this->registry->getBuildPasses());
	}
}
