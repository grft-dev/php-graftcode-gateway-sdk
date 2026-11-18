<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler;

use GraftcodeProtocol\Models\UnifiedGraftModel;

interface HandlersInterface
{
    public function generateUnifiedGraftModel(mixed $analyzedObject, UnifiedGraftModel $parentCommand, Handlers $handlers): mixed;
}
