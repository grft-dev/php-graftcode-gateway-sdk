<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler\Shared;

use Exception;
use GraftcodeModuleAnalyzer\Core\Handler\Handlers;
use GraftcodeModuleAnalyzer\Utils\TypeNameResolver;
use GraftcodeProtocol\Models\UnifiedGraftModel;

/**
 * Handler for primitive types.
 * Produces nested structure: TYPE_USAGE -> TYPE_USAGE_PRIMITIVE
 * TYPE_USAGE_PRIMITIVE payload: [IS_NULLABLE, NAMESPACE, TYPENAME, PRIMITIVE_TYPE, ASSEMBLY_NAME, ASSEMBLY_VERSION]
 */
final class SharedPrimitiveTypeHandler extends SharedTypeHandler
{
    /**
     * @throws Exception
     */
    public function generateUnifiedGraftModel(
        mixed $analyzedObject,
        UnifiedGraftModel $parentCommand,
        Handlers $handlers
    ): UnifiedGraftModel {
        try {
            $rawTypeName = $this->extractTypeName($analyzedObject);
            $resolvedTypeName = TypeNameResolver::resolveRelativeTypeName($rawTypeName, $handlers);
            $isNullable = $this->getIsNullable($analyzedObject, $rawTypeName);

            return $this->buildPrimitiveTypeUsage($resolvedTypeName, $isNullable, $handlers);
        } catch (Exception $e) {
            $this->logger->trackError('Error generating primitive type model', [
                'exception' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
