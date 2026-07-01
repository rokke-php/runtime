<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Build;

use PHPUnit\Framework\TestCase;
use Rokke\Contracts\Module\CapabilityInterface;
use Rokke\Runtime\Build\ApplicationModel;
use Rokke\Runtime\Build\ModelBuilder;
use Rokke\Runtime\Build\ModelBuilderPassInterface;

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class CapabilityFixture implements CapabilityInterface {}

final class ModelCapturingPass implements ModelBuilderPassInterface
{
	/** @var list<ApplicationModel> */
	public array $capturedModels = [];

	/** @param list<CapabilityInterface> $capabilities */
	public function process(array $capabilities, ApplicationModel $model): void
	{
		$this->capturedModels[] = $model;
	}
}

final class RecordingPass implements ModelBuilderPassInterface
{
	/** @var list<list<CapabilityInterface>> */
	public array $calls = [];

	public string $tag;

	/** @var list<string> */
	public static array $order = [];

	public function __construct(string $tag = 'pass')
	{
		$this->tag = $tag;
	}

	/** @param list<CapabilityInterface> $capabilities */
	public function process(array $capabilities, ApplicationModel $model): void
	{
		$this->calls[]        = $capabilities;
		self::$order[] = $this->tag;
	}
}

// ── Tests ─────────────────────────────────────────────────────────────────────

final class ModelBuilderTest extends TestCase
{
	protected function setUp(): void
	{
		RecordingPass::$order = [];
	}

	public function testBuildWithNoPassesReturnsEmptyModel(): void
	{
		$builder = new ModelBuilder([]);
		$model   = $builder->build([]);

		$this->assertInstanceOf(ApplicationModel::class, $model);
	}

	public function testBuildInvokesEachPassOnce(): void
	{
		$pass    = new RecordingPass();
		$builder = new ModelBuilder([$pass]);

		$builder->build([]);

		$this->assertCount(1, $pass->calls);
	}

	public function testBuildPassesCapabilitiesToEachPass(): void
	{
		$cap  = new CapabilityFixture();
		$pass = new RecordingPass();

		$builder = new ModelBuilder([$pass]);
		$builder->build([$cap]);

		$this->assertSame([$cap], $pass->calls[0]);
	}

	public function testBuildInvokesPassesInRegistrationOrder(): void
	{
		$a = new RecordingPass('first');
		$b = new RecordingPass('second');
		$c = new RecordingPass('third');

		$builder = new ModelBuilder([$a, $b, $c]);
		$builder->build([]);

		$this->assertSame(['first', 'second', 'third'], RecordingPass::$order);
	}

	public function testAllPassesReceiveTheSameModelInstance(): void
	{
		$pass    = new ModelCapturingPass();
		$builder = new ModelBuilder([$pass, $pass]);

		$builder->build([]);

		$this->assertCount(2, $pass->capturedModels);
		$this->assertSame($pass->capturedModels[0], $pass->capturedModels[1]);
	}
}
