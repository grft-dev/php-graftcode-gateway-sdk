<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler\Shared\CommentDoc;

use ReflectionFunctionAbstract;

/**
 * Parsed PHPDoc plus reflection context passed to CommentDoc section handlers.
 *
 * @phpstan-type ParsedDoc array{
 *   summary: string,
 *   description: string,
 *   params: list<array{name:string,type:string,description:string,is_variadic:bool}>,
 *   returns: ?array{type:string,description:string},
 *   throws: list<array{type:string,description:string}>,
 *   examples: list<array{title:string,code:string}>,
 *   links: list<array{target:string,label:string,kind:string}>,
 *   deprecated: ?array{message:string,replacement:string},
 *   author: string,
 *   version: string,
 *   since: string,
 *   license: string,
 *   tags: string
 * }
 */
final class CommentDocBuildContext
{
    /**
     * @param ParsedDoc $parsed
     */
    public function __construct(
        public readonly ReflectionFunctionAbstract $reflection,
        public readonly string $rawDoc,
        public readonly array $parsed,
        public readonly bool $emitCodeExamples,
    ) {
    }
}
