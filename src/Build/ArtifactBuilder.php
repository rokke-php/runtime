<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use Rokke\Runtime\ApplicationKernel;

final class ArtifactBuilder
{
	public function __construct(
		private readonly ArtifactCompiler $compiler = new ArtifactCompiler(),
		private readonly ArtifactWriter   $writer   = new ArtifactWriter(),
	) {}

	public function build(ApplicationKernel $kernel, string $outputDirectory): void
	{
		$kernel->build();

		$runtime = $kernel->compiledRuntime();
		$node    = $this->compiler->compile($runtime);

		$this->writer->write($node, rtrim($outputDirectory, '/\\') . '/runtime.php');
	}
}
