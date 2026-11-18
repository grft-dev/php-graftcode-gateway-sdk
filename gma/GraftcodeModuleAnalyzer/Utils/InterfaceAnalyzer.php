<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils;

use Exception;
use FilesystemIterator;
use GraftcodeModuleAnalyzer\Utils\{
    Const\Chars,
    Const\SpecialChars
};
use GraftcodeModuleAnalyzer\Core\Handler\BaseHandler;
use PhpParser\{
    Error as PhpParserError,
    Node,
    Node\Expr\Variable,
    Node\Stmt\Class_,
    Node\Stmt\Enum_,
    Node\Stmt\Function_,
    Node\Stmt\Interface_,
    Node\Stmt\Namespace_,
    Node\Stmt\Trait_,
    NodeTraverser,
    NodeVisitorAbstract,
    ParserFactory
};
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Throwable;

final class InterfaceAnalyzer extends BaseHandler
{
    /**
     * Returns global functions found in PHP files.
     * @return array<array{name: string, params: array<string>}>
     * @throws Exception
     */
    public function returnGlobalFunctionsFromPhp(string $path): array
    {
        if (false === file_exists($path)) {
            throw new Exception('Path does not exist: ' . $path);
        }

        $phpFiles = match (true) {
            is_file($path) && str_ends_with($path, '.php') => [$path],
            is_dir($path) => self::findPhpFilesRecursively($path),
            default => throw new Exception('Invalid path or not a PHP file: ' . $path)
        };

        $globalFunctions = [];
        $parser = new ParserFactory()->createForNewestSupportedVersion();

        foreach ($phpFiles as $phpFile) {
            try {
                $source = file_get_contents($phpFile);
                if (false === $source) {
                    continue;
                }

                $ast = $parser->parse($source);
                if (null === $ast) {
                    continue;
                }

                $functions = self::extractGlobalFunctionsFromAst($ast);
                $globalFunctions = array_merge($globalFunctions, $functions);

            } catch (PhpParserError $e) {
                $this->logger->trackException($e, [
                    'message' => 'PHP syntax error in file {phpFile}: {error}',
                    'phpFile' => $phpFile,
                    'error' => $e->getMessage()
                ]);
                continue;
            } catch (Exception $e) {
                $this->logger->trackException($e, ['message' => 'Error processing PHP file {$phpFile}']);
                continue;
            }
        }

        return $globalFunctions;
    }

    /**
     * @return array<ReflectionClass>
     * @throws Exception
     */
    public function returnClassesFromPhp(string $path, ?string $composerProjectRoot = null): array
    {
        if (false === file_exists($path)) {
            throw new Exception('Path does not exist: ' . $path);
        }

        $projectRoot = $composerProjectRoot ?? PackageAutoloader::resolveProjectRoot($path);
        if ($projectRoot !== null) {
            PackageAutoloader::registerFromProjectRoot($projectRoot);
        }

        $phpFiles = match (true) {
            is_file($path) && str_ends_with($path, '.php') => [$path],
            is_dir($path) => self::findPhpFilesRecursively($path),
            default => throw new Exception('Invalid path or not a PHP file: '. $path)
        };
        
        $classes = [];
        $parser = new ParserFactory()->createForNewestSupportedVersion();
        
        foreach ($phpFiles as $phpFile) {
            try {
                $source = file_get_contents($phpFile);
                if ($source === false) {
                    continue;
                }

                $ast = $parser->parse($source);
                if ($ast === null) {
                    continue;
                }

                $classNames = self::extractClassNamesFromAst($ast);

                foreach ($classNames as $className) {
                    if (
                        false === class_exists($className)
                        && false === interface_exists($className)
                        && false === trait_exists($className)
                        && false === enum_exists($className)
                    ) {
                        require_once $phpFile;
                    }

                    if (
                        class_exists($className, false)
                        || interface_exists($className, false)
                        || trait_exists($className, false)
                        || enum_exists($className, false)
                    ) {
                        $classes[] = new ReflectionClass($className);
                    }
                }

            } catch (PhpParserError $e) {
                // Handle PHP syntax errors gracefully
                $this->logger->trackException($e, [
                    'message' => 'PHP syntax error in file {phpFile}: {error}',
                    'phpFile' => $phpFile,
                    'error' => $e->getMessage()
                ]);
                continue;
            } catch (Exception|Throwable $e) {
                $this->logger->trackException($e, [
                    'message' => 'Error processing PHP file {phpFile}',
                    'phpFile' => $phpFile,
                ]);

                continue;
            }
        }

        return $classes;
    }

