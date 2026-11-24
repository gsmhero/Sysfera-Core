<?php
declare(strict_types=1);
namespace SysferaStoragerent;

/** A refused action. errCode is a translation key (err_<code>), http the status for the API answer. */
final class Err extends \RuntimeException
{
    public function __construct(public readonly string $errCode, string $message = '', public readonly int $http = 400)
    {
        parent::__construct($message !== '' ? $message : $errCode);
    }
}
