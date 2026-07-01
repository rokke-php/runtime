<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests;

use PHPUnit\Framework\TestCase;
use Rokke\Contracts\Lifecycle\ApplicationState;

final class ApplicationStateTest extends TestCase
{
	public function testCreatedIsFirstState(): void
	{
		$states = ApplicationState::cases();
		$this->assertSame(ApplicationState::Created, $states[0]);
	}

	public function testStoppedIsLastState(): void
	{
		$states = ApplicationState::cases();
		$this->assertSame(ApplicationState::Stopped, $states[5]);
	}

	public function testAllStatesAreDefined(): void
	{
		$states = ApplicationState::cases();

		$this->assertCount(6, $states);
		$this->assertContains(ApplicationState::Created, $states);
		$this->assertContains(ApplicationState::Bootstrapping, $states);
		$this->assertContains(ApplicationState::Starting, $states);
		$this->assertContains(ApplicationState::Running, $states);
		$this->assertContains(ApplicationState::Stopping, $states);
		$this->assertContains(ApplicationState::Stopped, $states);
	}

	public function testStateOrderIsCorrect(): void
	{
		$states = ApplicationState::cases();

		$this->assertSame([
			ApplicationState::Created,
			ApplicationState::Bootstrapping,
			ApplicationState::Starting,
			ApplicationState::Running,
			ApplicationState::Stopping,
			ApplicationState::Stopped,
		], $states);
	}
}
