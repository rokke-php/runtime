<?php

declare(strict_types=1);

namespace Rokke\Runtime;

use Rokke\Contracts\Context\ContextInterface;
use Rokke\Runtime\Contracts\ContextManagerInterface;
use RuntimeException;
use Swoole\Coroutine;

final class ContextManager implements ContextManagerInterface
{
	private const CONTEXT_KEY = '__rokke_context';

	public function current(): ContextInterface
	{
		$cid = Coroutine::getCid();

		if ($cid === -1) {
			throw new RuntimeException('Context can only be accessed inside an active coroutine.');
		}

		/** @var array<string, mixed>|\ArrayObject<string, mixed> $swooleContext */
		$swooleContext = Coroutine::getContext($cid);

		if (!isset($swooleContext[self::CONTEXT_KEY])) {
			throw new RuntimeException('Context has not been initialized for this coroutine.');
		}

		/** @var ContextInterface */
		return $swooleContext[self::CONTEXT_KEY];
	}

	public function create(): ContextInterface
	{
		$cid = Coroutine::getCid();

		if ($cid === -1) {
			throw new RuntimeException('Context must be initialized inside a coroutine.');
		}

		/** @var array<string, mixed>|\ArrayObject<string, mixed> $swooleContext */
		$swooleContext = Coroutine::getContext($cid);

		$contextId = 'ctx_' . bin2hex(random_bytes(8));
		$context   = new Context($contextId);

		$swooleContext[self::CONTEXT_KEY] = $context;

		return $context;
	}

	public function destroyCurrent(): void
	{
		$cid = Coroutine::getCid();

		if ($cid === -1) {
			return;
		}

		/** @var array<string, mixed>|\ArrayObject<string, mixed> $swooleContext */
		$swooleContext = Coroutine::getContext($cid);

		if (isset($swooleContext[self::CONTEXT_KEY])) {
			/** @var ContextInterface $context */
			$context = $swooleContext[self::CONTEXT_KEY];
			$context->destroy();
			unset($swooleContext[self::CONTEXT_KEY]);
		}
	}
}
