<?php
require_once 'auth/auth.php';
require_once 'auth/db_connect.php'; // PDO $pdo is available here
require_once 'include/helpers.php'; // Helper functions

require_login_and_role('driver', 'auth/login.php', 'index.php');

$page_title = "My Assigned Schedules & Waste Collection";
$errors = [];
$success_messages = []; // Use an array to store multiple success messages if needed

$driver_id = getLoggedInDriverId($pdo);
$schedules = [];
$waste_types = getWasteTypes($pdo); // For dropdowns

if (!$driver_id) {
    $errors[] = "Driver details not found for your user account. Please contact an administrator.";
} else {
    $schedules = getDriverSchedules($pdo, $driver_id);
}

// Handle form submissions for updating schedule status and recording waste
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $driver_id) {
    $submitted_schedule_id = $_POST['schedule_id'] ?? null;
    $action_type = $_POST['action_type'] ?? null;

    if ($submitted_schedule_id && $action_type) {
        if ($action_type === 'update_status') {
            $new_status = $_POST['status'] ?? null;
            if ($new_status && $submitted_schedule_id) {
                if (updateCollectionScheduleStatus($pdo, $submitted_schedule_id, $new_status)) {
                    $success_messages[$submitted_schedule_id] = "Schedule status updated successfully!";
                } else {
                    $errors[] = "Failed to update status for schedule ID: $submitted_schedule_id.";
                }
            } else {
                $errors[] = "Missing status or schedule ID for status update.";
            }
        } elseif ($action_type === 'record_waste') {
            $waste_type_id = $_POST['waste_type_id'] ?? null;
            $quantity = $_POST['quantity'] ?? null;
            $collection_time = !empty($_POST['collection_time']) ? $_POST['collection_time'] : date('H:i:s'); // Default to current time if empty

            if ($waste_type_id && $quantity !== null && $quantity !== '' && $submitted_schedule_id) {
                if (is_numeric($quantity) && $quantity > 0) {
                    if (recordCollectedWaste($pdo, $submitted_schedule_id, $waste_type_id, (float)$quantity, $collection_time)) {
                        $success_messages[$submitted_schedule_id] = "Waste recorded successfully!";
                        // Optionally, mark schedule as completed if not already
                        // $current_schedule_details = getScheduleById($pdo, $submitted_schedule_id);
                        // if ($current_schedule_details && $current_schedule_details['status'] !== 'completed') {
                        //    updateCollectionScheduleStatus($pdo, $submitted_schedule_id, 'completed');
                        // }
                    } else {
                        $errors[] = "Failed to record waste for schedule ID: $submitted_schedule_id.";
                    }
                } else {
                     $errors[] = "Invalid quantity. Must be a positive number.";
                }
            } else {
                $errors[] = "Missing waste type, quantity, or schedule ID for recording waste.";
            }
        }
        // Re-fetch schedules to show updated data
        $schedules = getDriverSchedules($pdo, $driver_id);
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
                    <?php foreach ($errors as $error): ?>
                        <p class="mb-0"><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php // Success messages are displayed per card now ?>

            <?php if (!$driver_id): ?>
                <div class="alert alert-warning">Your driver information could not be found.</div>
            <?php elseif (empty($schedules)): ?>
                <div class="alert alert-info">You have no collection schedules assigned, or all past schedules are hidden.</div>
            <?php else: ?>
                <div class="row row-cards">
                    <?php foreach ($schedules as $schedule):
                        // Filter out schedules that are too old or already completed if desired
                        // For example, don't show if older than 7 days and completed
                        // $schedule_date_obj = new DateTime($schedule['collection_date']);
                        // $today_obj = new DateTime();
                        // $interval = $today_obj->diff($schedule_date_obj);
                        // if ($schedule['status'] === 'completed' && $interval->days > 7 && !$interval->invert) {
                        //     continue;
                        // }
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    Schedule ID: <?php echo htmlspecialchars($schedule['schedule_id']); ?>
                                    (<?php echo htmlspecialchars(ucfirst($schedule['status'])); ?>)
                                </h3>
                            </div>
                            <div class="card-body">
                                <?php if (isset($success_messages[$schedule['schedule_id']])): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <?php echo htmlspecialchars($success_messages[$schedule['schedule_id']]); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php endif; ?>

                                <p><strong>Customer:</strong> <?php echo htmlspecialchars($schedule['customer_first_name'] . ' ' . $schedule['customer_last_name']); ?></p>
                                <p><strong>Identifier:</strong> <?php echo htmlspecialchars($schedule['customer_identifier']); ?></p>
                                <p><strong>Address:</strong> <?php echo htmlspecialchars($schedule['customer_address']); ?></p>
                                <?php if ($schedule['address_latitude'] && $schedule['address_longitude']): ?>
                                <p><a href="https://maps.google.com/?q=<?php echo htmlspecialchars($schedule['address_latitude']); ?>,<?php echo htmlspecialchars($schedule['address_longitude']); ?>" target="_blank">View on Map</a></p>
                                <?php endif; ?>
                                <p><strong>Date:</strong> <?php echo htmlspecialchars($schedule['collection_date']); ?></p>
                                <?php if($schedule['vehicle_registration']) : ?>
                                <p><strong>Vehicle:</strong> <?php echo htmlspecialchars($schedule['vehicle_registration'] . ($schedule['vehicle_model'] ? ' - ' . $schedule['vehicle_model'] : '')); ?></p>
                                <?php endif; ?>
                                <p><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($schedule['notes'] ?? 'N/A')); ?></p>

                                <hr>
                                <!-- Update Status Form -->
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="mb-3">
                                    <input type="hidden" name="schedule_id" value="<?php echo $schedule['schedule_id']; ?>">
                                    <input type="hidden" name="action_type" value="update_status">
                                    <div class="mb-3">
                                        <label for="status_<?php echo $schedule['schedule_id']; ?>" class="form-label">Update Schedule Status:</label>
                                        <select name="status" id="status_<?php echo $schedule['schedule_id']; ?>" class="form-select">
                                            <option value="scheduled" <?php echo ($schedule['status'] === 'scheduled' ? 'selected' : ''); ?>>Scheduled</option>
                                            <option value="completed" <?php echo ($schedule['status'] === 'completed' ? 'selected' : ''); ?>>Completed</option>
                                            <option value="missed" <?php echo ($schedule['status'] === 'missed' ? 'selected' : ''); ?>>Missed</option>
                                            <option value="cancelled" <?php echo ($schedule['status'] === 'cancelled' ? 'selected' : ''); ?>>Cancelled</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-info">Update Status</button>
                                </form>

                                <!-- Record Waste Form -->
                                <?php if ($schedule['status'] !== 'cancelled'): // Don't show for cancelled schedules ?>
                                <hr>
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                                    <input type="hidden" name="schedule_id" value="<?php echo $schedule['schedule_id']; ?>">
                                    <input type="hidden" name="action_type" value="record_waste">
                                    <h5 class="mt-3">Record Collected Waste:</h5>
                                    <div class="mb-3">
                                        <label for="waste_type_id_<?php echo $schedule['schedule_id']; ?>" class="form-label">Waste Type:</label>
                                        <select name="waste_type_id" id="waste_type_id_<?php echo $schedule['schedule_id']; ?>" class="form-select" required>
                                            <option value="">Select Waste Type</option>
                                            <?php foreach ($waste_types as $type): ?>
                                                <option value="<?php echo $type['waste_type_id']; ?>"><?php echo htmlspecialchars($type['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="quantity_<?php echo $schedule['schedule_id']; ?>" class="form-label">Quantity (e.g., kg, items):</label>
                                        <input type="number" step="0.01" name="quantity" id="quantity_<?php echo $schedule['schedule_id']; ?>" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="collection_time_<?php echo $schedule['schedule_id']; ?>" class="form-label">Collection Time (HH:MM):</label>
                                        <input type="time" name="collection_time" id="collection_time_<?php echo $schedule['schedule_id']; ?>" class="form-control" value="<?php echo date('H:i'); ?>">
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-success">Record Waste</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'include/footer.php'; ?>
