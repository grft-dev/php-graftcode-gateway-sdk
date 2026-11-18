<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Handler\Shared;

use GraftcodeModuleAnalyzer\Core\Handler\{
    BaseHandler,
    Handlers,
    HandlersInterface
};
use GraftcodeModuleAnalyzer\Core\Handler\Shared\CommentDoc\{
    CommentDocBuildContext,
    CommentDocSectionHandlerInterface,
    CommentDocUgmFactory
};
use GraftcodeModuleAnalyzer\Utils\AnalyzerConfig;
use GraftcodeProtocol\{
    Enums\UnifiedGraftModelType,
    Models\UnifiedGraftModel
};
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionFunctionAbstract;
use Throwable;

/**
 * Build a COMMENT_DOC subtree from a PHPDoc block attached to a reflected
 * method, constructor, or global function.
 *
 * Returns NULL when the analyzed object exposes no doc-comment so callers
 * can skip appending an empty node to the parent payload.
 *
 * Wire-format mirrors the Python reference implementation
 * (see graftcode-module-analyzer/src/python/.../shared_comment_doc_handler.py):
 *
 *   COMMENT_DOC
 *     COMMENT_SUMMARY        : [text]
 *     COMMENT_DESCRIPTION    : [text]
 *     COMMENT_PARAMETERS_ARRAY [ COMMENT_PARAMETER, ... ]
 *     COMMENT_PARAMETER      : [name, raw_type, normalized_type,
 *                               description, is_required, is_nullable,
 *                               default_value]
 *     COMMENT_RETURNS        : [raw_type, normalized_type, description,
 *                               is_nullable]
 *     COMMENT_THROWS_ARRAY   [ COMMENT_THROW, ... ]
 *     COMMENT_THROW          : [raw_type, normalized_type, description]
 *     COMMENT_EXAMPLES_ARRAY [ COMMENT_EXAMPLE, ... ]
 *     COMMENT_EXAMPLE        : [title, language, code]
 *     COMMENT_LINKS_ARRAY    [ COMMENT_LINK, ... ]
 *     COMMENT_LINK           : [target, label, kind]
 *     COMMENT_DEPRECATED     : [is_deprecated, message, replacement]
 *     COMMENT_METADATA       : [author, since, version, license, tags]
 *     COMMENT_SOURCE         : [language, file, line_start, line_end,
 *                               comment_style, raw]
 *                               file = source filename only (not absolute path)
 */
final class SharedCommentDocHandler extends BaseHandler implements HandlersInterface
{
    /**
     * Append a COMMENT_DOC node via the registered shared handler when
     * documentation is present. Keeps parent payloads backward-compatible.
     */
    public static function appendToPayloadIfPresent(
        mixed $analyzedObject,
        UnifiedGraftModel $command,
        Handlers $handlers
    ): UnifiedGraftModel {
        $handler = $handlers->SHARED_HANDLER[UnifiedGraftModelType::COMMENT_DOC->value] ?? null;
        if (!$handler instanceof self) {
            return $command;
        }

        return $handler->appendToPayload($analyzedObject, $command, $handlers);
    }

    /**
     * Run {@see generateUnifiedGraftModel()} and append the result when
     * non-null. Extraction failures are logged and swallowed.
     */
    public function appendToPayload(
        mixed $analyzedObject,
        UnifiedGraftModel $command,
        Handlers $handlers
    ): UnifiedGraftModel {
        try {
            $node = $this->generateUnifiedGraftModel($analyzedObject, $command, $handlers);
        } catch (Throwable $e) {
            $this->logger->trackException($e, [
                'message' => 'CommentDoc extraction failed; continuing without doc node',
            ]);
            return $command;
        }

        if (!$node instanceof UnifiedGraftModel) {
            return $command;
        }

        return $command->addArgToPayload($node);
    }

