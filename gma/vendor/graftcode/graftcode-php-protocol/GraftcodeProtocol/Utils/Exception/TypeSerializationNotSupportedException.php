<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Utils\Exception;

use RuntimeException;

final class TypeSerializationNotSupportedException extends RuntimeException
{
    public function __construct(mixed $typeSerialization)
    {
        parent::__construct(
            sprintf('PHP: Type serialization not supported for type: %s' ,
                is_object($typeSerialization) ? get_class($typeSerialization) : gettype($typeSerialization)
            )
        );
    }
}
