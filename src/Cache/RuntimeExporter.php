<?php

declare(strict_types=1);

namespace Rokke\Runtime\Cache;

/**
 * Serializes a RuntimeManifest to a binary cache file.
 *
 * Uses an atomic write (temp-file + rename) so concurrent requests never read
 * a half-written file. Directories are created automatically.
 */
final class RuntimeExporter
{
	public static function export(RuntimeManifest $manifest, string $path): void
	{
		$dir = dirname($path);

		if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
			throw new \RuntimeException("Cannot create cache directory: {$dir}");
		}

		$data = serialize($manifest);
		$tmp  = $path . '.tmp.' . getmypid() . '.' . mt_rand();

		if (file_put_contents($tmp, $data) === false) {
			throw new \RuntimeException("Cannot write cache file: {$tmp}");
		}

		if (!rename($tmp, $path)) {
			@unlink($tmp);
			throw new \RuntimeException("Cannot move cache file to: {$path}");
		}
	}
}