    /**
     * @return UnifiedGraftModel|null COMMENT_DOC subtree, or NULL when the
     *                                analyzed object has no PHPDoc.
     */
    public function generateUnifiedGraftModel(
        mixed $analyzedObject,
        UnifiedGraftModel $parentCommand,
        Handlers $handlers
    ): ?UnifiedGraftModel {
        if (!$analyzedObject instanceof ReflectionFunctionAbstract) {
            return null;
        }

        $rawDoc = $analyzedObject->getDocComment();
        if (false === $rawDoc || trim($rawDoc) === '') {
            return null;
        }

        $parsed = $this->parseDocBlock($rawDoc);
        $context = new CommentDocBuildContext(
            $analyzedObject,
            $rawDoc,
            $parsed,
            AnalyzerConfig::shouldEmitCodeExamples()
        );

        $children = [];
        foreach ($handlers->COMMENT_DOC_SECTION_HANDLERS as $sectionHandler) {
            if (!$sectionHandler instanceof CommentDocSectionHandlerInterface) {
                continue;
            }
            $node = $sectionHandler->buildSection($context);
            if ($node instanceof UnifiedGraftModel) {
                $children[] = $node;
            }
        }

        if ($children === []) {
            return null;
        }

        return CommentDocUgmFactory::node(UnifiedGraftModelType::COMMENT_DOC, $children);
    }

    /**
     * Parse a PHPDoc string into a normalized associative array.
     *
     * Prefers `phpdocumentor/reflection-docblock` when available; otherwise
     * falls back to a minimal regex-based parser so the analyzer still emits
     * a usable (summary-only) COMMENT_DOC tree without the optional dep.
     *
     * @return array{
     *   summary: string, description: string,
     *   params: list<array{name:string,type:string,description:string,is_variadic:bool}>,
     *   returns: ?array{type:string,description:string},
     *   throws: list<array{type:string,description:string}>,
     *   examples: list<array{title:string,code:string}>,
     *   links: list<array{target:string,label:string,kind:string}>,
     *   deprecated: ?array{message:string,replacement:string},
     *   author: string, version: string, since: string, license: string, tags: string
     * }
     */
    private function parseDocBlock(string $rawDoc): array
    {
        if (class_exists(DocBlockFactory::class)) {
            try {
                return $this->parseWithDocBlockFactory($rawDoc);
            } catch (Throwable $e) {
                $this->logger->trackVerbose(
                    'Falling back to naive PHPDoc parser: {reason}',
                    ['reason' => $e->getMessage()]
                );
            }
        }
        return $this->parseNaively($rawDoc);
    }

