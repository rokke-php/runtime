<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Module;

use PHPUnit\Framework\TestCase;
use Rokke\Contracts\Module\CapabilityInterface;
use Rokke\Contracts\Module\ModuleBuilderInterface;
use Rokke\Contracts\Module\ModuleInterface;
use Rokke\Runtime\Module\ModuleBuilder;
use Rokke\Runtime\Module\ModuleSystem;

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class RecordingModule implements ModuleInterface
{
	/** @var list<ModuleBuilderInterface> */
	public array $receivedBuilders = [];

	public function register(ModuleBuilderInterface $builder): void
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

final class ModuleSystemTest extends TestCase
{
	private ModuleSystem $system;

	protected function setUp(): void
	{
		$this->system = new ModuleSystem();
	}

	public function testAllReturnsEmptyByDefault(): void
	{
		$this->assertSame([], $this->system->all());
	}

	public function testAllReturnsRegisteredModules(): void
	{
		$a = new RecordingModule();
		$b = new RecordingModule();

		$this->system->register($a);
		$this->system->register($b);

		$this->assertSame([$a, $b], $this->system->all());
	}

	public function testBuildAllCallsRegisterOnEachModule(): void
	{
		$a = new RecordingModule();
		$b = new RecordingModule();

		$this->system->register($a);
		$this->system->register($b);

		$builder = new ModuleBuilder();
		$this->system->buildAll($builder);

		$this->assertCount(1, $a->receivedBuilders);
		$this->assertCount(1, $b->receivedBuilders);
	}

	public function testBuildAllPassesSameBuilderInstanceToAllModules(): void
	{
		$a = new RecordingModule();
		$b = new RecordingModule();

		$this->system->register($a);
		$this->system->register($b);

		$builder = new ModuleBuilder();
		$this->system->buildAll($builder);

		$this->assertSame($builder, $a->receivedBuilders[0]);
		$this->assertSame($builder, $b->receivedBuilders[0]);
	}

	public function testBuildAllWithNoModulesIsNoOp(): void
	{
		$builder = new ModuleBuilder();
		$this->system->buildAll($builder);

		$this->assertSame([], $builder->getCapabilities());
	}

	public function testModuleCanAddCapabilitiesToBuilder(): void
	{
		$capability = new CapabilityStub('operation');

		$this->system->register(new class ($capability) implements ModuleInterface {
			public function __construct(private readonly CapabilityInterface $cap) {}

			public function register(ModuleBuilderInterface $builder): void
			{
				$builder->addCapability($this->cap);
			}
		});

		$builder = new ModuleBuilder();
		$this->system->buildAll($builder);

		$this->assertSame([$capability], $builder->getCapabilities());
	}
}
