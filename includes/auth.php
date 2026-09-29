<?php
/**
 * Authentication helpers for the customer-facing site and the admin panel.
 * Both share one `users` table, distinguished by the `role` column.
 */

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $cached = null;
    if ($cached === null) {
        $stmt = db()->prepare('SELECT id, full_name, email, phone, address, role FROM users WHERE id = :id');
        $stmt->execute(['id' => $_SESSION['user_id']]);
        $cached = $stmt->fetch() ?: null;
    }
    return $cached;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $user = current_user();
    return $user !== null && $user['role'] === 'admin';
}

/** Redirect to the login page if the visitor is not logged in. */
function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('error', 'Please log in to continue.');
        redirect('auth/login.php');
    }
}

/** Redirect away if the visitor is not an admin. */
function require_admin(): void
{
    if (!is_admin()) {
        set_flash('error', 'Admin access required.');
        redirect('admin/login.php');
    }
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
}

function logout_user(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}