    private function parseWithDocBlockFactory(string $rawDoc): array
    {
        $factory = DocBlockFactory::createInstance();
        $docBlock = $factory->create($rawDoc);

        $params = [];
        foreach ($docBlock->getTagsByName('param') as $tag) {
            $type = method_exists($tag, 'getType') ? (string)$tag->getType() : '';
            $name = method_exists($tag, 'getVariableName') ? (string)$tag->getVariableName() : '';
            $description = method_exists($tag, 'getDescription')
                ? trim((string)$tag->getDescription())
                : '';
            $isVariadic = method_exists($tag, 'isVariadic') && $tag->isVariadic();
            $params[] = [
                'name'        => $name,
                'type'        => $type,
                'description' => $description,
                'is_variadic' => $isVariadic,
            ];
        }

        $returns = null;
        $returnTags = $docBlock->getTagsByName('return');
        if (!empty($returnTags)) {
            $tag = $returnTags[0];
            $returns = [
                'type'        => method_exists($tag, 'getType') ? (string)$tag->getType() : '',
                'description' => method_exists($tag, 'getDescription')
                    ? trim((string)$tag->getDescription())
                    : '',
            ];
        }

        $throws = [];
        foreach ($docBlock->getTagsByName('throws') as $tag) {
            $throws[] = [
                'type'        => method_exists($tag, 'getType') ? (string)$tag->getType() : '',
                'description' => method_exists($tag, 'getDescription')
                    ? trim((string)$tag->getDescription())
                    : '',
            ];
        }

        $examples = [];
        foreach ($docBlock->getTagsByName('example') as $tag) {
            $rendered = trim((string)$tag);
            $title    = '';
            $code     = $rendered;
            if (method_exists($tag, 'getDescription')) {
                $title = trim((string)$tag->getDescription());
            }
            $examples[] = ['title' => $title, 'code' => $code];
        }

        $links = [];
        foreach ($docBlock->getTagsByName('see') as $tag) {
            $links[] = [
                'target' => trim((string)$tag),
                'label'  => '',
                'kind'   => 'see',
            ];
        }
        foreach ($docBlock->getTagsByName('link') as $tag) {
            $links[] = [
                'target' => trim((string)$tag),
                'label'  => '',
                'kind'   => 'link',
            ];
        }

        $deprecated = null;
        $deprecatedTags = $docBlock->getTagsByName('deprecated');
        if (!empty($deprecatedTags)) {
            $tag = $deprecatedTags[0];
            $deprecated = [
                'message'     => method_exists($tag, 'getDescription')
                    ? trim((string)$tag->getDescription())
                    : '',
                'replacement' => method_exists($tag, 'getVersion')
                    ? (string)$tag->getVersion()
                    : '',
            ];
        }

        return [
            'summary'     => trim((string)$docBlock->getSummary()),
            'description' => trim((string)$docBlock->getDescription()),
            'params'      => $params,
            'returns'     => $returns,
            'throws'      => $throws,
            'examples'    => $examples,
            'links'       => $links,
            'deprecated'  => $deprecated,
            'author'      => $this->joinTagValues($docBlock->getTagsByName('author')),
            'version'     => $this->joinTagValues($docBlock->getTagsByName('version')),
            'since'       => $this->joinTagValues($docBlock->getTagsByName('since')),
            'license'     => $this->joinTagValues($docBlock->getTagsByName('license')),
            'tags'        => '',
        ];
    }

    /**
     * @param iterable<object> $tags
     */
    private function joinTagValues(iterable $tags): string
    {
        $values = [];
        foreach ($tags as $tag) {
            $rendered = trim((string)$tag);
            if ($rendered !== '') {
                $values[] = $rendered;
            }
        }
        return implode('; ', $values);
    }

    private function parseNaively(string $rawDoc): array
    {
        $clean = $this->stripDocCommentDelimiters($rawDoc);

        $bodyLines = [];
        $params    = [];
        $returns   = null;
        $throws    = [];
        $examples  = [];
        $links     = [];
        $deprecated = null;
        $author = $version = $since = $license = '';

        foreach (preg_split('/\R/', $clean) ?: [] as $line) {
            $line = ltrim($line);
            if ($line === '') {
                $bodyLines[] = '';
                continue;
            }
            if ($line[0] !== '@') {
                $bodyLines[] = $line;
                continue;
            }

            $matches = [];
            if (preg_match('/^@(\w+)\s*(.*)$/', $line, $matches)) {
                $tag  = strtolower($matches[1]);
                $body = trim($matches[2]);
                $this->handleNaiveTag(
                    $tag,
                    $body,
                    $params,
                    $returns,
                    $throws,
                    $examples,
                    $links,
                    $deprecated,
                    $author,
                    $version,
                    $since,
                    $license
                );
            }
        }

        [$summary, $description] = $this->splitSummary($bodyLines);

        return [
            'summary'     => $summary,
            'description' => $description,
            'params'      => $params,
            'returns'     => $returns,
            'throws'      => $throws,
            'examples'    => $examples,
            'links'       => $links,
            'deprecated'  => $deprecated,
            'author'      => $author,
            'version'     => $version,
            'since'       => $since,
            'license'     => $license,
            'tags'        => '',
        ];
    }

