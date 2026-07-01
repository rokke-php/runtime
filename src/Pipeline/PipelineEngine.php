<?php

declare(strict_types=1);

namespace Rokke\Runtime\Pipeline;

use Rokke\Contracts\Container\ServiceContainerInterface;
use Rokke\Contracts\Pipeline\PipelineInterface;
use Rokke\Runtime\Contracts\HandlerInterface;
use RuntimeException;

final class PipelineEngine implements PipelineInterface
{
	private mixed $input = null;

	/** @var array<int, mixed> */
	private array $middlewares = [];

	public function __construct(
		private readonly ServiceContainerInterface $container
	) {}

	public function send(mixed $input): static
	{
		$this->input = $input;

		return $this;
	}

	/** @param array<int, mixed> $middlewares */
	public function through(array $middlewares): static
	{
		$this->middlewares = $middlewares;

		return $this;
	}

	public function then(callable|HandlerInterface $handler): mixed
	{
		$pipeline = array_reduce(
			array_reverse($this->middlewares),
			function (callable $next, mixed $middleware): callable {
				return function (mixed $passable) use ($next, $middleware): mixed {
					if (is_string($middleware)) {
						$middleware = $this->container->make($middleware);
					}

					if (is_callable($middleware)) {
						return $middleware($passable, $next);
					}

					if (is_object($middleware) && method_exists($middleware, 'process')) {
						return $middleware->process($passable, $next);
					}

					throw new RuntimeException('The provided middleware is not callable and does not have a process() method.');
				};
			},
			function (mixed $passable) use ($handler): mixed {
				if ($handler instanceof HandlerInterface) {
					return $handler->handle($passable);
				}

				return $handler($passable);
			},
		);

		return $pipeline($this->input);
	}
}
