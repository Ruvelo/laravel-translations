<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Exceptions;

use RuntimeException;

/**
 * Base class for the package's own exceptions, so callers can catch them all.
 */
abstract class TranslationsException extends RuntimeException {}