    /**
     * @param array<int, array{name:string,type:string,description:string,is_variadic:bool}> $params
     * @param array{type:string,description:string}|null $returns
     * @param array<int, array{type:string,description:string}> $throws
     * @param array<int, array{title:string,code:string}> $examples
     * @param array<int, array{target:string,label:string,kind:string}> $links
     * @param array{message:string,replacement:string}|null $deprecated
     */
    private function handleNaiveTag(
        string $tag,
        string $body,
        array &$params,
        ?array &$returns,
        array &$throws,
        array &$examples,
        array &$links,
        ?array &$deprecated,
        string &$author,
        string &$version,
        string &$since,
        string &$license
    ): void {
        switch ($tag) {
            case 'param':
                $params[] = $this->parseParamLine($body);
                break;
            case 'return':
            case 'returns':
                $returns = $this->parseTypedDescription($body);
                break;
            case 'throws':
            case 'throw':
                $throws[] = $this->parseTypedDescription($body);
                break;
            case 'example':
                $examples[] = ['title' => '', 'code' => $body];
                break;
            case 'see':
                $links[] = ['target' => $body, 'label' => '', 'kind' => 'see'];
                break;
            case 'link':
                $links[] = ['target' => $body, 'label' => '', 'kind' => 'link'];
                break;
            case 'deprecated':
                $deprecated = ['message' => $body, 'replacement' => ''];
                break;
            case 'author':
                $author = $author === '' ? $body : "$author; $body";
                break;
            case 'version':
                $version = $body;
                break;
            case 'since':
                $since = $body;
                break;
            case 'license':
                $license = $body;
                break;
        }
    }

    /**
     * @return array{name:string,type:string,description:string,is_variadic:bool}
     */
    private function parseParamLine(string $body): array
    {
        $matches = [];
        if (preg_match('/^([^\s$]+)\s+(\.{3})?\$(\w+)\s*(.*)$/', $body, $matches)) {
            return [
                'name'        => $matches[3],
                'type'        => $matches[1],
                'description' => trim($matches[4]),
                'is_variadic' => $matches[2] !== '',
            ];
        }
        if (preg_match('/^(\.{3})?\$(\w+)\s*(.*)$/', $body, $matches)) {
            return [
                'name'        => $matches[2],
                'type'        => '',
                'description' => trim($matches[3]),
                'is_variadic' => $matches[1] !== '',
            ];
        }
        return ['name' => '', 'type' => '', 'description' => trim($body), 'is_variadic' => false];
    }

    /**
     * @return array{type:string,description:string}
     */
    private function parseTypedDescription(string $body): array
    {
        $body = trim($body);
        if ($body === '') {
            return ['type' => '', 'description' => ''];
        }
        $parts = preg_split('/\s+/', $body, 2);
        if ($parts === false || count($parts) === 0) {
            return ['type' => '', 'description' => ''];
        }
        $type = $parts[0] ?? '';
        $description = isset($parts[1]) ? trim($parts[1]) : '';
        return ['type' => $type, 'description' => $description];
    }

    /**
     * @param list<string> $bodyLines
     * @return array{0:string,1:string}
     */
    private function splitSummary(array $bodyLines): array
    {
        $blocks = [];
        $current = [];
        foreach ($bodyLines as $line) {
            if ($line === '') {
                if (!empty($current)) {
                    $blocks[] = implode("\n", $current);
                    $current = [];
                }
                continue;
            }
            $current[] = $line;
        }
        if (!empty($current)) {
            $blocks[] = implode("\n", $current);
        }
        $summary     = trim($blocks[0] ?? '');
        $description = trim(implode("\n\n", array_slice($blocks, 1)));
        return [$summary, $description];
    }

    private function stripDocCommentDelimiters(string $rawDoc): string
    {
        $stripped = preg_replace('/^\s*\/\*\*+/', '', $rawDoc);
        $stripped = preg_replace('/\*+\/\s*$/', '', $stripped ?? '');
        $stripped = preg_replace('/^\s*\*\s?/m', '', $stripped ?? '');
        return $stripped ?? '';
    }
}

