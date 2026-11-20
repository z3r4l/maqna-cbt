<?php

use App\Policies\SchoolScopePolicy;
use PHPUnit\Framework\TestCase;

final class SchoolScopePolicyTest extends TestCase
{
    public function testAdminCanResetStudentInOwnSchool(): void
    {
        self::assertTrue(SchoolScopePolicy::canResetStudent(['school_admin'], true, 1, 1, true));
    }

    public function testStudentAndTeacherCannotReset(): void
    {
        self::assertFalse(SchoolScopePolicy::canResetStudent(['student'], false, 1, 1, true));
        self::assertFalse(SchoolScopePolicy::canResetStudent(['teacher'], true, 1, 1, true));
    }

    public function testCrossSchoolAndNonStudentAreDenied(): void
    {
        self::assertFalse(SchoolScopePolicy::canResetStudent(['school_admin'], true, 1, 2, true));
        self::assertFalse(SchoolScopePolicy::canResetStudent(['school_admin'], true, 1, 1, false));
    }
}
