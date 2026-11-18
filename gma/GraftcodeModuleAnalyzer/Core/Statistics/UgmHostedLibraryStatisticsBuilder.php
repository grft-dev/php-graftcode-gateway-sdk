<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Core\Statistics;

use GraftcodeProtocol\Enums\RuntimeName;
use GraftcodeProtocol\Enums\UnifiedGraftModelType;
use GraftcodeProtocol\Models\HostedLibraryStatisticsModel;
use GraftcodeProtocol\Models\UnifiedGraftModel;
use GraftcodeProtocol\Protocol\Deserializers\UnifiedGraftModelDeserializer;

final class UgmHostedLibraryStatisticsBuilder
{
    public static function build(array $ugmBytes): HostedLibraryStatisticsModel
    {
        $deserializer = new UnifiedGraftModelDeserializer($ugmBytes);
        return self::buildFromModel($deserializer->deserialize());
    }

    public static function buildFromModel(UnifiedGraftModel $ugm): HostedLibraryStatisticsModel
    {
        $counters = [
            'types' => 0,
            'fields' => 0,
            'methods' => 0,
        ];
        self::collectExposureCounts($ugm, $counters);

        $libraryNamespace = '';
        $libraryName = '';
        if ($ugm->getUnifiedGraftModelType() === UnifiedGraftModelType::LIBRARY && count($ugm->getPayload()) >= 2) {
            $payload = $ugm->getPayload();
            $libraryNamespace = (string) ($payload[0] ?? '');
            $libraryName = (string) ($payload[1] ?? '');
        }

        return new HostedLibraryStatisticsModel(
            name: $libraryName,
            namespace: $libraryNamespace,
            runtime: self::runtimeNameToString($ugm->getRuntimeName()),
            typesExposed: $counters['types'],
            fieldsExposed: $counters['fields'],
            methodsExposed: $counters['methods'],
        );
    }

    /**
     * @param array{types: int, fields: int, methods: int} $counters
     */
    private static function collectExposureCounts(UnifiedGraftModel $node, array &$counters): void
    {
        $nodeType = $node->getUnifiedGraftModelType();
        if (in_array($nodeType, [
            UnifiedGraftModelType::TYPE_DEF,
            UnifiedGraftModelType::MODULE,
            UnifiedGraftModelType::ENUM,
            UnifiedGraftModelType::GENERIC_TYPE_DEF,
        ], true)) {
            $counters['types']++;
        } elseif (in_array($nodeType, [
            UnifiedGraftModelType::STATIC_FIELD,
            UnifiedGraftModelType::INSTANCE_FIELD,
            UnifiedGraftModelType::GLOBAL_FIELD,
            UnifiedGraftModelType::PROPERTY,
        ], true)) {
            $counters['fields']++;
        } elseif (in_array($nodeType, [
            UnifiedGraftModelType::STATIC_METHOD,
            UnifiedGraftModelType::INSTANCE_METHOD,
            UnifiedGraftModelType::CONSTRUCTOR,
            UnifiedGraftModelType::GLOBAL_METHOD,
            UnifiedGraftModelType::DESTRUCTOR,
            UnifiedGraftModelType::GENERIC_STATIC_METHOD,
            UnifiedGraftModelType::GENERIC_INSTANCE_METHOD,
            UnifiedGraftModelType::OVERLOADED_CONSTRUCTOR,
            UnifiedGraftModelType::OVERLOADED_STATIC_METHOD,
            UnifiedGraftModelType::OVERLOADED_INSTANCE_METHOD,
            UnifiedGraftModelType::OVERLOADED_GLOBAL_METHOD,
        ], true)) {
            $counters['methods']++;
        }

        foreach ($node->getPayload() as $payloadItem) {
            if ($payloadItem instanceof UnifiedGraftModel) {
                self::collectExposureCounts($payloadItem, $counters);
            }
        }
    }

    private static function runtimeNameToString(RuntimeName $runtimeName): string
    {
        return $runtimeName->name;
    }
}
