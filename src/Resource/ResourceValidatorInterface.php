<?php

declare(strict_types=1);

namespace Rokke\Runtime\Resource;

/**
 * Validates a pooled resource before it is returned to a consumer.
 *
 * Return false to discard the resource and have the pool create a replacement.
 * Implementations should be fast — this is called on every acquire.
 */
interface ResourceValidatorInterface
{
	/**
	 * @param mixed $resource The resource being checked (e.g. a DB connection)
	 * @return bool true → hand resource to consumer; false → discard and create new
	 */
	public function validate(mixed $resource): bool;
}
