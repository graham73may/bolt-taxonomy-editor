<?php

namespace Bolt\Extension\Soapbox\TaxonomyEditor\Exceptions;

use League\Flysystem\Exception;
use SplFileInfo;

class UnwriteableFileException extends Exception
{

    public static function forFileInfo(SplFileInfo $fileInfo)
    {

        return new static(sprintf('Unwritable file encountered: %s. Please manually set the files permissions', $fileInfo->getRealPath()));
    }
}
