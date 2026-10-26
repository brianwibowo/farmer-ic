<?php

namespace App\Policies;

class AuditLogPolicy extends ResourcePolicy
{
    protected string $resource = 'audit_logs';

    protected bool $global = true;
}
