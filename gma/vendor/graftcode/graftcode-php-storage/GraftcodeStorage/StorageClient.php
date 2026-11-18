<?php

declare(strict_types=1);

namespace GraftcodeStorage;

use GraftcodeStorage\Storage\Factory\StorageFactory;
use GraftcodeStorage\Storage\StorageInterface;
use GraftcodeStorage\Utils\Exception\Storage\UnsupportedStorageTypeException;

final readonly class StorageClient implements StorageClientInterface
{
    private StorageInterface $storage;

    /**
     * @throws UnsupportedStorageTypeException
     */
    public function __construct()
    {
        $this->storage = StorageFactory::createStorage();
    }

    public function getStorage(): StorageInterface
    {
        return $this->storage;
    }
}
