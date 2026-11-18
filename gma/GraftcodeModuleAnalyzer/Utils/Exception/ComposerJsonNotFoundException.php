<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils\Exception;

final class ComposerJsonNotFoundException extends DependencyTreeBuildException
{
    public function __construct(string $workingPath)
    {
        return parent::__construct(sprintf('Cannot build dependency tree: composer.json not found in %s', $workingPath));
    }
}



