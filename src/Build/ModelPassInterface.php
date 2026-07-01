<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

interface ModelPassInterface
{
	public function process(ApplicationModel $model): void;
}
