<?php

namespace Config;

use CodeIgniter\Shield\Config\AuthGroups as ShieldGroups;

class AuthGroups extends ShieldGroups
{
    public string $defaultGroup = 'student';

    public array $groups = [
        'superadmin' => ['title' => 'Superadmin', 'description' => 'System owner'],
        'school_admin' => ['title' => 'Admin sekolah', 'description' => 'School administration'],
        'teacher' => ['title' => 'Guru', 'description' => 'Assigned teaching staff'],
        'proctor' => ['title' => 'Pengawas', 'description' => 'Assigned proctor'],
        'student' => ['title' => 'Siswa', 'description' => 'Exam participant'],
    ];

    public array $permissions = [
        'school.manage' => 'Manage school settings',
        'academic.manage' => 'Manage academic data',
        'subjects.manage' => 'Manage subject and topic masters',
        'teaching.manage' => 'Manage teaching assignments',
        'users.manage' => 'Manage school users',
        'questions.manage' => 'Manage assigned question bank',
        'exams.manage' => 'Manage assigned exams',
        'exams.publish' => 'Publish authorized exams',
        'attempts.take' => 'Take assigned exams',
        'results.grade' => 'Grade assigned attempts',
        'results.release' => 'Release authorized results',
        'reports.view' => 'View authorized reports',
        'monitor.view' => 'Monitor assigned exams',
        'audit.view' => 'View scoped audit log',
    ];

    public array $matrix = [
        'superadmin' => ['school.*', 'academic.*', 'subjects.*', 'teaching.*', 'users.*', 'questions.*', 'exams.*', 'attempts.*', 'results.*', 'reports.*', 'monitor.*', 'audit.*'],
        'school_admin' => ['school.manage', 'academic.manage', 'subjects.manage', 'teaching.manage', 'users.manage', 'questions.manage', 'exams.manage', 'exams.publish', 'results.grade', 'results.release', 'reports.view', 'monitor.view', 'audit.view'],
        'teacher' => ['questions.manage', 'exams.manage', 'exams.publish', 'results.grade', 'results.release', 'reports.view', 'monitor.view'],
        'proctor' => ['monitor.view'],
        'student' => ['attempts.take'],
    ];
}
