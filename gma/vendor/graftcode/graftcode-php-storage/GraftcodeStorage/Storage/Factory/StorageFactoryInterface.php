<?php

declare(strict_types=1);

namespace GraftcodeStorage\Storage\Factory;

use GraftcodeStorage\Storage\StorageInterface;

interface StorageFactoryInterface
{
    public static function createStorage(): StorageInterface;
}
