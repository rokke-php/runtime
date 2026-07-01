<?php

declare(strict_types=1);

namespace Rokke\Runtime;

use Rokke\Contracts\Lifecycle\ApplicationState;
use Rokke\Contracts\Lifecycle\LifecycleEventsInterface;
use Rokke\Runtime\Contracts\LifecycleManagerInterface;

final class Lifecycle implements LifecycleEventsInterface, LifecycleManagerInterface
{
	private ApplicationState $state = ApplicationState::Created;

	/** @var array<string, callable[]> */
	private array $hooks = [
		'bootstrapping' => [],
		'starting'      => [],
		'running'       => [],
		'stopping'      => [],
		'stopped'       => [],
	];

	/** @var array<string, string> Valid transitions: current state name => next state name */
	private const TRANSITIONS = [
		'Created'       => 'Bootstrapping',
		'Bootstrapping' => 'Starting',
		'Starting'      => 'Running',
		'Running'       => 'Stopping',
		'Stopping'      => 'Stopped',
	];

	public function currentState(): ApplicationState
	{
		return $this->state;
	}

	public function canTransitionTo(ApplicationState $state): bool
	{
		return (self::TRANSITIONS[$this->state->name] ?? null) === $state->name;
	}

	public function onBootstrapping(callable $listener): void
	{
		$this->hooks['bootstrapping'][] = $listener;
	}

	public function onStarting(callable $listener): void
	{
		$this->hooks['starting'][] = $listener;
	}

	public function onRunning(callable $listener): void
	{
		$this->hooks['running'][] = $listener;
	}

	public function onStopping(callable $listener): void
	{
		$this->hooks['stopping'][] = $listener;
	}

	public function onStopped(callable $listener): void
	{
		$this->hooks['stopped'][] = $listener;
	}

	public function transitionTo(ApplicationState $newState): void
	{
		$this->state = $newState;

		$hookName  = strtolower($newState->name);
		$failures  = [];

		if (isset($this->hooks[$hookName])) {
			foreach ($this->hooks[$hookName] as $callback) {
				try {
					$callback();
				} catch (\Throwable $e) {
					$failures[] = $e;
				}
			}
		}

		if ($failures !== []) {
			$messages = implode('; ', array_map(fn (\Throwable $e): string => $e->getMessage(), $failures));
			throw new \RuntimeException("Lifecycle hook(s) failed during {$newState->name}: {$messages}");
		}
	}
}
