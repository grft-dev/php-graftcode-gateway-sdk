<?php

declare(strict_types=1);

namespace GraftcodeModuleAnalyzer\Utils\Storage;

use Exception;
use GraftcodeModuleAnalyzer\Utils\Exception\FailedInitializeStorageClientException;
use GraftcodeModuleAnalyzer\Utils\Exception\UnserializeSingletonException;
use GraftcodeStorage\Storage\StorageInterface;
use GraftcodeStorage\StorageClient;

final class StorageManger
{
    public ?StorageInterface $storage = null {
        get {
            return $this->storage;
        }
    }

    /**
     * @throws FailedInitializeStorageClientException
     */
    public function __construct()
    {
        try {
            if (null === $this->storage) {
                $this->storage = new StorageClient()->getStorage();
            }

        } catch (Exception $e) {
            $this->reset();

            throw new FailedInitializeStorageClientException($e);
        }
    }

    private function __clone()
    {}

    /**
     * @throws Exception
     */
    public function __wakeup()
    {
        throw new UnserializeSingletonException();
    }

    private function reset(): void
    {
        $this->storage = null;
    }
}
