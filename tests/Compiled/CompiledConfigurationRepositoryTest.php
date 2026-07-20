<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Compiled;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Compiled\CompiledConfigurationRepository;

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class ConfigA {}
final class ConfigB {}

// ── Tests ─────────────────────────────────────────────────────────────────────

final class CompiledConfigurationRepositoryTest extends TestCase
{
	// ── empty() ──────────────────────────────────────────────────────────────

	public function testEmptyRepositoryHasNoEntries(): void
	{
		$repo = CompiledConfigurationRepository::empty();

		$this->assertFalse($repo->has(ConfigA::class));
	}

	public function testEmptyRepositoryAllReturnsEmptyArray(): void
	{
		$this->assertSame([], CompiledConfigurationRepository::empty()->all());
	}

	// ── build() ──────────────────────────────────────────────────────────────

	public function testBuildWithEmptyListProducesEmptyRepository(): void
	{
		$repo = CompiledConfigurationRepository::build([]);

		$this->assertSame([], $repo->all());
	}

	public function testBuildStoresSingleConfiguration(): void
	{
		$a    = new ConfigA();
		$repo = CompiledConfigurationRepository::build([$a]);

		$this->assertTrue($repo->has(ConfigA::class));
		$this->assertSame($a, $repo->get(ConfigA::class));
	}

	public function testBuildStoresMultipleConfigurationsOfDifferentTypes(): void
	{
		$a    = new ConfigA();
		$b    = new ConfigB();
		$repo = CompiledConfigurationRepository::build([$a, $b]);

		$this->assertSame($a, $repo->get(ConfigA::class));
		$this->assertSame($b, $repo->get(ConfigB::class));
	}

	public function testBuildThrowsOnDuplicateType(): void
	{
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage(ConfigA::class);

		CompiledConfigurationRepository::build([new ConfigA(), new ConfigA()]);
	}

	// ── has() + get() ────────────────────────────────────────────────────────

	public function testHasReturnsFalseForUnregisteredClass(): void
	{
		$repo = CompiledConfigurationRepository::build([new ConfigA()]);

		$this->assertFalse($repo->has(ConfigB::class));
	}

	public function testGetThrowsForUnregisteredClass(): void
	{
		$repo = CompiledConfigurationRepository::build([new ConfigA()]);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage(ConfigB::class);

		$repo->get(ConfigB::class);
	}

	// ── all() ────────────────────────────────────────────────────────────────

	public function testAllReturnsBuildOrder(): void
	{
		$a    = new ConfigA();
		$b    = new ConfigB();
		$repo = CompiledConfigurationRepository::build([$a, $b]);

		$this->assertSame([$a, $b], $repo->all());
	}

	public function testRepositoryIsImmutable(): void
	{
		$repo = CompiledConfigurationRepository::build([new ConfigA()]);

		/** @phpstan-ignore function.impossibleType */
		$this->assertFalse(method_exists($repo, 'add'));
	}
}
