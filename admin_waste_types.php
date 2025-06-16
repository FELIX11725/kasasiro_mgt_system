<?php
require_once 'auth/auth.php';
require_once 'auth/db_connect.php'; // PDO $pdo is available here
require_once 'include/helpers.php'; // Helper functions

require_login_and_role(['admin', 'staff'], 'auth/login.php', 'index.php');

$page_title = "Manage Waste Types";
$errors = [];
$success_message = '';

$action = $_GET['action'] ?? 'list'; // Default action: list, create_form, edit_form
$waste_type_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Handle POST requests for Create and Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $disposal_guidelines = trim($_POST['disposal_guidelines'] ?? '');
    $current_id = $_POST['waste_type_id'] ?? null; // For edit action

    if (empty($name)) {
        $errors[] = "Waste type name is required.";
    }

    if (empty($errors)) {
        if (isset($_POST['create_waste_type'])) {
            try {
                if (addWasteType($pdo, $name, $description, $disposal_guidelines)) {
                    $success_message = "Waste type '{$name}' created successfully!";
                    $action = 'list'; // Refresh list
                } else {
                    $errors[] = "Failed to create waste type. It might already exist or there was a database error.";
                }
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) { // Duplicate entry
                    $errors[] = "Error: A waste type with this name already exists.";
                } else {
                    $errors[] = "Database error: " . $e->getMessage();
                }
            }
        } elseif (isset($_POST['update_waste_type']) && $current_id) {
            try {
                if (updateWasteType($pdo, (int)$current_id, $name, $description, $disposal_guidelines)) {
                    $success_message = "Waste type '{$name}' updated successfully!";
                    $action = 'list'; // Refresh list
                } else {
                    $errors[] = "Failed to update waste type. It might already exist with that name or there was a database error.";
                }
            } catch (PDOException $e) {
                 if ($e->errorInfo[1] == 1062) { // Duplicate entry
                    $errors[] = "Error: Another waste type with this name already exists.";
                } else {
                    $errors[] = "Database error: " . $e->getMessage();
                }
            }
        }
    } else {
        // If validation errors, stay on the current form action
        $action = ($current_id || isset($_POST['update_waste_type'])) ? 'edit_form' : 'create_form';
        // If it was an edit form, we need to re-assign waste_type_id for pre-filling
        if ($action === 'edit_form' && $current_id) {
             $waste_type_id = (int)$current_id; // ensure it's set for the form load
        }
    }
}

// Handle Delete action
if ($action === 'delete' && $waste_type_id) {
    if (isWasteTypeInUse($pdo, $waste_type_id)) {
        $errors[] = "Cannot delete this waste type. It is currently in use in collected waste records. Please remove those associations first or consider marking it as 'inactive' if such a feature existed.";
    } else {
        try {
            if (deleteWasteType($pdo, $waste_type_id)) {
                $success_message = "Waste type deleted successfully!";
            } else {
                $errors[] = "Failed to delete waste type.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error during deletion: " . $e->getMessage();
        }
    }
    $action = 'list'; // Always return to list view after attempting delete
}

// Fetch data for display
$all_waste_types = [];
if ($action === 'list' || $action === 'delete') { // Fetch all for list view or after a delete
    $all_waste_types = getAllWasteTypes($pdo);
}

$current_waste_type = null;
if ($action === 'edit_form' && $waste_type_id) {
    $current_waste_type = getWasteTypeById($pdo, $waste_type_id);
    if (!$current_waste_type) {
        $errors[] = "Waste type not found for editing.";
        $action = 'list'; // Revert to list
        $all_waste_types = getAllWasteTypes($pdo); // Re-fetch for list
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
                        Add New Waste Type
                    </a>
                </div>
                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Disposal Guidelines</th>
                                    <th class="w-1">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($all_waste_types)): ?>
                                    <tr><td colspan="5" class="text-center">No waste types found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($all_waste_types as $type): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($type['waste_type_id']); ?></td>
                                        <td><?php echo htmlspecialchars($type['name']); ?></td>
                                        <td><?php echo nl2br(htmlspecialchars(substr($type['description'] ?? '', 0, 100) . (strlen($type['description'] ?? '') > 100 ? '...' : ''))); ?></td>
                                        <td><?php echo nl2br(htmlspecialchars(substr($type['disposal_guidelines'] ?? '', 0, 100) . (strlen($type['disposal_guidelines'] ?? '') > 100 ? '...' : ''))); ?></td>
                                        <td>
                                            <a href="?action=edit_form&id=<?php echo $type['waste_type_id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                            <a href="?action=delete&id=<?php echo $type['waste_type_id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this waste type?');">Delete</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($action === 'create_form' || $action === 'edit_form'):
                $form_values = $action === 'edit_form' ? $current_waste_type : ['name' => '', 'description' => '', 'disposal_guidelines' => ''];
                // If POST failed validation, try to repopulate from POST data
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($errors)) {
                    $form_values['name'] = $_POST['name'] ?? $form_values['name'];
                    $form_values['description'] = $_POST['description'] ?? $form_values['description'];
                    $form_values['disposal_guidelines'] = $_POST['disposal_guidelines'] ?? $form_values['disposal_guidelines'];
                }
            ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><?php echo ($action === 'create_form' ? 'Add New' : 'Edit'); ?> Waste Type</h3>
                    </div>
                    <div class="card-body">
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?action=<?php echo $action; ?><?php echo ($action === 'edit_form' && $waste_type_id ? '&id='.$waste_type_id : ''); ?>" method="POST">
                            <?php if ($action === 'edit_form' && $waste_type_id): ?>
                                <input type="hidden" name="waste_type_id" value="<?php echo htmlspecialchars($waste_type_id); ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label" for="name">Name:</label>
                                <input type="text" name="name" id="name" class="form-control" value="<?php echo htmlspecialchars($form_values['name'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="description">Description (Optional):</label>
                                <textarea name="description" id="description" class="form-control" rows="3"><?php echo htmlspecialchars($form_values['description'] ?? ''); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="disposal_guidelines">Disposal Guidelines (Optional):</label>
                                <textarea name="disposal_guidelines" id="disposal_guidelines" class="form-control" rows="3"><?php echo htmlspecialchars($form_values['disposal_guidelines'] ?? ''); ?></textarea>
                            </div>

                            <?php if ($action === 'create_form'): ?>
                                <button type="submit" name="create_waste_type" class="btn btn-primary">Create Waste Type</button>
                            <?php else: ?>
                                <button type="submit" name="update_waste_type" class="btn btn-primary">Update Waste Type</button>
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
