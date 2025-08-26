<?php
session_start();
require_once 'db_connect.php';

$errors = [];

// If user is already logged in, redirect based on role
if (isset($_SESSION['user_id'])) {
    // Example redirection logic, adjust paths as needed
    if ($_SESSION['role_name'] === 'admin') {
        header("Location: ../admin_dashboard.php"); // Example path
        exit;
    } elseif ($_SESSION['role_name'] === 'customer') {
        header("Location: ../customer_dashboard.php"); // Example path
        exit;
    } elseif ($_SESSION['role_name'] === 'driver') {
        header("Location: ../driver_dashboard.php"); // Example path
        exit;
    } else {
        header("Location: ../index.php"); // Default redirect
        exit;
    }
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username)) $errors[] = "Username is required.";
    if (empty($password)) $errors[] = "Password is required.";

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT u.user_id, u.username, u.password, r.role_name
                               FROM Users u
                               JOIN Roles r ON u.role_id = r.role_id
                               WHERE u.username = :username");
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Password is correct, start session
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role_name'] = $user['role_name'];

            // Redirect based on role
            if ($user['role_name'] === 'admin') {
                header("Location: ../admin_dashboard.php"); // Adjust path as needed
                exit;
            } elseif ($user['role_name'] === 'customer') {
                header("Location: ../customer_dashboard.php"); // Adjust path as needed
                exit;
            } elseif ($user['role_name'] === 'driver') {
                header("Location: ../driver_dashboard.php"); // Adjust path as needed
                exit;
            } else {
                // Default redirect for other roles or if no specific dashboard
                header("Location: ../index.php");
                exit;
            }
        } else {
            $errors[] = "Invalid username or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login</title>
    <link rel="stylesheet" href="../dist/css/tabler.min.css">
    <script src="../dist/js/tabler.min.js"></script>
    <style>
        body { display: flex; justify-content: center; align-items: center; min-height: 100vh; background-color: #f3f4f6; }
        .container { background-color: #fff; padding: 2rem; border-radius: 0.5rem; box-shadow: 0 0 10px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
    </style>
</head>
<body>
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1050;">
        <!-- Toasts will be appended here -->
    </div>

    <div class="container">
        <h2 class="text-center mb-4">Login</h2>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="mb-3">
                <label for="username" class="form-label">Username:</label>
                <input type="text" name="username" id="username" class="form-control" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password:</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
        <p class="mt-3 text-center">
            Don't have an account? <a href="register.php">Register here</a>
        </p>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const toastContainer = document.querySelector('.toast-container');

        function showToast(message, type = 'danger') {
            const toastId = 'toast-' + Date.now();
            const toastHTML = `
                <div id="${toastId}" class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
                    <div class="toast-header">
                        <strong class="me-auto">${type === 'success' ? 'Success' : 'Error'}</strong>
                        <button type="button" class="ms-2 btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                    <div class="toast-body">
                        ${message}
                    </div>
                </div>`;

            toastContainer.insertAdjacentHTML('beforeend', toastHTML);

            const toastElement = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastElement);

            toast.show();
        }

        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $error): ?>
                showToast("<?php echo htmlspecialchars($error); ?>", 'danger');
            <?php endforeach; ?>
        <?php endif; ?>
    });
    </script>
</body>
</html>
