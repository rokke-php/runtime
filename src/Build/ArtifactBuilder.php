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

	/**
	 * Compile the kernel's runtime into a PHP artifact file.
	 *
	 * @throws \RuntimeException if any operation uses a behavior pipeline (not yet supported).
	 */
	public function build(ApplicationKernel $kernel, string $outputDirectory): void
	{
		$kernel->build();

		$runtime = $kernel->compiledRuntime();

		foreach ($runtime->operations->all() as $op) {
			if ($op->behaviorPipelineId !== null) {
				throw new \RuntimeException(
					"ArtifactBuilder: operation '{$op->id}' uses a behavior pipeline, " .
					'which is not yet supported in artifact generation (v0.21.0).',
				);
			}
		}

		$node = $this->compiler->compile($runtime);

		$this->writer->write($node, rtrim($outputDirectory, '/\\') . '/runtime.php');
	}
}
