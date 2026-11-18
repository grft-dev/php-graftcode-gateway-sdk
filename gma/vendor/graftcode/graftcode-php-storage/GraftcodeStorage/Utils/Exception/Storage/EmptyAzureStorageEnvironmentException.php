<?php

namespace GraftcodeStorage\Utils\Exception\Storage;

use InvalidArgumentException;

final class EmptyAzureStorageEnvironmentException extends InvalidArgumentException
{
    public function __construct(string $key)
    {
        return parent::__construct($key . ' environment variable is not set');
    }
}
