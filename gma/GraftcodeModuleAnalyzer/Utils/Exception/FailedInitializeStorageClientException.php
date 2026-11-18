<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils\Exception;

use Exception;

final class FailedInitializeStorageClientException extends Exception
{
    public function __construct(Exception $e)
    {
        return parent::__construct('Failed to initialize StorageClient: '. $e->getMessage(), previous: $e);
    }
}
