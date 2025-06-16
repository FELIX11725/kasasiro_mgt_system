<?php
require_once 'auth/auth.php';
require_once 'auth/db_connect.php'; // PDO $pdo is available here
require_once 'include/helpers.php'; // Helper functions

require_login_and_role(['admin', 'staff'], 'auth/login.php', 'index.php');

$page_title = "Manage Vehicles";
$errors = [];
$success_message = '';

// Define possible vehicle statuses
$vehicle_statuses = ['available', 'in_maintenance', 'active', 'unavailable', 'decommissioned'];


$action = $_GET['action'] ?? 'list'; // Default action: list, create_form, edit_form
$vehicle_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Handle POST requests for Create and Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $registration_number = trim($_POST['registration_number'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $capacity = !empty($_POST['capacity']) ? (float)$_POST['capacity'] : null;
    $status = $_POST['status'] ?? 'available';
    $current_id = $_POST['vehicle_id'] ?? null; // For edit action

    // Validation
    if (empty($registration_number)) {
        $errors[] = "Registration number is required.";
    }
    if (!in_array($status, $vehicle_statuses)) {
        $errors[] = "Invalid status selected.";
    }
    if ($capacity !== null && (!is_numeric($capacity) || $capacity < 0)) {
        $errors[] = "Capacity must be a positive number if provided.";
    }

    // Check for registration number uniqueness
    if (empty($errors)) {
        $existing_vehicle_by_reg = getVehicleByRegistration($pdo, $registration_number);
        if (isset($_POST['create_vehicle'])) {
            if ($existing_vehicle_by_reg) {
                $errors[] = "A vehicle with this registration number already exists.";
            }
        } elseif (isset($_POST['update_vehicle']) && $current_id) {
            if ($existing_vehicle_by_reg && $existing_vehicle_by_reg['vehicle_id'] != $current_id) {
                $errors[] = "Another vehicle with this registration number already exists.";
            }
        }
    }


    if (empty($errors)) {
        if (isset($_POST['create_vehicle'])) {
            try {
                if (addVehicle($pdo, $registration_number, $model, $capacity, $status)) {
                    $success_message = "Vehicle '{$registration_number}' created successfully!";
                    $action = 'list'; // Refresh list
                } else {
                    $errors[] = "Failed to create vehicle."; // General error
                }
            } catch (PDOException $e) {
                 // The unique constraint on registration_number is handled by prior check,
                 // but another PDO exception could occur.
                $errors[] = "Database error during vehicle creation: " . $e->getMessage();
            }
        } elseif (isset($_POST['update_vehicle']) && $current_id) {
            try {
                if (updateVehicle($pdo, (int)$current_id, $registration_number, $model, $capacity, $status)) {
                    $success_message = "Vehicle '{$registration_number}' updated successfully!";
                    $action = 'list'; // Refresh list
                } else {
                    $errors[] = "Failed to update vehicle."; // General error
                }
            } catch (PDOException $e) {
                // The unique constraint on registration_number is handled by prior check.
                $errors[] = "Database error during vehicle update: " . $e->getMessage();
            }
        }
    } else {
        // If validation errors, stay on the current form action
        $action = ($current_id || isset($_POST['update_vehicle'])) ? 'edit_form' : 'create_form';
        if ($action === 'edit_form' && $current_id) {
             $vehicle_id = (int)$current_id;
        }
    }
}

// Handle Delete action
if ($action === 'delete' && $vehicle_id) {
    if (isVehicleInUse($pdo, $vehicle_id)) {
        $errors[] = "Cannot delete this vehicle. It is currently assigned to active or pending collection schedules. Please reassign those schedules first or mark the vehicle as 'Unavailable' or 'Decommissioned'.";
    } else {
        try {
            if (deleteVehicle($pdo, $vehicle_id)) {
                $success_message = "Vehicle deleted successfully!";
            } else {
                $errors[] = "Failed to delete vehicle.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error during deletion: " . $e->getMessage();
        }
    }
    $action = 'list'; // Always return to list view after attempting delete
}

// Fetch data for display
$all_vehicles = [];
// Use getAllVehiclesDetails for the main list
if ($action === 'list' || ($action === 'delete' && !empty($errors)) || ($action === 'delete' && empty($errors)) ) {
    $all_vehicles = getAllVehiclesDetails($pdo);
}


$current_vehicle = null;
if ($action === 'edit_form' && $vehicle_id) {
    $current_vehicle = getVehicleById($pdo, $vehicle_id);
    if (!$current_vehicle) {
        $errors[] = "Vehicle not found for editing.";
        $action = 'list';
        $all_vehicles = getAllVehiclesDetails($pdo); // Re-fetch for list
    }
}

include 'include/header.php';
include 'include/sidebar.php';
?>

<div class="page-wrapper">
    <div class="page-header d-print-none">
        <div class="container-xl">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <h2 class="page-title"><?php echo htmlspecialchars($page_title); ?></h2>
                </div>
            </div>
        </div>
    </div>

    <div class="page-body">
        <div class="container-xl">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php foreach ($errors as $error): ?><p class="mb-0"><?php echo htmlspecialchars($error); ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($action === 'list'): ?>
                <div class="col-auto ms-auto d-print-none mb-3">
                    <a href="?action=create_form" class="btn btn-primary">
                        Add New Vehicle
                    </a>
                </div>
                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Registration #</th>
                                    <th>Model</th>
                                    <th>Capacity (tons/m³)</th>
                                    <th>Status</th>
                                    <th class="w-1">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($all_vehicles)): ?>
                                    <tr><td colspan="6" class="text-center">No vehicles found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($all_vehicles as $vehicle): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($vehicle['vehicle_id']); ?></td>
                                        <td><?php echo htmlspecialchars($vehicle['registration_number']); ?></td>
                                        <td><?php echo htmlspecialchars($vehicle['model'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($vehicle['capacity'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="badge bg-<?php
                                                echo ($vehicle['status'] === 'available' ? 'success' :
                                                     ($vehicle['status'] === 'in_maintenance' ? 'warning' :
                                                     ($vehicle['status'] === 'active' ? 'info' : 'secondary')));
                                            ?>">
                                                <?php echo htmlspecialchars(ucfirst($vehicle['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="?action=edit_form&id=<?php echo $vehicle['vehicle_id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                            <a href="?action=delete&id=<?php echo $vehicle['vehicle_id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this vehicle? This action cannot be undone.');">Delete</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($action === 'create_form' || $action === 'edit_form'):
                $form_values = $action === 'edit_form' ? $current_vehicle : [
                    'registration_number' => '', 'model' => '', 'capacity' => '', 'status' => 'available'
                ];
                // If POST failed validation, repopulate from POST data
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($errors)) {
                    $form_values['registration_number'] = $_POST['registration_number'] ?? $form_values['registration_number'];
                    $form_values['model'] = $_POST['model'] ?? $form_values['model'];
                    $form_values['capacity'] = $_POST['capacity'] ?? $form_values['capacity'];
                    $form_values['status'] = $_POST['status'] ?? $form_values['status'];
                }
            ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><?php echo ($action === 'create_form' ? 'Add New' : 'Edit'); ?> Vehicle</h3>
                    </div>
                    <div class="card-body">
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?action=<?php echo $action; ?><?php echo ($action === 'edit_form' && $vehicle_id ? '&id='.$vehicle_id : ''); ?>" method="POST">
                            <?php if ($action === 'edit_form' && $vehicle_id): ?>
                                <input type="hidden" name="vehicle_id" value="<?php echo htmlspecialchars($vehicle_id); ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label" for="registration_number">Registration Number:</label>
                                <input type="text" name="registration_number" id="registration_number" class="form-control" value="<?php echo htmlspecialchars($form_values['registration_number']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="model">Model (Optional):</label>
                                <input type="text" name="model" id="model" class="form-control" value="<?php echo htmlspecialchars($form_values['model'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="capacity">Capacity (e.g., tons or cubic meters - Optional):</label>
                                <input type="number" step="0.01" name="capacity" id="capacity" class="form-control" value="<?php echo htmlspecialchars($form_values['capacity'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="status">Status:</label>
                                <select name="status" id="status" class="form-select" required>
                                    <?php foreach ($vehicle_statuses as $status_option): ?>
                                        <option value="<?php echo $status_option; ?>" <?php echo (($form_values['status'] ?? 'available') === $status_option ? 'selected' : ''); ?>>
                                            <?php echo htmlspecialchars(ucfirst($status_option)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <?php if ($action === 'create_form'): ?>
                                <button type="submit" name="create_vehicle" class="btn btn-primary">Create Vehicle</button>
                            <?php else: ?>
                                <button type="submit" name="update_vehicle" class="btn btn-primary">Update Vehicle</button>
                            <?php endif; ?>
                            <a href="?action=list" class="btn btn-secondary">Cancel</a>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'include/footer.php'; ?>
