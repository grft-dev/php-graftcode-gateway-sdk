<?php

declare(strict_types=1);

namespace GraftcodeStorage\Utils\Exception\File;

use Exception;
use Psr\Http\Message\{
    ResponseInterface,
    UriInterface
};
use Throwable;

final class UploadFileException extends Exception
{
    public function __construct(
        string $filePath,
        ResponseInterface $response,
        UriInterface $url,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            sprintf('Failed to upload file: %s to url: %s from reason: %s',
                $filePath,
                $url,
                $response->getReasonPhrase()
            ),
            $response->getStatusCode(),
            $previous
        );
    }
}
