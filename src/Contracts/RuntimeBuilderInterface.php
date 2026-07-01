<?php

declare(strict_types=1);

namespace Rokke\Runtime\Contracts;

/**
 * Orchestrates the transformation of the ApplicationGraph into an executable Runtime.
 * Runs the compilation pass pipeline: validation, optimization, linking.
 */
interface RuntimeBuilderInterface
{
	public function build(ApplicationGraphInterface $graph): RuntimeInterface;
}
