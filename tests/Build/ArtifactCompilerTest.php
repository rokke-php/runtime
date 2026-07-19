<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Build;

use PHPUnit\Framework\TestCase;
use Rokke\Contracts\Extension\ExtensionBuilderInterface;
use Rokke\Contracts\Extension\ExtensionBuildInterface;
use Rokke\Contracts\Extension\ExtensionInterface;
use Rokke\Runtime\ApplicationKernel;
use Rokke\Runtime\Build\ApplicationModel;
use Rokke\Runtime\Build\ArtifactCompiler;
use Rokke\Runtime\Build\CodeGen\PhpWriter;
use Rokke\Runtime\Build\ExtensionBuildPassInterface;
use Rokke\Runtime\Build\OperationCapability;
use Rokke\Runtime\Compiled\CompiledConfigurationRepository;
use Rokke\Runtime\Compiled\CompiledRuntime;

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class ArtifactConfig
{
	public function __construct(public readonly string $label) {}
}

final class ArtifactConfigBuildPass implements ExtensionBuildPassInterface
{
	public function process(ApplicationModel $model): array
	{
		return [new ArtifactConfig(label: 'compiled-value')];
	}
}

final class ArtifactConfigExtension implements ExtensionInterface, ExtensionBuildInterface
{
	public function register(ExtensionBuilderInterface $builder): void {}

	public function buildPasses(): iterable
	{
		return [new ArtifactConfigBuildPass()];
	}
}

final class ArtifactDep {}

final class ArtifactHandler
{
	public function __construct(private readonly ArtifactDep $dep) {}

	public function __invoke(): string
	{
		return 'from-artifact';
	}
}

// ── Tests ─────────────────────────────────────────────────────────────────────

final class ArtifactCompilerTest extends TestCase
{
	private function buildRuntime(): CompiledRuntime
	{
		$kernel = new ApplicationKernel();
		$kernel->register(new class () implements ExtensionInterface {
			public function register(ExtensionBuilderInterface $builder): void
			{
				$builder->service(ArtifactDep::class);
				$builder->addCapability(new OperationCapability(
					'artifact-op',
					'ArtifactOp',
					ArtifactHandler::class,
				));
			}
		});
		$kernel->build();

		return $kernel->compiledRuntime();
	}

	private function loadArtifact(CompiledRuntime $runtime): CompiledRuntime
	{
		$compiler = new ArtifactCompiler();
		$writer   = new PhpWriter();
		$node     = $compiler->compile($runtime);
		$php      = $writer->render($node);

		$tmp = tempnam(sys_get_temp_dir(), 'rokke_artifact_');
		assert(is_string($tmp));
		file_put_contents($tmp, $php);
		$loaded = require $tmp;
		unlink($tmp);

		return $loaded;
	}

	public function testArtifactIsCompiledRuntime(): void
	{
		$runtime  = $this->buildRuntime();
		$artifact = $this->loadArtifact($runtime);

		$this->assertInstanceOf(CompiledRuntime::class, $artifact);
	}

	public function testArtifactExecutesOperation(): void
	{
		$runtime  = $this->buildRuntime();
		$artifact = $this->loadArtifact($runtime);

		$kernel = new ApplicationKernel();
		$kernel->loadCompiledRuntime($artifact);
		$result = $kernel->run('artifact-op');

		$this->assertSame('from-artifact', $result);
	}

	public function testArtifactContainsConfigurations(): void
	{
		$kernel = new ApplicationKernel();
		$kernel->register(new ArtifactConfigExtension());
		$kernel->build();

		$runtime  = $kernel->compiledRuntime();
		$artifact = $this->loadArtifact($runtime);

		$this->assertTrue($artifact->configurations()->has(ArtifactConfig::class));
		$config = $artifact->configurations()->get(ArtifactConfig::class);
		assert($config instanceof ArtifactConfig);
		$this->assertSame('compiled-value', $config->label);
	}

	public function testArtifactWithNoConfigurationsHasEmptyRepository(): void
	{
		$runtime  = $this->buildRuntime();
		$artifact = $this->loadArtifact($runtime);

		$this->assertInstanceOf(CompiledConfigurationRepository::class, $artifact->configurations());
		$this->assertSame([], $artifact->configurations()->all());
	}

	public function testArtifactContainsNoClosures(): void
	{
		$runtime  = $this->buildRuntime();
		$compiler = new ArtifactCompiler();
		$writer   = new PhpWriter();
		$php      = $writer->render($compiler->compile($runtime));

		$this->assertStringNotContainsString('function (', $php);
		$this->assertStringNotContainsString('fn (', $php);
		$this->assertStringNotContainsString('static fn', $php);
	}
}
