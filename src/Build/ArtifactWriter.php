<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use Rokke\Runtime\Build\CodeGen\NodeInterface;
use Rokke\Runtime\Build\CodeGen\PhpWriter;

final class ArtifactWriter
{
	public function __construct(private readonly PhpWriter $writer = new PhpWriter()) {}

	public function write(NodeInterface $node, string $path): void
	{
		$dir = dirname($path);

		if (!is_dir($dir)) {
			mkdir($dir, 0o755, true);
		}

		file_put_contents($path, $this->writer->render($node));
	}
}
