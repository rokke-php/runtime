<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests;

use PHPUnit\Framework\TestCase;
use Rokke\Contracts\Lifecycle\ApplicationState;
use Rokke\Contracts\Lifecycle\LifecycleEventsInterface;
use Rokke\Runtime\Contracts\LifecycleManagerInterface;
use Rokke\Runtime\Lifecycle;

final class LifecycleTest extends TestCase
{
	private Lifecycle $lifecycle;

	protected function setUp(): void
	{
		$this->lifecycle = new Lifecycle();
	}

	public function testImplementsLifecycleEventsInterface(): void
	{
		$this->assertInstanceOf(LifecycleEventsInterface::class, $this->lifecycle);
	}

	public function testImplementsLifecycleManagerInterface(): void
	{
		$this->assertInstanceOf(LifecycleManagerInterface::class, $this->lifecycle);
	}

	public function testInitialStateIsCreated(): void
	{
		$this->assertSame(ApplicationState::Created, $this->lifecycle->currentState());
	}

	public function testTransitionsToNewState(): void
	{
		$this->lifecycle->transitionTo(ApplicationState::Bootstrapping);

		$this->assertSame(ApplicationState::Bootstrapping, $this->lifecycle->currentState());
	}

	public function testFiresBootstrappingCallback(): void
	{
		$fired = false;

		$this->lifecycle->onBootstrapping(function () use (&$fired): void {
			$fired = true;
		});

		$this->lifecycle->transitionTo(ApplicationState::Bootstrapping);

		$this->assertTrue($fired);
	}

	public function testFiresStartingCallback(): void
	{
		$fired = false;

		$this->lifecycle->onStarting(function () use (&$fired): void {
			$fired = true;
		});

		$this->lifecycle->transitionTo(ApplicationState::Starting);

		$this->assertTrue($fired);
	}

	public function testFiresRunningCallback(): void
	{
		$fired = false;

		$this->lifecycle->onRunning(function () use (&$fired): void {
			$fired = true;
		});

		$this->lifecycle->transitionTo(ApplicationState::Running);

		$this->assertTrue($fired);
	}

	public function testFiresStoppingCallback(): void
	{
		$fired = false;

		$this->lifecycle->onStopping(function () use (&$fired): void {
			$fired = true;
		});

		$this->lifecycle->transitionTo(ApplicationState::Stopping);

		$this->assertTrue($fired);
	}

	public function testFiresStoppedCallback(): void
	{
		$fired = false;

		$this->lifecycle->onStopped(function () use (&$fired): void {
			$fired = true;
		});

		$this->lifecycle->transitionTo(ApplicationState::Stopped);

		$this->assertTrue($fired);
	}

	public function testFiresMultipleCallbacksForSameState(): void
	{
		$callCount = 0;

		$this->lifecycle->onStarting(function () use (&$callCount): void {
			$callCount++;
		});

		$this->lifecycle->onStarting(function () use (&$callCount): void {
			$callCount++;
		});

		$this->lifecycle->transitionTo(ApplicationState::Starting);

		$this->assertSame(2, $callCount);
	}

	public function testDoesNotFireCallbackForOtherStates(): void
	{
		$fired = false;

		$this->lifecycle->onStarting(function () use (&$fired): void {
			$fired = true;
		});

		$this->lifecycle->transitionTo(ApplicationState::Bootstrapping);

		$this->assertFalse($fired);
	}

	public function testTransitionToCreatedFiresNoCallbacks(): void
	{
		$fired = false;

		$this->lifecycle->onBootstrapping(function () use (&$fired): void {
			$fired = true;
		});

		$this->lifecycle->transitionTo(ApplicationState::Created);

		$this->assertFalse($fired);
	}

	public function testRemainingHooksAreFiredWhenEarlierHookThrows(): void
	{
		$secondFired = false;

		$this->lifecycle->onStarting(function (): void {
			throw new \RuntimeException('hook error');
		});

		$this->lifecycle->onStarting(function () use (&$secondFired): void {
			$secondFired = true;
		});

		$this->lifecycle->transitionTo(ApplicationState::Starting);

		$this->assertTrue($secondFired);
	}

	public function testCanTransitionToFromCreated(): void
	{
		$this->assertTrue($this->lifecycle->canTransitionTo(ApplicationState::Bootstrapping));
		$this->assertFalse($this->lifecycle->canTransitionTo(ApplicationState::Starting));
		$this->assertFalse($this->lifecycle->canTransitionTo(ApplicationState::Running));
		$this->assertFalse($this->lifecycle->canTransitionTo(ApplicationState::Stopping));
		$this->assertFalse($this->lifecycle->canTransitionTo(ApplicationState::Stopped));
		$this->assertFalse($this->lifecycle->canTransitionTo(ApplicationState::Created));
	}

	public function testCanTransitionToFromBootstrapping(): void
	{
		$this->lifecycle->transitionTo(ApplicationState::Bootstrapping);

		$this->assertTrue($this->lifecycle->canTransitionTo(ApplicationState::Starting));
		$this->assertFalse($this->lifecycle->canTransitionTo(ApplicationState::Created));
		$this->assertFalse($this->lifecycle->canTransitionTo(ApplicationState::Running));
	}

	public function testCanTransitionToFromRunning(): void
	{
		$this->lifecycle->transitionTo(ApplicationState::Bootstrapping);
		$this->lifecycle->transitionTo(ApplicationState::Starting);
		$this->lifecycle->transitionTo(ApplicationState::Running);

		$this->assertTrue($this->lifecycle->canTransitionTo(ApplicationState::Stopping));
		$this->assertFalse($this->lifecycle->canTransitionTo(ApplicationState::Created));
		$this->assertFalse($this->lifecycle->canTransitionTo(ApplicationState::Stopped));
	}

	public function testCanTransitionToFromStopped(): void
	{
		$this->lifecycle->transitionTo(ApplicationState::Bootstrapping);
		$this->lifecycle->transitionTo(ApplicationState::Starting);
		$this->lifecycle->transitionTo(ApplicationState::Running);
		$this->lifecycle->transitionTo(ApplicationState::Stopping);
		$this->lifecycle->transitionTo(ApplicationState::Stopped);

		$this->assertFalse($this->lifecycle->canTransitionTo(ApplicationState::Created));
		$this->assertFalse($this->lifecycle->canTransitionTo(ApplicationState::Bootstrapping));
		$this->assertFalse($this->lifecycle->canTransitionTo(ApplicationState::Running));
	}
}
