<?php

namespace App\Policies;

final class SchoolScopePolicy
{
    public static function canResetStudent(array $groups, bool $canManageUsers, int $actorSchool, int $targetSchool, bool $targetIsStudent): bool
    {
        return $canManageUsers
            && ($actorSchool === $targetSchool)
            && $targetIsStudent
            && (in_array('school_admin', $groups, true) || in_array('superadmin', $groups, true));
    }
}
