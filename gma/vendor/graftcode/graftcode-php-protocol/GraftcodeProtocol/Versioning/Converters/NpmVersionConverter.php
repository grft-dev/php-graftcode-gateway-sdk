<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Versioning\Converters;

final readonly class NpmVersionConverter extends NuGetVersionConverter
{
    // npm uses SemVer 2.0, same as NuGet
}

