<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Models;

final class DependencyTreeModel implements \JsonSerializable
{
    /**
     * @param DependencyTreeModel[] $dependencyChildren
     */
    public function __construct(
        public string $dependencyNamespace = '',
        public string $dependencyName = '',
        public string $dependencyVersion = '',
        public array $dependencyChildren = [],
        public ?string $requiredConstraint = null,
        public ?bool $isVersionSatisfied = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $children = [];
        foreach ($data['DependencyChildren'] ?? [] as $child) {
            if (is_array($child)) {
                $children[] = self::fromArray($child);
            }
        }

        return new self(
            dependencyNamespace: is_string($data['DependencyNamespace'] ?? null) ? $data['DependencyNamespace'] : '',
            dependencyName: is_string($data['DependencyName'] ?? null) ? $data['DependencyName'] : '',
            dependencyVersion: is_string($data['DependencyVersion'] ?? null) ? $data['DependencyVersion'] : '',
            dependencyChildren: $children,
            requiredConstraint: is_string($data['RequiredConstraint'] ?? null) ? $data['RequiredConstraint'] : null,
            isVersionSatisfied: is_bool($data['IsVersionSatisfied'] ?? null) ? $data['IsVersionSatisfied'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $result = [
            'DependencyNamespace' => $this->dependencyNamespace,
            'DependencyName' => $this->dependencyName,
            'DependencyVersion' => $this->dependencyVersion,
            'DependencyChildren' => array_map(
                static fn (DependencyTreeModel $child): array => $child->jsonSerialize(),
                $this->dependencyChildren,
            ),
        ];

        if ($this->requiredConstraint !== null) {
            $result['RequiredConstraint'] = $this->requiredConstraint;
        }

        if ($this->isVersionSatisfied !== null) {
            $result['IsVersionSatisfied'] = $this->isVersionSatisfied;
        }

        return $result;
    }
}
