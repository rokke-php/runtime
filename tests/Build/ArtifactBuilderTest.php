<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Build;

use PHPUnit\Framework\TestCase;
use Rokke\Contracts\Extension\ExtensionBuilderInterface;
use Rokke\Contracts\Extension\ExtensionInterface;
use Rokke\Runtime\ApplicationKernel;
use Rokke\Runtime\Build\ArtifactBuilder;
use Rokke\Runtime\Build\OperationCapability;
use Rokke\Runtime\Compiled\CompiledRuntime;

final class BuilderOpHandler
{
	public function __invoke(): string
	{
		return 'built';
	}
}

final class ArtifactBuilderTest extends TestCase
{
	private string $outputDir;

	protected function setUp(): void
	{
		$this->outputDir = sys_get_temp_dir() . '/rokke_artifact_builder_' . uniqid('', true);
		mkdir($this->outputDir, 0o755, true);
	}

	protected function tearDown(): void
	{
		array_map('unlink', glob($this->outputDir . '/*') ?: []);
		rmdir($this->outputDir);
	}

	private function makeKernel(): ApplicationKernel
	{
		$kernel = new ApplicationKernel();
		$kernel->register(new class () implements ExtensionInterface {
			public function register(ExtensionBuilderInterface $builder): void
			{
				$builder->addCapability(new OperationCapability('test-op', 'TestOp', BuilderOpHandler::class));
			}
		});
		return $kernel;
	}

	public function testBuildWritesRuntimePhp(): void
	{
		$builder = new ArtifactBuilder();
		$builder->build($this->makeKernel(), $this->outputDir);

		$this->assertFileExists($this->outputDir . '/runtime.php');
	}

	public function testWrittenFileIsValidPhp(): void
	{
		$builder = new ArtifactBuilder();
		$builder->build($this->makeKernel(), $this->outputDir);

		$runtime = require $this->outputDir . '/runtime.php';
		$this->assertInstanceOf(CompiledRuntime::class, $runtime);
	}

	public function testLoadedArtifactExecutesOperation(): void
	{
		$builder = new ArtifactBuilder();
		$builder->build($this->makeKernel(), $this->outputDir);

		$runtime = require $this->outputDir . '/runtime.php';
		assert($runtime instanceof CompiledRuntime);

		$kernel = new ApplicationKernel();
		$kernel->loadCompiledRuntime($runtime);

		$this->assertSame('built', $kernel->run('test-op'));
	}
}
