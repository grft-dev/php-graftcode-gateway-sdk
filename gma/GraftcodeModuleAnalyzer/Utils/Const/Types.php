<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils\Const;

final readonly class Types
{
    public const string UNKNOWN = 'unknown';
    public const string ARRAY = 'array';
    public const string NULL = 'null';
    public const string ITERATOR = 'iterator';
    public const string GENERATOR = 'generator';
    public const string CALLABLE = 'callable';
    public const string CLOSURE = 'closure';
    public const string MIXED = 'mixed';
    public const string INT = 'int';
    public const string FLOAT = 'float';
    public const string STRING  = 'string';
    public const string BOOL = 'bool';
    public const string SELF = 'self';
    public const string PARENT = 'parent';
    public const string STATIC = 'static';
    public const string VOID = 'void';
}
