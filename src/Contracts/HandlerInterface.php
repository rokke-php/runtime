<?php

declare(strict_types=1);

namespace Rokke\Runtime\Contracts;

interface HandlerInterface
{
	public function handle(mixed $input): mixed;
}
