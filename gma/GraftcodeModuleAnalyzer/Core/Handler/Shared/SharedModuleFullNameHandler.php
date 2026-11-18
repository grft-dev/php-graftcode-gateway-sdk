<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler\Shared;

use GraftcodeModuleAnalyzer\Core\Handler\{
    BaseHandler,
    Handlers,
    HandlersInterface
};
use GraftcodeModuleAnalyzer\Utils\AnalyzedObjectUtils;
use GraftcodeModuleAnalyzer\Utils\Const\Types;
use GraftcodeProtocol\Models\UnifiedGraftModel;
use ReflectionClass;
use Throwable;

final class SharedModuleFullNameHandler extends BaseHandler implements HandlersInterface
{
    /**
     * @throws Throwable
     */
    public function generateUnifiedGraftModel(
        mixed $analyzedObject,
        UnifiedGraftModel $parentCommand,
        Handlers $handlers
    ): string {
        try {
            $this->logger->trackVerbose(sprintf(
                'Trying to get module full name for object: %s ',
                AnalyzedObjectUtils::getClassNameOrType($analyzedObject)
            ));

            if (!$analyzedObject instanceof ReflectionClass) {
                $this->logger->trackVerbose('Analyzed object is not an ReflectionClass object, returning null');

                return Types::NULL;
            }

            $namespace = $this->getFirstPartWithNamespaceName($analyzedObject->getNamespaceName());
            if (!empty($namespace)) {

                $this->logger->trackVerbose('Get module name from reflection namespace');

                return $namespace;
            }

            $filename = $analyzedObject->getFileName();
            if ($filename !== false) {
                $basename = basename($filename, '.php');
                if (!str_starts_with($basename, 'temp_')
                    && !str_starts_with($basename, 'test_')
                    && $basename !== 'index'
                    && $basename !== 'autoload'
                ) {
                    $this->logger->trackVerbose('Get module name from reflection file name');

                    return $basename;
                }
            }

            $shortName = $analyzedObject->getShortName();
            if (!empty($shortName)) {
                $this->logger->trackVerbose('Get module name from reflection short name');

                return strtolower($shortName);
            }

            $this->logger->trackVerbose('No module name found, returning null (rare case in PHP)');

            return Types::NULL;

        } catch (Throwable $e) {
            $this->logger->trackException($e, ['message' => 'Unexpected error occurred while getting module name']);

            return Types::NULL;
        }
    }

    function getFirstPartWithNamespaceName(string $namespaceName): string
    {
        $pos = strpos($namespaceName, '\\');

        return false === $pos ? $namespaceName : substr($namespaceName, 0, $pos);
    }
}
