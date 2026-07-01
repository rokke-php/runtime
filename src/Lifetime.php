<?php

declare(strict_types=1);

namespace Rokke\Runtime;

enum Lifetime
{
	case Singleton;
	case Scoped;
	case Transient;
	case Pooled;
}
