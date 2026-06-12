<?php
// app/controllers/AuthMiddleware.php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once __DIR__ . '/../models/Permission.php';

function checkPermission($required_permission) {
    if (!isset($_SESSION['role_id'])) {
        header('Location: ' . BASE_URL . '/403.php');
        exit;
    }

    $role_id = $_SESSION['role_id'];
    $permission = new Permission();
    if (!$permission->hasPermission($role_id, $required_permission)) {
        header('Location: ' . BASE_URL . '/403.php');
        exit;
    }
}
// app/controllers/AuthMiddleware.php

class AuthMiddleware {
    public function checkLogin() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . '/account/login.php');
            exit;
        }
    }

    public function checkAdmin() {
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            header('Location: ' . BASE_URL . '/403.php');
            exit;
        }
    }

    public function checkCandidate() {
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'candidate') {
            header('Location: ' . BASE_URL . '/403.php');
            exit;
        }
    }

    public function checkEmployer() {
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'employer') {
            header('Location: ' . BASE_URL . '/403.php');
            exit;
        }
    }
}

