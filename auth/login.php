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
    <style>
        body { display: flex; justify-content: center; align-items: center; min-height: 100vh; background-color: #f3f4f6; }
        .container { background-color: #fff; padding: 2rem; border-radius: 0.5rem; box-shadow: 0 0 10px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        .alert { margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="text-center mb-4">Login</h2>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" role="alert">
                <?php foreach ($errors as $error): ?>
                    <p class="mb-0"><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

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
</body>
</html>
