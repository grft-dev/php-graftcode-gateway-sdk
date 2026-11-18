<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils\Exception;

use InvalidArgumentException;

final class GetModifierTypeFromObjectTypeException extends InvalidArgumentException
{
    public function __construct(mixed $objectType)
    {
        $typeDescription = is_object($objectType)
            ? (property_exists($objectType, 'type') ? $objectType->type : get_class($objectType))
            : gettype($objectType);

        parent::__construct(sprintf('Can\'t get modifier type for: %s', $typeDescription));
    }
}
