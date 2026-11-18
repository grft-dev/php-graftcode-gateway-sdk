<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Utils\Exception;

use GraftcodeProtocol\Enums\StringEncodingMode;
use RuntimeException;

final class ConversionByteToStringException extends RuntimeException
{
    public function __construct(array $bytes, StringEncodingMode $encodingMode)
    {
        parent::__construct(sprintf('Failed to convert string from: [%s] to: %s', implode(', ', $bytes), $encodingMode->name));
    }
}
