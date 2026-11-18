<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Models;

use GraftcodeProtocol\Enums\RuntimeName;
use GraftcodeProtocol\Enums\UnifiedGraftModelType;

interface UnifiedGraftModelInterface
{
    public function addArgToPayload(mixed $arg): UnifiedGraftModelInterface;
    public function getRuntimeName(): RuntimeName;
    public function getUnifiedGraftModelType(): UnifiedGraftModelType;
    public function getPayload(): array;
    public function getPayloadByIndex(int $index): mixed;
    public function findPayloadByIndex(int $index): mixed;
    public function getPayloadSize(): int;
}
