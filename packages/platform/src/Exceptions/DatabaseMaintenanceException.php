<?php

namespace Nreljed\Platform\Exceptions;

use RuntimeException;

/**
 * Thrown when a database maintenance operation (reset/reseed) cannot run.
 */
class DatabaseMaintenanceException extends RuntimeException {}
