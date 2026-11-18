<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler;

final readonly class LibraryMetadata
{
    public function __construct(
        public string $namespace,
        public string $name,
        public string $version,
        public string $runtimeName,
        public string $gmaUgmVersion,
        public ?string $composerProjectRoot = null,
    ) {}

    public function getAssemblyName(): string
    {
        if ($this->namespace === '' && $this->name === '') {
            return '';
        }

        return $this->namespace !== ''
            ? $this->namespace . '/' . $this->name
            : $this->name;
    }
}
