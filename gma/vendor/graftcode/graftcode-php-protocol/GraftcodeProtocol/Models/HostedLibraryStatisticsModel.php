<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Models;

final class HostedLibraryStatisticsModel implements \JsonSerializable
{
    public function __construct(
        public string $name = '',
        public string $namespace = '',
        public string $runtime = '',
        public int $typesExposed = 0,
        public int $fieldsExposed = 0,
        public int $methodsExposed = 0,
    ) {
    }

    /**
     * @return array<string, int|string>
     */
    public function jsonSerialize(): array
    {
        return [
            'Name' => $this->name,
            'Namespace' => $this->namespace,
            'Runtime' => $this->runtime,
            'TypesExposed' => $this->typesExposed,
            'FieldsExposed' => $this->fieldsExposed,
            'MethodsExposed' => $this->methodsExposed,
        ];
    }
}