    private static function findPhpFilesRecursively(string $directory): array
    {
        $phpFiles = [];
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                $phpFiles[] = $file->getPathname();
            }
        }
        
        return $phpFiles;
    }

    /**
     * @param array<Node> $ast
     */
    private static function extractClassNamesFromAst(array $ast): array
    {
        $classNames = [];
        $currentNamespace = CHARS::EMPTY;
        
        $visitor = new class($classNames, $currentNamespace) extends NodeVisitorAbstract {
            public function __construct(
                private array &$classNames,
                private string &$currentNamespace
            ) {}
            
            public function enterNode(Node $node): void
            {
                match (true) {
                    $node instanceof Namespace_ => $this->processNamespace($node),
                    $node instanceof Class_ => $this->processClass($node),
                    $node instanceof Interface_ => $this->processInterface($node),
                    $node instanceof Trait_ => $this->processTrait($node),
                    $node instanceof Enum_ => $this->processEnum($node),
                    default => null
                };
            }
            
            private function processNamespace(Namespace_ $node): void {
                $this->currentNamespace = $node->name ? $node->name->toString() : CHARS::EMPTY;
            }
            
            private function processClass(Class_ $node): void {
                if ($node->name) {
                    $className = $this->buildFullyQualifiedName($node->name->toString());
                    $this->classNames[] = $className;
                }
            }
            
            private function processInterface(Interface_ $node): void {
                $interfaceName = $this->buildFullyQualifiedName($node->name->toString());
                $this->classNames[] = $interfaceName;
            }
            
            private function processTrait(Trait_ $node): void {
                $traitName = $this->buildFullyQualifiedName($node->name->toString());
                $this->classNames[] = $traitName;
            }
            
            private function processEnum(Enum_ $node): void {
                $enumName = $this->buildFullyQualifiedName($node->name->toString());
                $this->classNames[] = $enumName;
            }
            
            private function buildFullyQualifiedName(string $name): string {
                if (empty($this->currentNamespace)) {
                    return $name;
                }
                return $this->currentNamespace . SpecialChars::BACKSLASH . $name;
            }
        };
        
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);
        
        return $classNames;
    }

    /**
     * Extracts global function information from AST.
     * @param array<Node> $ast
     * @return array<array{name: string, params: array<string>}>
     */
    private static function extractGlobalFunctionsFromAst(array $ast): array
    {
        $functions = [];
        $currentNamespace = CHARS::EMPTY;

        $visitor = new class($functions, $currentNamespace) extends NodeVisitorAbstract {
            public function __construct(
                private array &$functions,
                private string &$currentNamespace
            ) {}

            public function enterNode(Node $node): void
            {
                if ($node instanceof Namespace_) {
                    $this->currentNamespace = $node->name ? $node->name->toString() : CHARS::EMPTY;
                } elseif ($node instanceof Function_) {
                    $this->processFunction($node);
                }
            }

            private function processFunction(Function_ $node): void
            {
                $functionName = $node->name->toString();

                // Build fully qualified function name
                if (!empty($this->currentNamespace)) {
                    $functionName = $this->currentNamespace . SpecialChars::BACKSLASH . $functionName;
                }

                // Extract parameter names
                $params = [];
                foreach ($node->params as $param) {
                    if ($param->var instanceof Variable && is_string($param->var->name)) {
                        $params[] = $param->var->name;
                    }
                }

                $this->functions[] = [
                    'name' => $functionName,
                    'params' => $params
                ];
            }
        };

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $functions;
    }
}
