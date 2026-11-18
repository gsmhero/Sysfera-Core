<?php
declare(strict_types=1);
namespace SysferaContracts;

/** A refused action. errCode is a translation key (err_<code>), http the status for the page. */
final class Err extends \RuntimeException
{
    public function __construct(public readonly string $errCode, string $message = '', public readonly int $http = 400, public readonly array $extra = [])
    {
        parent::__construct($message !== '' ? $message : $errCode);
    }
}
