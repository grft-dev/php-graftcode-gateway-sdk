<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Gpge;

use Throwable;

final class GpgeErrorProtocol
{
    public const string WIRE_PREFIX = 'GRAFT';
    public const int MAX_REASON_PHRASE_LENGTH = 200;

    private const array MESSAGE_INFERENCE_ORDER = [
        'FRAMEWORK_TYPE_IN_PUBLIC_API',
        'UNSUPPORTED_TECHNOLOGY',
        'INVALID_REQUEST',
        'PACKAGE_NOT_FOUND',
        'SERVICE_UNAVAILABLE',
    ];

  /** @var array<string, array{httpStatus:int,messageTemplate:string,exceptionTypes:list<string>,messagePatterns:list<string>}> */
    private const array DEFINITIONS = [
        'FRAMEWORK_TYPE_IN_PUBLIC_API' => [
            'httpStatus' => 422,
            'messageTemplate' => 'Using complex types from framework in public interfaces is not supported yet. Type used %s',
            'exceptionTypes' => ['UnsupportedTypeUsageException'],
            'messagePatterns' => ['using complex types from framework in public interfaces'],
        ],
        'UNSUPPORTED_TECHNOLOGY' => [
            'httpStatus' => 422,
            'messageTemplate' => 'The requested technology is not supported for this graft operation.',
            'exceptionTypes' => ['NotSupportedException'],
            'messagePatterns' => [
                'not supported',
                'unsupported',
                'not yet supported',
                'not yet supported by gpge',
                'is not supported for getting original package name',
                'no compilation for',
            ],
        ],
        'PACKAGE_NOT_FOUND' => [
            'httpStatus' => 404,
            'messageTemplate' => 'The requested package or version was not found.',
            'exceptionTypes' => ['FileNotFoundException'],
            'messagePatterns' => ['no ugm file found', 'package not found'],
        ],
        'INVALID_REQUEST' => [
            'httpStatus' => 400,
            'messageTemplate' => 'The package request is invalid.',
            'exceptionTypes' => ['ArgumentException', 'ArgumentNullException', 'InvalidOperationException'],
            'messagePatterns' => ['invalid package', 'cannot be null', 'malformed', 'parse error'],
        ],
        'SERVICE_UNAVAILABLE' => [
            'httpStatus' => 503,
            'messageTemplate' => 'The graft service is busy. Please try again shortly.',
            'exceptionTypes' => [],
            'messagePatterns' => ['service saturated', 'too many concurrent', 'timeout', 'unavailable', 'overloaded'],
        ],
        'INTERNAL_ERROR' => [
            'httpStatus' => 500,
            'messageTemplate' => 'Package generation failed. Please try again later.',
            'exceptionTypes' => [],
            'messagePatterns' => [],
        ],
    ];

    public static function formatWireMessage(string $code, string $publicMessage): string
    {
        return '[' . self::WIRE_PREFIX . ':' . $code . '] ' . $publicMessage;
    }

    public static function formatTemplate(string $code, string ...$args): string
    {
        $template = self::DEFINITIONS[$code]['messageTemplate'];
        if ($args === []) {
            return $template;
        }

        try {
            return vsprintf($template, $args);
        } catch (\ValueError) {
            return $template;
        }
    }

    public static function formatCodedMessage(string $code, string ...$args): string
    {
        return self::formatWireMessage($code, self::formatTemplate($code, ...$args));
    }

    public static function resolve(Throwable $throwable): GpgePublicError
    {
        $current = $throwable;
        $visited = [];

        while ($current !== null && !isset($visited[spl_object_id($current)])) {
            $visited[spl_object_id($current)] = true;
            $parsed = self::tryParseWireMessage($current->getMessage());
            if ($parsed !== null && $parsed->code !== 'INTERNAL_ERROR') {
                return $parsed;
            }
            $current = $current->getPrevious();
        }

        $inferred = self::tryInferCode($throwable);
        if ($inferred !== null) {
            return $inferred;
        }

        return self::toPublicError('INTERNAL_ERROR', self::DEFINITIONS['INTERNAL_ERROR']['messageTemplate']);
    }

