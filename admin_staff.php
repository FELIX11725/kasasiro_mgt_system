<?php
require_once 'auth/auth.php';
require_once 'auth/db_connect.php'; // PDO $pdo is available here
require_once 'include/helpers.php'; // Helper functions

require_login_and_role(['admin'], 'auth/login.php', 'index.php'); // Admin only

$page_title = "Manage Staff";
$errors = [];
$success_message = '';

$action = $_GET['action'] ?? 'list'; // list, create_form, edit_form
$staff_id_to_edit = isset($_GET['id']) ? (int)$_GET['id'] : null;

$all_roles = getAllRoles($pdo); // For dropdowns

// Filter out 'customer' role for staff creation/editing if desired
$staff_roles = array_filter($all_roles, function($role) {
    return $role['role_name'] !== 'customer';
});


// Handle POST requests for Create and Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->beginTransaction(); // Start transaction for multi-table operations

    try {
        // Common user fields
        $user_id = $_POST['user_id'] ?? null; // For edit
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? ''; // Empty if not changing
        $phone_number = trim($_POST['phone_number'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $role_id = $_POST['role_id'] ?? null;

        // Staff specific
        $job_title = trim($_POST['job_title'] ?? '');

        // Driver specific
        $license_number = trim($_POST['license_number'] ?? '');
        $is_driver_role = false;
        if ($role_id) {
            foreach($all_roles as $role) {
                if ($role['role_id'] == $role_id && $role['role_name'] === 'driver') {
                    $is_driver_role = true;
                    break;
                }
            }
        }

        // Validation
        if (empty($first_name)) $errors[] = "First name is required.";
        if (empty($last_name)) $errors[] = "Last name is required.";
        if (empty($username)) $errors[] = "Username is required.";
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
        if (empty($role_id)) $errors[] = "Role is required.";
        if (empty($job_title)) $errors[] = "Job title is required.";

        if (isset($_POST['create_staff'])) { // CREATE Action
            if (empty($password) || strlen($password) < 6) $errors[] = "Password must be at least 6 characters long for new staff.";
            // Check username uniqueness
            $stmt_user_exists = $pdo->prepare("SELECT user_id FROM Users WHERE username = :username");
            $stmt_user_exists->bindParam(':username', $username);
            $stmt_user_exists->execute();
            if ($stmt_user_exists->fetch()) $errors[] = "Username already exists.";
            // Check email uniqueness
            $stmt_email_exists = $pdo->prepare("SELECT user_id FROM Users WHERE email = :email");
            $stmt_email_exists->bindParam(':email', $email);
            $stmt_email_exists->execute();
            if ($stmt_email_exists->fetch()) $errors[] = "Email already registered.";

        } else { // EDIT Action
             if (!empty($password) && strlen($password) < 6) $errors[] = "New password must be at least 6 characters long.";
            // Check username uniqueness (if changed)
            $stmt_user_exists = $pdo->prepare("SELECT user_id FROM Users WHERE username = :username AND user_id != :user_id");
            $stmt_user_exists->bindParam(':username', $username);
            $stmt_user_exists->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt_user_exists->execute();
            if ($stmt_user_exists->fetch()) $errors[] = "Username already exists for another user.";
            // Check email uniqueness (if changed)
            $stmt_email_exists = $pdo->prepare("SELECT user_id FROM Users WHERE email = :email AND user_id != :user_id");
            $stmt_email_exists->bindParam(':email', $email);
            $stmt_email_exists->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt_email_exists->execute();
            if ($stmt_email_exists->fetch()) $errors[] = "Email already registered by another user.";
        }

        if ($is_driver_role && empty($license_number)) $errors[] = "License number is required for drivers.";


        if (empty($errors)) {
            if (isset($_POST['create_staff'])) {
                $new_user_id = addUser($pdo, $username, $password, $role_id, $first_name, $last_name, $email, $phone_number, $address);
                if ($new_user_id) {
                    addStaff($pdo, $new_user_id, $job_title);
                    if ($is_driver_role) {
                        addDriver($pdo, $new_user_id, $license_number);
                    }
                    $success_message = "Staff member '{$first_name} {$last_name}' created successfully!";
                    $action = 'list';
                } else {
                    $errors[] = "Failed to create user record."; // Should be caught by specific checks above
                }
            } elseif (isset($_POST['update_staff']) && $user_id && $staff_id_to_edit) {
                updateUserDetails($pdo, $user_id, $first_name, $last_name, $email, $phone_number, $address, $role_id);
                updateStaffDetails($pdo, $staff_id_to_edit, $job_title);

                // Handle password change
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt_pass = $pdo->prepare("UPDATE Users SET password = :password WHERE user_id = :user_id");
                    $stmt_pass->bindParam(':password', $hashed_password);
                    $stmt_pass->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                    $stmt_pass->execute();
                }

                // Handle Driver role update
                $staff_details_before_update = getStaffDetailsById($pdo, $staff_id_to_edit); // to check previous driver status

                if ($is_driver_role) {
                    updateDriverDetails($pdo, $user_id, $license_number); // This will insert or update
                } else {
                    // If they were a driver but role changed, remove driver record
                    if ($staff_details_before_update && $staff_details_before_update['license_number'] !== null) {
                         // Before removing, check if assigned to active schedules
                        if ($staff_details_before_update['driver_id'] && isStaffDriverAssignedToSchedules($pdo, $staff_details_before_update['driver_id'])) {
                           $errors[] = "Cannot change role from driver. Driver is assigned to active schedules. Please reassign schedules first.";
                        } else {
                            removeDriverRecord($pdo, $user_id);
                        }
                    }
                }
                if(empty($errors)){ // if no error during role change from driver
                    $success_message = "Staff member '{$first_name} {$last_name}' updated successfully!";
                    $action = 'list';
                } else { // Error occurred during role change, stay on edit form
                    $action = 'edit_form';
                    $pdo->rollBack(); // Rollback due to error during role change check
                }
            }
            if (empty($errors)) $pdo->commit(); else $pdo->rollBack();
        } else { // Validation errors occurred
            $pdo->rollBack();
            $action = ($user_id || isset($_POST['update_staff'])) ? 'edit_form' : 'create_form';
            if ($action === 'edit_form' && !$staff_id_to_edit && $user_id) { // Repopulate staff_id if lost
                $stmt_get_staff = $pdo->prepare("SELECT staff_id FROM Staff WHERE user_id = :user_id");
                $stmt_get_staff->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt_get_staff->execute();
                $s_id_res = $stmt_get_staff->fetch();
                if($s_id_res) $staff_id_to_edit = $s_id_res['staff_id'];
            }
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $errors[] = "Database error: " . $e->getMessage();
        $action = ($user_id || isset($_POST['update_staff'])) ? 'edit_form' : 'create_form';
         if ($action === 'edit_form' && !$staff_id_to_edit && $user_id) {
            $stmt_get_staff = $pdo->prepare("SELECT staff_id FROM Staff WHERE user_id = :user_id");
            $stmt_get_staff->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt_get_staff->execute();
            $s_id_res = $stmt_get_staff->fetch();
            if($s_id_res) $staff_id_to_edit = $s_id_res['staff_id'];
        }
    }
}


// Handle Delete action
if ($action === 'delete' && $staff_id_to_edit) {
    $staff_details_to_delete = getStaffDetailsById($pdo, $staff_id_to_edit);
    if ($staff_details_to_delete && $staff_details_to_delete['driver_id'] && isStaffDriverAssignedToSchedules($pdo, $staff_details_to_delete['driver_id'])) {
        $errors[] = "Cannot delete staff member. They are a driver assigned to active collection schedules. Please reassign schedules first.";
    } else {
        if (deleteStaffUser($pdo, $staff_id_to_edit)) { // This function now uses transaction and checks driver schedule itself
            $success_message = "Staff member deleted successfully!";
        } else {
            // Check if the error was due to active schedules again (if deleteStaffUser returns false due to it)
             if ($staff_details_to_delete && $staff_details_to_delete['driver_id'] && isStaffDriverAssignedToSchedules($pdo, $staff_details_to_delete['driver_id'])) {
                 $errors[] = "Cannot delete staff member. They are a driver assigned to active collection schedules. Please reassign schedules first.";
             } else {
                 $errors[] = "Failed to delete staff member. They might have other critical dependencies or a database error occurred.";
             }
        }
    }
    $action = 'list'; // Always return to list view
}


// Fetch data for display
$all_staff = [];
if ($action === 'list') {
    $all_staff = getAllStaffDetails($pdo);
}

$current_staff_member = null;
if ($action === 'edit_form' && $staff_id_to_edit) {
    $current_staff_member = getStaffDetailsById($pdo, $staff_id_to_edit);
    if (!$current_staff_member) {
        $errors[] = "Staff member not found for editing.";
        $action = 'list';
        $all_staff = getAllStaffDetails($pdo);
    }
}


include 'include/header.php';
include 'include/sidebar.php';
?>

<div class="page-wrapper">
    <div class="page-header d-print-none">
        <div class="container-xl"><div class="row g-2 align-items-center"><div class="col"><h2 class="page-title"><?php echo htmlspecialchars($page_title); ?></h2></div></div></div>
    </div>
    <div class="page-body">
        <div class="container-xl">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php foreach ($errors as $error): ?><p class="mb-0"><?php echo htmlspecialchars($error); ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <?php if ($action === 'list'): ?>
                <div class="col-auto ms-auto d-print-none mb-3"><a href="?action=create_form" class="btn btn-primary">Add New Staff</a></div>
                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table table-striped">
                            <thead><tr><th>Staff ID</th><th>Name</th><th>Username</th><th>Email</th><th>Phone</th><th>Role</th><th>Job Title</th><th>License #</th><th class="w-1">Actions</th></tr></thead>
                            <tbody>
                                <?php if (empty($all_staff)): ?>
                                    <tr><td colspan="9" class="text-center">No staff members found.</td></tr>
                                <?php else: foreach ($all_staff as $staff): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($staff['staff_id']); ?></td>
                                        <td><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($staff['username']); ?></td>
                                        <td><?php echo htmlspecialchars($staff['email']); ?></td>
                                        <td><?php echo htmlspecialchars($staff['phone_number'] ?? 'N/A'); ?></td>
                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($staff['role_name']); ?></span></td>
                                        <td><?php echo htmlspecialchars($staff['job_title']); ?></td>
                                        <td><?php echo htmlspecialchars($staff['license_number'] ?? 'N/A'); ?></td>
                                        <td>
                                            <a href="?action=edit_form&id=<?php echo $staff['staff_id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                            <a href="?action=delete&id=<?php echo $staff['staff_id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this staff member? This may also delete their user account.');">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php elseif ($action === 'create_form' || $action === 'edit_form'):
                $form_values = $action === 'edit_form' ? $current_staff_member : [
                    'first_name' => '', 'last_name' => '', 'username' => '', 'email' => '',
                    'phone_number' => '', 'address' => '', 'role_id' => '', 'job_title' => '', 'license_number' => ''
                ];
                 // Repopulate from POST on validation failure
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($errors)) {
                    foreach ($form_values as $key => $value) {
                        $form_values[$key] = $_POST[$key] ?? $value;
                    }
                }
            ?>
                <div class="card">
                    <div class="card-header"><h3 class="card-title"><?php echo ($action === 'create_form' ? 'Add New' : 'Edit'); ?> Staff Member</h3></div>
                    <div class="card-body">
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?action=<?php echo $action; ?><?php echo ($action === 'edit_form' && $staff_id_to_edit ? '&id='.$staff_id_to_edit : ''); ?>" method="POST">
                            <?php if ($action === 'edit_form' && $staff_id_to_edit): ?>
                                <input type="hidden" name="staff_id" value="<?php echo htmlspecialchars($staff_id_to_edit); ?>">
                                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($form_values['user_id']); ?>">
                            <?php endif; ?>

                            <h4 class="mb-3">User Account Details</h4>
                            <div class="row">
                                <div class="col-md-6 mb-3"><label class="form-label" for="first_name">First Name:</label><input type="text" name="first_name" id="first_name" class="form-control" value="<?php echo htmlspecialchars($form_values['first_name']); ?>" required></div>
                                <div class="col-md-6 mb-3"><label class="form-label" for="last_name">Last Name:</label><input type="text" name="last_name" id="last_name" class="form-control" value="<?php echo htmlspecialchars($form_values['last_name']); ?>" required></div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3"><label class="form-label" for="username">Username:</label><input type="text" name="username" id="username" class="form-control" value="<?php echo htmlspecialchars($form_values['username']); ?>" required></div>
                                <div class="col-md-6 mb-3"><label class="form-label" for="email">Email:</label><input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($form_values['email']); ?>" required></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="password">Password: <?php if($action==='edit_form') echo "(Leave blank to keep current password)"; ?></label>
                                <input type="password" name="password" id="password" class="form-control" <?php echo ($action === 'create_form' ? 'required' : ''); ?>>
                            </div>
                             <div class="row">
                                <div class="col-md-6 mb-3"><label class="form-label" for="phone_number">Phone Number (Optional):</label><input type="tel" name="phone_number" id="phone_number" class="form-control" value="<?php echo htmlspecialchars($form_values['phone_number'] ?? ''); ?>"></div>
                                <div class="col-md-6 mb-3"><label class="form-label" for="role_id">Role:</label>
                                    <select name="role_id" id="role_id" class="form-select" required onchange="toggleLicenseField(this.value)">
                                        <option value="">Select Role</option>
                                        <?php foreach ($staff_roles as $role): ?>
                                            <option value="<?php echo $role['role_id']; ?>" <?php echo (($form_values['role_id'] ?? '') == $role['role_id'] ? 'selected' : ''); ?> data-role-name="<?php echo $role['role_name']; ?>">
                                                <?php echo htmlspecialchars(ucfirst($role['role_name'])); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3"><label class="form-label" for="address">Address (Optional):</label><textarea name="address" id="address" class="form-control" rows="2"><?php echo htmlspecialchars($form_values['address'] ?? ''); ?></textarea></div>

                            <h4 class="mt-4 mb-3">Staff Specific Details</h4>
                            <div class="mb-3"><label class="form-label" for="job_title">Job Title:</label><input type="text" name="job_title" id="job_title" class="form-control" value="<?php echo htmlspecialchars($form_values['job_title']); ?>" required></div>

                            <div id="driver_specific_fields" style="display:none;"> <!-- Initially hidden, shown by JS -->
                                <h4 class="mt-4 mb-3">Driver Specific Details</h4>
                                <div class="mb-3"><label class="form-label" for="license_number">License Number:</label><input type="text" name="license_number" id="license_number" class="form-control" value="<?php echo htmlspecialchars($form_values['license_number'] ?? ''); ?>"></div>
                            </div>

                            <?php if ($action === 'create_form'): ?>
                                <button type="submit" name="create_staff" class="btn btn-primary mt-3">Create Staff Member</button>
                            <?php else: ?>
                                <button type="submit" name="update_staff" class="btn btn-primary mt-3">Update Staff Member</button>
                            <?php endif; ?>
                            <a href="?action=list" class="btn btn-secondary mt-3">Cancel</a>
                        </form>
                    </div>
                </div>
                <script>
                    function toggleLicenseField(selectedRoleId) {
                        const driverSpecificFields = document.getElementById('driver_specific_fields');
                        const licenseInput = document.getElementById('license_number');
                        const roleSelect = document.getElementById('role_id');
                        const selectedOption = roleSelect.options[roleSelect.selectedIndex];
                        const roleName = selectedOption ? selectedOption.dataset.roleName : '';

                        if (roleName === 'driver') {
                            driverSpecificFields.style.display = 'block';
                            licenseInput.required = true;
                        } else {
                            driverSpecificFields.style.display = 'none';
                            licenseInput.required = false;
                        }
                    }
                    // Call on page load for edit form
                    document.addEventListener('DOMContentLoaded', function() {
                        const roleSelect = document.getElementById('role_id');
                        if (roleSelect.value) { // If a role is already selected (e.g. in edit mode)
                           toggleLicenseField(roleSelect.value);
                        }
                    });
                </script>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include 'include/footer.php'; ?>
