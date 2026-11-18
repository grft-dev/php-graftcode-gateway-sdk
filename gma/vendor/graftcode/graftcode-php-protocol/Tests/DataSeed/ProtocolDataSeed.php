<?php

declare(strict_types=1);

namespace Tests\DataSeed;

use GraftcodeProtocol\Enums\{
    RuntimeName,
    UnifiedGraftModelType
};
use GraftcodeProtocol\Models\{
    UnifiedGraftModel,
    UnifiedGraftModelInterface
};

final readonly class ProtocolDataSeed
{
    public static function getNestedRubyCommand(): UnifiedGraftModelInterface
    {
        return new UnifiedGraftModel(
            RuntimeName::RUBY,
            UnifiedGraftModelType::CAST,
            [
                new UnifiedGraftModel(
                    RuntimeName::RUBY,
                    UnifiedGraftModelType::GLOBAL_FIELD,
                    [
                        new UnifiedGraftModel(
                            RuntimeName::RUBY,
                            UnifiedGraftModelType::REF,
                            ["abc"]
                        ),
                        "ClassForStaticTest"
                    ],
                ),
                'three_numbers_adder',
                5,
                10,
                15
            ]
        );
    }

    public static function getNestedPhpCommand(): UnifiedGraftModelInterface
    {
        return new UnifiedGraftModel(
            RuntimeName::PHP,
            UnifiedGraftModelType::TYPE_DEF,
            [
                new UnifiedGraftModel(
                    RuntimeName::PHP,
                    UnifiedGraftModelType::PROPERTY,
                    [
                        1,
                        -0.1234567891011121314,
                        "numpy",
                        -536,
                        new UnifiedGraftModel(
                            RuntimeName::PHP,
                            UnifiedGraftModelType::RETURN_TYPE,
                            [
                                4,
                                new UnifiedGraftModel(
                                    RuntimeName::PHP,
                                    UnifiedGraftModelType::ARRAY,
                                    [5]
                                ),
                                6
                            ]
                        )
                    ]
                )
            ]
        );
    }

    public static function getNestedCommandJava(): UnifiedGraftModelInterface
    {
        return new UnifiedGraftModel(
            RuntimeName::JVM,
            UnifiedGraftModelType::GENERIC_INSTANCE_METHOD,
            [
                new UnifiedGraftModel(
                    RuntimeName::JVM,
                    UnifiedGraftModelType::PARAMETER,
                    'java.lang.Math'
                ),
                "a",
                -536,
                -214748364,
                new UnifiedGraftModel(
                    RuntimeName::JVM,
                    UnifiedGraftModelType::NAMESPACE,
                    [
                        "PI",
                        -9223372036854775808,
                        new UnifiedGraftModel(
                            RuntimeName::JVM,
                            UnifiedGraftModelType::INSTANCE_FIELD,
                            ["java.lang.Math"]
                        ),
                        "3.15",
                        3.14,
                        'Ť¿ϻÐßĦŁ',
                        'Ħ'
                    ]
                )
            ]
        );
    }
    public const  array NULL_SERIALIZED = [11, 1, 0];
    public const array CHAR_SERIALIZED = [6, 1, 91];
    public const string CHAR_DESERIALIZED = '[';
    public const string ASCII_STRING_DESERIALIZED = 'ABCDE';
    public const array ASCII_STRING_SERIALIZED = [1, 1, 5, 0, 0, 0, 65, 66, 67, 68, 69];
    public const string NO_ASCII_STRING_DESERIALIZED = 'Ť¿ϻÐßĦŁ';
    public const array NO_ASCII_STRING_SERIALIZED = [1, 1, 14, 0, 0, 0, 197, 164, 194, 191, 207, 187, 195, 144, 195, 159, 196, 166, 197, 129];
    public const string UTF16LE_STRING_DESERIALIZED = "\x41\x00\x42\x00\x43\x00\x44\x00\x45\x00";
    public const string NO_ASCII_UTF16LE_STRING_DESERIALIZED = "\x64\x01\xBF\x00\xFB\x03\xD0\x00\xDF\x00\x26\x01\x41\x01";
    public const string EMPTY_UTF16LE_STRING_DESERIALIZED = "\x00\x00";
    public const string UTF32LE_STRING_DESERIALIZED = "\x41\x00\x00\x00\x42\x00\x00\x00\x43\x00\x00\x00\x44\x00\x00\x00\x45\x00\x00\x00";
    public const string NO_ASCII_UTF32LE_STRING_DESERIALIZED = "\x64\x01\x00\x00\xBF\x00\x00\x00\xFB\x03\x00\x00\xD0\x00\x00\x00\xDF\x00\x00\x00\x26\x01\x00\x00\x41\x01\x00\x00";
    public const string EMPTY_UTF32LE_STRING_DESERIALIZED = "\x00\x00\x00\x00";
    public const string EMPTY_STRING_DESERIALIZED = '';
    public const array EMPTY_STRING_SERIALIZED = [1, 1, 0, 0, 0, 0];
    public const int INT_DESERIALIZED = 2137;
    public const array INT_SERIALIZED = [2, 4, 89, 8, 0, 0];
    public const int INT_MAX_DESERIALIZED = 2147483647;
    public const array INT_MAX_SERIALIZED = [2, 4, 255, 255, 255, 127];
    public const int INT_MIN_DESERIALIZED = -2147483648;
    public const array INT_MIN_SERIALIZED = [2, 4, 0, 0, 0, 128];
    public const array BOOL_SERIALIZED_TRUE = [3, 1, 1];
    public const array BOOL_SERIALIZED_FALSE = [3, 1, 0];
    public const true BOOL_DESERIALIZED_TRUE = true;
    public const false BOOL_DESERIALIZED_FALSE = false;
    public const array FLOAT_SERIALIZED = [4, 4, 195, 245, 170, 193];
    public const float FLOAT_DESERIALIZED = -21.37;
    public const float FLOAT_MAX_DESERIALIZED = 1.7976931348623157E+308;
    public const array FLOAT_MAX_SERIALIZED = [4, 4, 0, 0, 128, 127];
    public const float FLOAT_MIN_DESERIALIZED = 2.2250738585072014E-308;
    public const array FLOAT_MIN_SERIALIZED = [4, 4, 0, 0, 0, 0];
    public const int BYTE_DESERIALIZED = 90;
    public const array BYTE_SERIALIZED = [5, 1, 90];
    public const int LONG_DESERIALIZED = -18014398386025195;
    public const array LONG_SERIALIZED = [7, 8, 21, 205, 91, 7, 0, 0, 192, 255];
    public const int LONG_MAX_DESERIALIZED = 9223372036854775807;
    public const array LONG_MAX_SERIALIZED = [7, 8, 255, 255, 255, 255, 255, 255, 255, 127];
    public const int LONG_MIN_DESERIALIZED = -9223372036854775807;
    public const array LONG_MIN_SERIALIZED = [7, 8, 1, 0, 0, 0, 0, 0, 0, 128];
    public const float DOUBLE_DESERIALIZED = 0.987654321;
    public const array DOUBLE_SERIALIZED = [8, 8, 184, 86, 14, 60, 221, 154, 239, 63];
    public const array COMMAND_EMPTY_PAYLOAD_SERIALIZED = [0, 1, 0, 0, 0, 6, 13];
    public const array COMMAND_WITH_1D_STRING_ARRAY_PAYLOAD_SERIALIZED = [0, 1, 0, 0, 0, 9, 35];
    public const array COMMAND_WITH_STING_AND_NUMBERS_PAYLOAD_SERIALIZED = [0, 4, 0, 0, 0, 10, 5];
    public const array COMMAND_WITH_STRING_PAYLOAD_SERIALIZED = [0, 1, 0, 0, 0, 5, 19];
    public const array COMMAND_WITH_MIXED_PAYLOAD_SERIALIZED = [0, 1, 0, 0, 0, 2, 18];
    public const array COMMAND_WITH_MULTI_MIXED_PAYLOAD_SERIALIZED = [0, 5, 0, 0, 0, 6, 7];
}