    public static function resolveFromMessage(string $message): GpgePublicError
    {
        if (trim($message) === '') {
            return self::toPublicError('INTERNAL_ERROR', self::DEFINITIONS['INTERNAL_ERROR']['messageTemplate']);
        }

        $parsed = self::tryParseWireMessage($message);
        if ($parsed !== null && $parsed->code !== 'INTERNAL_ERROR') {
            return $parsed;
        }

        $lowerMessage = strtolower(self::firstLine($message));
        foreach (self::MESSAGE_INFERENCE_ORDER as $code) {
            $definition = self::DEFINITIONS[$code];
            foreach ($definition['messagePatterns'] as $pattern) {
                if (str_contains($lowerMessage, $pattern)) {
                    return self::toPublicError($code, self::buildInferredPublicMessage($code, $message));
                }
            }
        }

        return self::toPublicError('INTERNAL_ERROR', self::DEFINITIONS['INTERNAL_ERROR']['messageTemplate']);
    }

    public static function formatReasonPhrase(string $publicMessage): string
    {
        $singleLine = self::firstLine($publicMessage);
        if ($singleLine === '') {
            return 'Graft package error';
        }
        if (strlen($singleLine) <= self::MAX_REASON_PHRASE_LENGTH) {
            return $singleLine;
        }

        return substr($singleLine, 0, self::MAX_REASON_PHRASE_LENGTH - 3) . '...';
    }

    private static function tryParseWireMessage(string $message): ?GpgePublicError
    {
        if (trim($message) === '') {
            return null;
        }

        if (!preg_match('/^\[GRAFT:([A-Z0-9_]+)\]\s*(.*)$/s', trim($message), $matches)) {
            return null;
        }

        $code = $matches[1];
        if (!isset(self::DEFINITIONS[$code])) {
            return null;
        }

        return self::toPublicError($code, trim($matches[2]));
    }

    private static function tryInferCode(Throwable $throwable): ?GpgePublicError
    {
        $current = $throwable;
        $visited = [];

        while ($current !== null && !isset($visited[spl_object_id($current)])) {
            $visited[spl_object_id($current)] = true;
            $typeName = $current::class;
            $shortTypeName = substr($typeName, strrpos($typeName, '\\') + 1);
            $lowerMessage = strtolower(self::firstLine($current->getMessage()));

            foreach (self::MESSAGE_INFERENCE_ORDER as $code) {
                $definition = self::DEFINITIONS[$code];
                if (self::matchesDefinition($definition, $shortTypeName, $typeName, $lowerMessage)) {
                    return self::toPublicError($code, self::buildInferredPublicMessage($code, $current->getMessage()));
                }
            }

            $current = $current->getPrevious();
        }

        return null;
    }

    /** @param array{httpStatus:int,messageTemplate:string,exceptionTypes:list<string>,messagePatterns:list<string>} $definition */
    private static function matchesDefinition(array $definition, string $typeName, string $fullTypeName, string $lowerMessage): bool
    {
        foreach ($definition['exceptionTypes'] as $exceptionType) {
            if ($typeName === $exceptionType || str_contains($fullTypeName, $exceptionType)) {
                return true;
            }
        }

        foreach ($definition['messagePatterns'] as $pattern) {
            if (str_contains($lowerMessage, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private static function buildInferredPublicMessage(string $code, string $rawMessage): string
    {
        if ($code === 'FRAMEWORK_TYPE_IN_PUBLIC_API') {
            $marker = 'type used ';
            $index = stripos($rawMessage, $marker);
            if ($index !== false) {
                $typeName = self::firstLine(substr($rawMessage, $index + strlen($marker)));
                if ($typeName !== '') {
                    return self::formatTemplate($code, $typeName);
                }
            }
        }

        if ($code === 'INVALID_REQUEST' && self::isSafeDeveloperMessage($rawMessage)) {
            return self::firstLine($rawMessage);
        }

        return self::DEFINITIONS[$code]['messageTemplate'];
    }

    private static function isSafeDeveloperMessage(string $message): bool
    {
        $firstLine = self::firstLine($message);
        if ($firstLine === '') {
            return false;
        }

        $lower = strtolower($firstLine);
        return !str_contains($lower, 'azure')
            && !str_contains($lower, 'blob')
            && !str_contains($lower, 'exception')
            && !str_contains($lower, 'stack')
            && !str_contains($lower, ' at ')
            && strlen($firstLine) <= 240;
    }

    private static function toPublicError(string $code, string $publicMessage): GpgePublicError
    {
        $definition = self::DEFINITIONS[$code];
        $message = trim($publicMessage) === '' ? $definition['messageTemplate'] : self::firstLine($publicMessage);

        return new GpgePublicError($code, $definition['httpStatus'], $message);
    }

    private static function firstLine(string $message): string
    {
        $parts = preg_split('/\R/', $message) ?: [];
        return trim($parts[0] ?? $message);
    }
}
