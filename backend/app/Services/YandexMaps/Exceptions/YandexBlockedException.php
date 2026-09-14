<?php

namespace App\Services\YandexMaps\Exceptions;

use RuntimeException;

// captcha page, 403, or a 429 that didn't clear after retries
class YandexBlockedException extends RuntimeException {}
