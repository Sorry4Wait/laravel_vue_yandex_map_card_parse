<?php

namespace App\Services\YandexMaps\Exceptions;

use RuntimeException;

/**
 * Thrown when a response comes back 200 OK but doesn't look like what we
 * expect - missing fields, different json shape, etc. Almost always means
 * yandex changed their internal api and the parser needs updating.
 */
class YandexSourceChangedException extends RuntimeException {}
