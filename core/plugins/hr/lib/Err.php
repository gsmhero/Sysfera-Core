<?php
declare(strict_types=1);
namespace SysferaHr;

/** A refused request: a stable machine code (errCode), the HTTP status and optional extra data. */
final class Err extends \RuntimeException
{
    public function __construct(public readonly string $errCode, string $message = '', public readonly int $http = 400, public readonly array $extra = [])
    {
        parent::__construct($message !== '' ? $message : $errCode);
    }
}
