<?php

namespace App\Services;

final class PermissionPolicy
{
    public const STAFF_PERMISSIONS = ['questions.manage', 'exams.manage', 'exams.publish', 'results.grade', 'results.release', 'reports.view', 'monitor.view'];

    public static function allows($user, string $permission): bool
    {
        if (! $user->can($permission)) return false;
        if (! in_array($permission, self::STAFF_PERMISSIONS, true) || $user->inGroup('school_admin', 'superadmin')) return true;
        $profile = db_connect()->table('user_profiles')->select('school_id')->where('user_id', $user->id)->where('active', 1)->get()->getRowArray();
        if ($profile === null) return false;
        $rule = db_connect()->table('user_permission_rules')->select('denied')->where('school_id', $profile['school_id'])->where('user_id', $user->id)->where('permission', $permission)->get()->getRowArray();
        return $rule === null || (int) $rule['denied'] === 0;
    }
}
