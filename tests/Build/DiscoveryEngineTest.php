<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Build;

use PHPUnit\Framework\TestCase;
use Rokke\Contracts\Module\CapabilityInterface;
use Rokke\Contracts\Module\DiscoveryProviderInterface;
use Rokke\Runtime\Build\DiscoveryEngine;

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class CapabilityDouble implements CapabilityInterface
{
	public function __construct(private readonly string $typeName) {}

	public function type(): string
	{
		return $this->typeName;
	}

	public function descriptor(): mixed
	{
		return null;
	}
}

// ── Tests ─────────────────────────────────────────────────────────────────────

final class DiscoveryEngineTest extends TestCase
{
	private DiscoveryEngine $engine;

	protected function setUp(): void
	{
		$this->engine = new DiscoveryEngine();
	}

	public function testNoProvidersReturnsEmptyArray(): void
	{
		$this->assertSame([], $this->engine->run([]));
	}

	public function testSingleProviderCapabilitiesAreReturned(): void
	{
		$cap = new CapabilityDouble('operation');

		$provider = new class ($cap) implements DiscoveryProviderInterface {
			public function __construct(private readonly CapabilityInterface $cap) {}

			/** @return list<CapabilityInterface> */
			public function discover(): array
			{
				return [$this->cap];
			}
		};

		$result = $this->engine->run([$provider]);

		$this->assertSame([$cap], $result);
	}

	public function testMultipleProvidersCapabilitiesMergedInOrder(): void
	{
		$capA = new CapabilityDouble('a');
		$capB = new CapabilityDouble('b');
		$capC = new CapabilityDouble('c');

		$providerA = new class ($capA, $capB) implements DiscoveryProviderInterface {
			public function __construct(
				private readonly CapabilityInterface $a,
				private readonly CapabilityInterface $b,
			) {}

			/** @return list<CapabilityInterface> */
			public function discover(): array
			{
				return [$this->a, $this->b];
			}
		};

		$providerB = new class ($capC) implements DiscoveryProviderInterface {
			public function __construct(private readonly CapabilityInterface $c) {}

			/** @return list<CapabilityInterface> */
			public function discover(): array
			{
				return [$this->c];
			}
		};

		$result = $this->engine->run([$providerA, $providerB]);

		$this->assertSame([$capA, $capB, $capC], $result);
	}

	public function testProviderReturningEmptyContributesNothingToResult(): void
	{
		$cap = new CapabilityDouble('operation');

		$empty = new class () implements DiscoveryProviderInterface {
			/** @return list<CapabilityInterface> */
			public function discover(): array
			{
				return [];
			}
		};

		$nonempty = new class ($cap) implements DiscoveryProviderInterface {
			public function __construct(private readonly CapabilityInterface $cap) {}

			/** @return list<CapabilityInterface> */
			public function discover(): array
			{
				return [$this->cap];
			}
		};

		$result = $this->engine->run([$empty, $nonempty]);

		$this->assertSame([$cap], $result);
	}
}
