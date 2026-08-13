<?php

require_once __DIR__ . '/../models/User.php';

class AuthService
{
    public static function login(
        string $username,
        string $password
    ): array {

        $username = trim($username);

        if ($username === '' || $password === '') {
            return [
                'success' => false,
                'message' => 'Username and password are required.'
            ];
        }

        $user = User::findByUsername($username);

        if (!$user) {
            return [
                'success' => false,
                'message' => 'Invalid username or password.'
            ];
        }

        if (!$user['is_active']) {
            return [
                'success' => false,
                'message' => 'This user account is inactive.'
            ];
        }

        if (!password_verify($password, $user['password_hash'])) {
            return [
                'success' => false,
                'message' => 'Invalid username or password.'
            ];
        }

        return [
            'success' => true,
            'user' => $user
        ];
    }
}