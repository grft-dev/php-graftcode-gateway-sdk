<?php

declare(strict_types=1);

namespace GraftcodeStorage;

use GraftcodeStorage\Storage\StorageInterface;

interface StorageClientInterface
{
    public function getStorage(): StorageInterface;
}
