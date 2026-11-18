<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Gpge;

final class GpgePublicError
{
    public function __construct(
        public readonly string $code,
        public readonly int $httpStatus,
        public readonly string $publicMessage,
    ) {
    }
}
