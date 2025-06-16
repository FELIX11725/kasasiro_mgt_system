<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Checks if a user is currently logged in.
 *
 * @return bool True if logged in, false otherwise.
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Redirects to the login page if the user is not logged in.
 *
 * @param string $redirect_path The path to redirect to after login (optional).
 */
function require_login(string $redirect_path = 'auth/login.php'): void {
    if (!is_logged_in()) {
        // Store the current page URL to redirect back after login if needed
        // $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header("Location: " . $redirect_path);
        exit;
    }
}

/**
 * Checks if the logged-in user has a specific role.
 *
 * @param string|array $role_name The name of the role (or an array of role names) to check for.
 * @return bool True if the user has the specified role (or one of the roles), false otherwise.
 */
function has_role($role_name): bool {
    if (!is_logged_in() || !isset($_SESSION['role_name'])) {
        return false;
    }
    if (is_array($role_name)) {
        return in_array($_SESSION['role_name'], $role_name, true);
    }
    return $_SESSION['role_name'] === $role_name;
}

/**
 * Redirects if the user does not have the required role(s).
 *
 * @param string|array $role_name The required role or an array of allowed roles.
 * @param string $redirect_path Path to redirect to if role check fails (e.g., an unauthorized page or login).
 */
function require_role($role_name, string $redirect_path = 'auth/login.php'): void {
    if (!has_role($role_name)) {
        $_SESSION['error_message'] = "You are not authorized to view this page.";
        header("Location: " . $redirect_path); // Or a specific "unauthorized.php" page
        exit;
    }
}

/**
 * A comprehensive check: requires login and specific role(s).
 *
 * @param string|array $role_name The required role or an array of allowed roles.
 * @param string $login_redirect_path Path to redirect for login.
 * @param string $role_redirect_path Path to redirect if role check fails (default is 'auth/login.php' or an unauthorized page).
 */
function require_login_and_role($role_name, string $login_redirect_path = 'auth/login.php', string $role_redirect_path = ''): void {
    require_login($login_redirect_path);

    // If role_redirect_path is not specified, use a sensible default or an unauthorized page
    if (empty($role_redirect_path)) {
        // Attempt to redirect to a generic dashboard or index if role check fails,
        // or fallback to login page if that's more appropriate.
        // For simplicity, we can redirect to index.php or a dedicated 'unauthorized.php' page.
        // Let's use index.php for now, assuming it can handle users if they land there without specific role for a page.
        // A better approach would be a dedicated unauthorized.php page.
        $role_redirect_path = '../index.php'; // Redirect to main index, assumes auth.php is in auth/
                                             // If you have an unauthorized.php, use that: 'auth/unauthorized.php'
    }

    if (!has_role($role_name)) {
        $_SESSION['error_message'] = "Access Denied: You do not have the required permissions for this page.";
        // If role_redirect_path was ../index.php, it will be used.
        // If you created auth/unauthorized.php, it would be better.
        header("Location: " . $role_redirect_path);
        exit;
    }
}

?>
