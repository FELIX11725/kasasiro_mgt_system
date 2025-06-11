<?php
session_start();
require_once 'db_connect.php';

$errors = [];
$success_message = '';

// Define the default role for new users
define('DEFAULT_ROLE', 'customer');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone_number = trim($_POST['phone_number']);
    $address = trim($_POST['address']);

    // Basic validation
    if (empty($first_name)) $errors[] = "First name is required.";
    if (empty($last_name)) $errors[] = "Last name is required.";
    if (empty($username)) $errors[] = "Username is required.";
    if (empty($email)) $errors[] = "Email is required.";
    else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format.";
    if (empty($password)) $errors[] = "Password is required.";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match.";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters long.";


    // Check if username or email already exists
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT user_id FROM Users WHERE username = :username OR email = :email");
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        if ($stmt->fetch()) {
            $errors[] = "Username or email already exists.";
        }
    }

    // If no errors, proceed with registration
    if (empty($errors)) {
        // Get the role_id for the default role
        $role_id = getRoleId($pdo, DEFAULT_ROLE);
        if (!$role_id) {
            // Fallback or error handling if default role is not found
            // This might happen if the Roles table is empty or DEFAULT_ROLE is misspelled
            $errors[] = "Default user role not found. Please contact administrator.";
            // For setup: if Roles table is empty, you might want to insert default roles here
            // Example: INSERT INTO Roles (role_name) VALUES ('customer'), ('admin'), ('driver');
            // And then try to get the role_id again.
        } else {
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert user into database
            $sql = "INSERT INTO Users (first_name, last_name, username, email, password, phone_number, address, role_id)
                    VALUES (:first_name, :last_name, :username, :email, :password, :phone_number, :address, :role_id)";
            $stmt = $pdo->prepare($sql);

            $stmt->bindParam(':first_name', $first_name, PDO::PARAM_STR);
            $stmt->bindParam(':last_name', $last_name, PDO::PARAM_STR);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);
            $stmt->bindParam(':phone_number', $phone_number, PDO::PARAM_STR);
            $stmt->bindParam(':address', $address, PDO::PARAM_STR);
            $stmt->bindParam(':role_id', $role_id, PDO::PARAM_INT);

            try {
                if ($stmt->execute()) {
                    // Optionally, create a customer entry if the role is 'customer'
                    if (DEFAULT_ROLE === 'customer') {
                        $user_id = $pdo->lastInsertId();
                        // Create a unique customer identifier, e.g., CUST-00001
                        // This is a simplified example. A robust system might need a sequence or a more complex generator.
                        $customer_identifier = "CUST-" . str_pad($user_id, 5, '0', STR_PAD_LEFT);

                        $cust_stmt = $pdo->prepare("INSERT INTO Customers (user_id, customer_identifier, address_latitude, address_longitude) VALUES (:user_id, :customer_identifier, NULL, NULL)");
                        $cust_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                        $cust_stmt->bindParam(':customer_identifier', $customer_identifier, PDO::PARAM_STR);
                        $cust_stmt->execute();
                    }
                    $success_message = "Registration successful! You can now <a href='login.php'>login</a>.";
                } else {
                    $errors[] = "Registration failed. Please try again.";
                }
            } catch (PDOException $e) {
                // Check for unique constraint violation for phone_number if it's set to unique in DB
                if ($e->errorInfo[1] == 1062) { // Error code for duplicate entry
                     if (strpos($e->getMessage(), 'phone_number') !== false) {
                        $errors[] = "This phone number is already registered.";
                    } else if (strpos($e->getMessage(), 'email') !== false) {
                        $errors[] = "This email address is already registered.";
                    } else if (strpos($e->getMessage(), 'username') !== false) {
                        $errors[] = "This username is already taken.";
                    } else {
                        $errors[] = "Registration failed due to a database error: " . $e->getMessage();
                    }
                } else {
                    $errors[] = "Registration failed. Please try again. Error: " . $e->getMessage();
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Registration</title>
    <!-- You might want to link to your main CSS file here -->
    <link rel="stylesheet" href="../dist/css/tabler.min.css">
    <style>
        body { display: flex; justify-content: center; align-items: center; min-height: 100vh; background-color: #f3f4f6; }
        .container { background-color: #fff; padding: 2rem; border-radius: 0.5rem; box-shadow: 0 0 10px rgba(0,0,0,0.1); width: 100%; max-width: 500px; }
        .alert { margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="text-center mb-4">Register</h2>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" role="alert">
                <?php foreach ($errors as $error): ?>
                    <p class="mb-0"><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success" role="alert">
                <?php echo $success_message; // Contains a link, so don't escape ?>
            </div>
        <?php else: // Hide form on success ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="mb-3">
                    <label for="first_name" class="form-label">First Name:</label>
                    <input type="text" name="first_name" id="first_name" class="form-control" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="last_name" class="form-label">Last Name:</label>
                    <input type="text" name="last_name" id="last_name" class="form-control" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="username" class="form-label">Username:</label>
                    <input type="text" name="username" id="username" class="form-control" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email:</label>
                    <input type="email" name="email" id="email" class="form-control" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password:</label>
                    <input type="password" name="password" id="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password:</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="phone_number" class="form-label">Phone Number:</label>
                    <input type="tel" name="phone_number" id="phone_number" class="form-control" value="<?php echo isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : ''; ?>">
                </div>
                <div class="mb-3">
                    <label for="address" class="form-label">Address:</label>
                    <textarea name="address" id="address" class="form-control"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary w-100">Register</button>
            </form>
        <?php endif; ?>
        <p class="mt-3 text-center">
            Already have an account? <a href="login.php">Login here</a>
        </p>
    </div>
</body>
</html>
