<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Enums;

/**
 * Represents different package managers and their version format conventions.
 */
enum PackageManager: string
{
    case NUGET = 'nuget';
    case PYTHON = 'python';
    case NPM = 'npm';
    case MAVEN = 'maven';
    case RUBYGEMS = 'rubygems';
    case CANONICAL = 'canonical';
}
