<?php

require_once __DIR__ . '/../models/Permission.php';

class PermissionController
{
    public static function save(int $userId): void
    {
        $permissions = $_POST['permissions'] ?? [];

        Permission::saveUserPermissions(
            $userId,
            $permissions
        );
    }
}