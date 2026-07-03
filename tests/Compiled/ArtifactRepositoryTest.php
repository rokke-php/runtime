<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Compiled;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Compiled\ArtifactRepository;

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class ArtifactA {}
final class ArtifactB {}
final class ArtifactC {}

interface ArtifactContract {}
final class ArtifactImpl implements ArtifactContract {}

// ── Tests ─────────────────────────────────────────────────────────────────────

final class ArtifactRepositoryTest extends TestCase
{
	// ── empty() ───────────────────────────────────────────────────────────────

	public function testEmptyRepositoryHasNoArtifacts(): void
	{
		$repo = ArtifactRepository::empty();

		$this->assertFalse($repo->has(ArtifactA::class));
		$this->assertNull($repo->get(ArtifactA::class));
	}

	// ── has() ─────────────────────────────────────────────────────────────────

	public function testHasReturnsTrueForRegisteredKey(): void
	{
		$repo = ArtifactRepository::build([ArtifactA::class => new ArtifactA()]);

		$this->assertTrue($repo->has(ArtifactA::class));
	}

	public function testHasReturnsFalseForUnregisteredKey(): void
	{
		$repo = ArtifactRepository::build([ArtifactA::class => new ArtifactA()]);

		$this->assertFalse($repo->has(ArtifactB::class));
	}

	// ── get() ─────────────────────────────────────────────────────────────────

	public function testGetReturnsNullForUnknownKey(): void
	{
		$repo = ArtifactRepository::build([ArtifactA::class => new ArtifactA()]);

		$this->assertNull($repo->get(ArtifactB::class));
	}

	public function testGetReturnsArtifactForKnownKey(): void
	{
		$artifact = new ArtifactA();
		$repo     = ArtifactRepository::build([ArtifactA::class => $artifact]);

		$this->assertSame($artifact, $repo->get(ArtifactA::class));
	}

	// ── build() ───────────────────────────────────────────────────────────────

	public function testBuildRegistersMultipleArtifacts(): void
	{
		$a    = new ArtifactA();
		$b    = new ArtifactB();
		$repo = ArtifactRepository::build([
			ArtifactA::class => $a,
			ArtifactB::class => $b,
		]);

		$this->assertSame($a, $repo->get(ArtifactA::class));
		$this->assertSame($b, $repo->get(ArtifactB::class));
		$this->assertFalse($repo->has(ArtifactC::class));
	}

	public function testBuildWithEmptyArrayProducesEmptyRepository(): void
	{
		$repo = ArtifactRepository::build([]);

		$this->assertFalse($repo->has(ArtifactA::class));
	}

	public function testBuildKeyCanBeInterfaceOrParentClass(): void
	{
		$impl = new ArtifactImpl();
		$repo = ArtifactRepository::build([ArtifactContract::class => $impl]);

		$this->assertTrue($repo->has(ArtifactContract::class));
		$this->assertSame($impl, $repo->get(ArtifactContract::class));
		$this->assertFalse($repo->has(ArtifactImpl::class));
	}

	// ── immutability ──────────────────────────────────────────────────────────

	public function testRepositoryExposesNoMutationMethods(): void
	{
		$repo    = ArtifactRepository::empty();
		$methods = get_class_methods($repo);

		$this->assertFalse(in_array('add', $methods, true));
		$this->assertFalse(in_array('set', $methods, true));
		$this->assertFalse(in_array('remove', $methods, true));
	}
}
