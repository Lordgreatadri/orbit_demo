<?php

namespace App\Enums;

enum LeaveApprovalMode: string
{
    case AnyApprover = 'any_approver';
    case LineManager = 'line_manager';
    case DepartmentHead = 'department_head';

    public function label(): string
    {
        return match ($this) {
            self::AnyApprover => 'Any approver',
            self::LineManager => 'Line manager',
            self::DepartmentHead => 'Department head',
        };
    }
}
