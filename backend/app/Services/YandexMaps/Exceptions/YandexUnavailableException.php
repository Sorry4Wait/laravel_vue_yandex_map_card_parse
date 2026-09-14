<?php

namespace App\Services\YandexMaps\Exceptions;

use RuntimeException;

// network errors, timeouts, 5xx - transient stuff worth retrying
class YandexUnavailableException extends RuntimeException {}
