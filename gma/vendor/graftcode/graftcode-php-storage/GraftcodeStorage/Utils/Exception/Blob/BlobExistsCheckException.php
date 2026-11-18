<?php

declare(strict_types=1);

namespace GraftcodeStorage\Utils\Exception\Blob;

use Exception;
use Psr\Http\Message\{
    ResponseInterface,
    UriInterface
};
use Throwable;

final class BlobExistsCheckException extends Exception
{
    public function __construct(
        ResponseInterface $response,
        string $storageBlobPath,
        UriInterface $url,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'Failed to check blob existence for path: %s reason: %s from url: %s',
                $storageBlobPath,
                $response->getReasonPhrase(),
                $url,
            ),
            $response->getStatusCode(),
            $previous,
        );
    }
}
