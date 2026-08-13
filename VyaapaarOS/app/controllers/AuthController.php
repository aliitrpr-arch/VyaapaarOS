<?php

require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../services/AuthService.php';

class AuthController
{
    public static function login(): ?string
    {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        $result = AuthService::login(
            $username,
            $password
        );

        if (!$result['success']) {
            return $result['message'];
        }

        $user = $result['user'];

        Session::set('user_id', $user['id']);
        Session::set('company_id', $user['company_id']);
        Session::set('branch_id', $user['branch_id']);
        Session::set('role_id', $user['role_id']);
        Session::set('role_name', $user['role_name']);
        Session::set('username', $user['username']);
        Session::set('full_name', $user['full_name']);

        session_regenerate_id(true);

        header('Location: dashboard.php');
        exit;
    }
}