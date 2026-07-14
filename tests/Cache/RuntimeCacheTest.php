<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Cache;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Build\MaxValidationInstruction;
use Rokke\Runtime\Cache\RuntimeExporter;
use Rokke\Runtime\Cache\RuntimeImporter;
use Rokke\Runtime\Cache\RuntimeManifest;
use Rokke\Runtime\Compiled\Arguments\ArgumentResolutionPlan;
use Rokke\Runtime\Compiled\Arguments\ContextArgumentInstruction;
use Rokke\Runtime\Compiled\ArtifactRepository;
use Rokke\Runtime\Compiled\CompiledOperation;
use Rokke\Runtime\Compiled\CompiledRuntime;
use Rokke\Runtime\Compiled\OperationRepository;
use Rokke\Runtime\Compiled\ParameterValidationPlan;
use Rokke\Runtime\Compiled\Results\ResultResolutionPlan;
use Rokke\Runtime\Compiled\Results\ScalarResultInstruction;
use Rokke\Runtime\Compiled\ValidationPlan;
use Rokke\Runtime\Context\OperationContext;
use Rokke\Runtime\Engine\ExecutionEngine;
use Rokke\Runtime\SimpleOperation;
use Rokke\Runtime\Tests\Cache\Fixture\CacheableHandler;

final class RuntimeCacheTest extends TestCase
{
	private string $cacheFile;

	protected function setUp(): void
	{
		$this->cacheFile = sys_get_temp_dir() . '/rokke_cache_test_' . uniqid() . '.bin';
	}

	protected function tearDown(): void
	{
		if (file_exists($this->cacheFile)) {
			unlink($this->cacheFile);
		}
	}

	// ── helpers ───────────────────────────────────────────────────────────────

	private function buildManifest(bool $withValidation = false): RuntimeManifest
	{
		$op         = new CompiledOperation('ping', pipelineId: 0, handlerId: 0, argumentPlanId: 0, resultPlanId: 0);
		$operations = OperationRepository::build([$op]);

		$argPlan    = new ArgumentResolutionPlan([new ContextArgumentInstruction()]);
		$resultPlan = new ResultResolutionPlan(new ScalarResultInstruction('string'));

		$validationPlans = [];

		if ($withValidation) {
			$instruction     = new MaxValidationInstruction(100);
			$paramPlan       = new ParameterValidationPlan(0, 'value', [$instruction]);
			$validationPlans = [0 => new ValidationPlan([$paramPlan])];
		}

		return new RuntimeManifest(
			operations: $operations,
			artifacts: ArtifactRepository::empty(),
			handlerClasses: [0 => CacheableHandler::class],
			argumentPlans: [0 => $argPlan],
			resultPlans: [0 => $resultPlan],
			validationPlans: $validationPlans,
		);
	}

	private function execute(CompiledRuntime $runtime, string $operationId = 'ping'): mixed
	{
		$engine = new ExecutionEngine($runtime);

		return $engine->execute(new SimpleOperation($operationId), new OperationContext('req-test'));
	}

	// ── export ────────────────────────────────────────────────────────────────

	public function testExportCreatesFile(): void
	{
		RuntimeExporter::export($this->buildManifest(), $this->cacheFile);

		$this->assertFileExists($this->cacheFile);
	}

	public function testExportCreatesMissingDirectories(): void
	{
		$nested = sys_get_temp_dir() . '/rokke_cache_' . uniqid() . '/sub/runtime.bin';

		try {
			RuntimeExporter::export($this->buildManifest(), $nested);
			$this->assertFileExists($nested);
		} finally {
			if (file_exists($nested)) {
				unlink($nested);
			}

			@rmdir(dirname($nested));
			@rmdir(dirname(dirname($nested)));
		}
	}

	// ── import ────────────────────────────────────────────────────────────────

	public function testImportReturnsCompiledRuntime(): void
	{
		RuntimeExporter::export($this->buildManifest(), $this->cacheFile);

		$runtime = RuntimeImporter::load($this->cacheFile);

		$this->assertInstanceOf(CompiledRuntime::class, $runtime);
	}

	public function testImportPreservesOperations(): void
	{
		RuntimeExporter::export($this->buildManifest(), $this->cacheFile);

		$runtime = RuntimeImporter::load($this->cacheFile);

		$this->assertTrue($runtime->operations->has('ping'));
		$this->assertFalse($runtime->operations->has('missing'));
	}

	// ── execution after import ────────────────────────────────────────────────

	public function testImportedRuntimeExecutesOperation(): void
	{
		RuntimeExporter::export($this->buildManifest(), $this->cacheFile);

		$runtime = RuntimeImporter::load($this->cacheFile);
		$result  = $this->execute($runtime);

		$this->assertSame('pong', $result);
	}

	public function testFromManifestReturnsRuntimeWithoutFile(): void
	{
		$manifest = $this->buildManifest();
		$runtime  = RuntimeImporter::fromManifest($manifest);

		$this->assertInstanceOf(CompiledRuntime::class, $runtime);
		$this->assertSame('pong', $this->execute($runtime));
	}

	// ── error handling ────────────────────────────────────────────────────────

	public function testImportThrowsForMissingFile(): void
	{
		$this->expectException(\RuntimeException::class);
		RuntimeImporter::load('/nonexistent/path/runtime.bin');
	}

	public function testImportThrowsForCorruptedFile(): void
	{
		file_put_contents($this->cacheFile, 'not-valid-serialized-data');

		$this->expectException(\RuntimeException::class);
		RuntimeImporter::load($this->cacheFile);
	}
}
