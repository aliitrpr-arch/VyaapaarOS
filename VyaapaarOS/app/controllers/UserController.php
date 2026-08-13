<?php

require_once __DIR__ . '/../models/User.php';

class UserController
{
    public static function create(array $data): int
    {
        $companyId = (int) $data['company_id'];

        $branchId = !empty($data['branch_id'])
            ? (int) $data['branch_id']
            : null;

        $roleId = (int) $data['role_id'];

        $username = trim($data['username'] ?? '');

        $password = $data['password'] ?? '';

        $fullName = trim($data['full_name'] ?? '');

        if ($username === '') {
            throw new Exception('Username is required.');
        }

        if ($fullName === '') {
            throw new Exception('Full name is required.');
        }

        if (strlen($password) < 6) {
            throw new Exception(
                'Password must be at least 6 characters.'
            );
        }

        return User::create(
            $companyId,
            $branchId,
            $roleId,
            $username,
            $password,
            $fullName,
            true
        );
    }
}