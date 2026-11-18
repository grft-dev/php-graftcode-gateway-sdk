<?php

declare(strict_types=1);

namespace GraftCodeLogger\Utils\VO;

use GraftCodeLogger\Utils\Uuid;

/**
 * Immutable configuration describing WHO the logger belongs to
 * (service + app-service name). The trace-id is no longer part of
 * this value object: it is a per-request concept managed by
 * LoggerClient::withOperationScope() instead. For backwards
 * compatibility a lazily-generated operationId is still exposed, but
 * new callers should NOT rely on it.
 */
final class LoggerClientOptions
{
    private ?Uuid $operationId = null;

    public function __construct(
        public readonly string $appServiceName,
        public readonly string $serviceName,
    ) {
    }

    /**
     * @deprecated Trace-ids must be managed per-request via
     *             LoggerClient::withOperationScope(). This method now
     *             lazily returns the same value for the client's
     *             lifetime only as a compatibility shim.
     */
    public function getOperationId(): string
    {
        if ($this->operationId === null) {
            $this->operationId = Uuid::uuid4();
        }
        return $this->operationId->toString();
    }
}
