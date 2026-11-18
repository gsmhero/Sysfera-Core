<?php
declare(strict_types=1);
namespace SysferaContracts;

/** Who is acting: a Sysfera user (administrators see and may do everything an author may). */
final class Ctx
{
    public function __construct(public readonly int $uid, public readonly bool $admin = false, public readonly string $name = '') {}
}
