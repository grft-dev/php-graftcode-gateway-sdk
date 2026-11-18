<?php

declare(strict_types=1);

namespace GraftcodeStorage\Utils\Exception\File;

use Exception;
use Psr\Http\Message\{
    ResponseInterface,
    UriInterface
};
use Throwable;

final class DeleteFileException extends Exception
{
    public function __construct(
        ResponseInterface $response,
        UriInterface $url,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'Failed to delete file reason phrase: %s from url: %s',
                $response->getReasonPhrase(),
                    $url
            ),
            $response->getStatusCode(),
            $previous,
        );
    }
}
