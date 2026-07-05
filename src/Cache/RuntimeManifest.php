<?php

declare(strict_types=1);

namespace Rokke\Runtime\Cache;

use Rokke\Runtime\Build\InvokerInterceptorDescriptor;
use Rokke\Runtime\Build\MiddlewareDescriptor;
use Rokke\Runtime\Build\ServiceDescriptor;
use Rokke\Runtime\Compiled\Arguments\ArgumentResolutionPlan;
use Rokke\Runtime\Compiled\ArtifactRepository;
use Rokke\Runtime\Compiled\OperationRepository;
use Rokke\Runtime\Compiled\Results\ResultResolutionPlan;
use Rokke\Runtime\Compiled\ValidationPlan;

/**
 * Serializable snapshot of a fully built application.
 *
 * Contains pure-data artifacts (plans, operations, artifacts) plus the descriptor
 * lists needed to reconstruct the callable-heavy parts (pipeline, interceptor chain,
 * factory repository) without re-running discovery or reflection.
 *
 * @see RuntimeExporter  — serialize to a file
 * @see RuntimeImporter  — restore CompiledRuntime from a file or this manifest
 */
final readonly class RuntimeManifest
{
	/**
	 * @param OperationRepository               $operations
	 * @param ArtifactRepository                $artifacts
	 * @param array<int, class-string>          $handlerClasses
	 * @param array<int, ArgumentResolutionPlan> $argumentPlans
	 * @param array<int, ResultResolutionPlan>  $resultPlans
	 * @param array<int, ValidationPlan>        $validationPlans
	 * @param list<MiddlewareDescriptor>        $middlewareDescriptors
	 * @param list<InvokerInterceptorDescriptor> $interceptorDescriptors
	 * @param list<ServiceDescriptor>           $serviceDescriptors
	 */
	public function __construct(
		public OperationRepository $operations,
		public ArtifactRepository $artifacts,
		public array $handlerClasses,
		public array $argumentPlans,
		public array $resultPlans,
		public array $validationPlans = [],
		public array $middlewareDescriptors = [],
		public array $interceptorDescriptors = [],
		public array $serviceDescriptors = [],
	) {}
}
