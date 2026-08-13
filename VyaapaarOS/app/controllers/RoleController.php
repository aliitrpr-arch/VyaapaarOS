<?php

require_once __DIR__ . '/../models/Role.php';

class RoleController
{
    public static function create(array $data): int
    {
        $companyId = (int) $data['company_id'];

        $roleName = trim($data['role_name'] ?? '');

        $description = trim(
            $data['description'] ?? ''
        );

        if ($roleName === '') {
            throw new Exception(
                'Role name is required.'
            );
        }

        return Role::create(
            $companyId,
            $roleName,
            $description
        );
    }
}