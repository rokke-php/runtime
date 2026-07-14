<?php

declare(strict_types=1);

namespace Rokke\Runtime\Cache;

use Rokke\Runtime\Build\FactoryCompiler;
use Rokke\Runtime\Build\FactoryRepository;
use Rokke\Runtime\Compiled\CompiledExecutionPipeline;
use Rokke\Runtime\Compiled\CompiledInterceptorPipeline;
use Rokke\Runtime\Compiled\CompiledRuntime;

/**
 * Restores a CompiledRuntime from a cache file produced by RuntimeExporter.
 *
 * Callables (handler instances) are reconstructed from the stored class names —
 * no reflection is re-run. The only work done here is object instantiation and
 * wiring the CompiledExecutionPipeline with the deserialized data tables.
 */
final class RuntimeImporter
{
	/**
	 * Load a CompiledRuntime from a binary cache file.
	 *
	 * @throws \RuntimeException when the file is missing or corrupted
	 */
	public static function load(string $path): CompiledRuntime
	{
		if (!is_file($path)) {
			throw new \RuntimeException("Cache file not found: {$path}");
		}

		$data = file_get_contents($path);

		if ($data === false) {
			throw new \RuntimeException("Cannot read cache file: {$path}");
		}

		$manifest = @unserialize($data, ['allowed_classes' => true]);

		if (!$manifest instanceof RuntimeManifest) {
			throw new \RuntimeException(
				'Invalid cache file — expected RuntimeManifest, got ' .
				(is_object($manifest) ? get_class($manifest) : gettype($manifest)) .
				": {$path}",
			);
		}

		return self::fromManifest($manifest);
	}

	/**
	 * Build a CompiledRuntime directly from a manifest without touching the filesystem.
	 * Useful in tests and when the manifest was built in memory.
	 */
	public static function fromManifest(RuntimeManifest $manifest): CompiledRuntime
	{
		/** @var array<int, callable(): mixed> $handlers */
		$handlers = array_map(
			static fn (string $class): object => new $class(),
			$manifest->handlerClasses,
		);

		$factories = $manifest->serviceDescriptors !== []
			? FactoryRepository::build($manifest->serviceDescriptors, new FactoryCompiler())
			: FactoryRepository::empty();

		$executionPipeline = new CompiledExecutionPipeline(
			handlers: $handlers,
			argumentPlans: $manifest->argumentPlans,
			resultPlans: $manifest->resultPlans,
			behaviorPipelines: [],
			validationPlans: $manifest->validationPlans,
		);

		return new CompiledRuntime(
			executionPipeline: $executionPipeline,
			interceptorPipeline: CompiledInterceptorPipeline::empty(),
			operations: $manifest->operations,
			factories: $factories,
			artifacts: $manifest->artifacts,
		);
	}
}
