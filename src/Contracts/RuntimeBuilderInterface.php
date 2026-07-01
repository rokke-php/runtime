<?php

declare(strict_types=1);

namespace Rokke\Runtime\Contracts;

use Rokke\Runtime\Build\ApplicationModel;

/**
 * Transforms a fully-built ApplicationModel into an executable Runtime.
 * Runs after all ModelBuilderPasses have populated the ApplicationModel.
 */
interface RuntimeBuilderInterface
{
	public function build(ApplicationModel $model): RuntimeInterface;
}
