<?php
require_once 'auth/auth.php';
require_once 'auth/db_connect.php'; // PDO $pdo is available here
require_once 'include/helpers.php'; // Helper functions

// Accessible only to 'admin' or 'operator' (or your equivalent roles)
// require_login_and_role(['admin', 'staff']); // Assuming 'staff' might be an operator role
// For now, let's assume 'admin' and 'driver' can manage schedules, adjust as per your Roles table
// For the purpose of this task, let's use 'admin' and a hypothetical 'operator' role.
// If 'operator' role doesn't exist, this will restrict to 'admin' only if 'operator' is not in DB.
// We added 'staff' to Roles in database.sql, let's use that.
require_login_and_role(['admin', 'staff'], 'auth/login.php', 'index.php');


$action = $_GET['action'] ?? 'list'; // Default action
$schedule_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

$page_title = "Manage Collection Schedules";
$errors = [];
$success_message = '';

// Fetch data for dropdowns
$customers = getAllCustomers($pdo);
$drivers = getAllDrivers($pdo);
$vehicles = getAllVehicles($pdo);

// Handle form submissions for Create and Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = $_POST['customer_id'] ?? null;
    $driver_id = $_POST['driver_id'] ?? null; // Can be empty
    $vehicle_id = $_POST['vehicle_id'] ?? null; // Can be empty
    $collection_date = $_POST['collection_date'] ?? null;
    $status = $_POST['status'] ?? 'scheduled'; // Default status
    $notes = $_POST['notes'] ?? '';
    $schedule_id_hidden = $_POST['schedule_id_hidden'] ?? null; // For edit

    // Validation
    if (empty($customer_id)) $errors[] = "Customer is required.";
    if (empty($collection_date)) $errors[] = "Collection date is required.";
    // Basic date validation (can be more complex)
    if (!empty($collection_date) && !DateTime::createFromFormat('Y-m-d', $collection_date)) {
        $errors[] = "Invalid collection date format. Use YYYY-MM-DD.";
    }


    if (empty($errors)) {
        if ($action === 'create_submit' || (isset($_POST['submit_action']) && $_POST['submit_action'] === 'create')) {
            try {
                $stmt = $pdo->prepare("INSERT INTO CollectionSchedules (customer_id, driver_id, vehicle_id, collection_date, status, notes)
                                       VALUES (:customer_id, :driver_id, :vehicle_id, :collection_date, :status, :notes)");
                $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
                $stmt->bindParam(':driver_id', $driver_id, PDO::PARAM_INT, ($driver_id ? PDO::PARAM_INT : PDO::PARAM_NULL));
                $stmt->bindParam(':vehicle_id', $vehicle_id, PDO::PARAM_INT, ($vehicle_id ? PDO::PARAM_INT : PDO::PARAM_NULL));
                $stmt->bindParam(':collection_date', $collection_date);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':notes', $notes);
                $stmt->execute();
                $success_message = "Schedule created successfully!";
                $action = 'list'; // Go back to list view
            } catch (PDOException $e) {
                $errors[] = "Error creating schedule: " . $e->getMessage();
            }
        } elseif ($action === 'edit_submit' || (isset($_POST['submit_action']) && $_POST['submit_action'] === 'edit' && $schedule_id_hidden)) {
             try {
                $stmt = $pdo->prepare("UPDATE CollectionSchedules
                                       SET customer_id = :customer_id, driver_id = :driver_id, vehicle_id = :vehicle_id,
                                           collection_date = :collection_date, status = :status, notes = :notes
                                       WHERE schedule_id = :schedule_id");
                $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
                $stmt->bindParam(':driver_id', $driver_id, ($driver_id ? PDO::PARAM_INT : PDO::PARAM_NULL));
                $stmt->bindParam(':vehicle_id', $vehicle_id, ($vehicle_id ? PDO::PARAM_INT : PDO::PARAM_NULL));
                $stmt->bindParam(':collection_date', $collection_date);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':notes', $notes);
                $stmt->bindParam(':schedule_id', $schedule_id_hidden, PDO::PARAM_INT);
                $stmt->execute();
                $success_message = "Schedule updated successfully!";
                $action = 'list'; // Go back to list view
            } catch (PDOException $e) {
                $errors[] = "Error updating schedule: " . $e->getMessage();
            }
        }
    } else {
        // If validation errors, stay on the current form action (create or edit)
        $action = ($schedule_id_hidden || $action === 'edit_form') ? 'edit_form' : 'create_form';
        // If it was an edit form, we need to re-fetch the schedule data for pre-filling if not already set for display
        if ($action === 'edit_form' && $schedule_id_hidden) {
             $current_schedule = getScheduleById($pdo, $schedule_id_hidden);
             if (!$current_schedule) $errors[] = "Schedule not found for editing.";
        }
    }
}

// Handle Delete action
if ($action === 'delete' && $schedule_id) {
    // CSRF protection would be good here in a real app
    try {
        // Optional: First, check if there's related data in CollectedWaste
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM CollectedWaste WHERE schedule_id = :schedule_id");
        $check_stmt->bindParam(':schedule_id', $schedule_id, PDO::PARAM_INT);
        $check_stmt->execute();
        $related_waste_count = $check_stmt->fetchColumn();

        if ($related_waste_count > 0) {
            // If related waste exists, prevent deletion or ask for confirmation to delete related data too.
            // For simplicity, we'll prevent deletion for now.
            $errors[] = "Cannot delete schedule. It has collected waste records associated with it. Please delete those first or reassign them.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM CollectionSchedules WHERE schedule_id = :schedule_id");
            $stmt->bindParam(':schedule_id', $schedule_id, PDO::PARAM_INT);
            $stmt->execute();
            $success_message = "Schedule deleted successfully!";
        }
    } catch (PDOException $e) {
        $errors[] = "Error deleting schedule: " . $e->getMessage();
    }
    $action = 'list'; // Go back to list view
}


// Fetch data for list view
$schedules = [];
if ($action === 'list') {
    $schedules = getAllCollectionSchedules($pdo);
}

// Fetch data for edit form
$current_schedule = null;
if ($action === 'edit_form' && $schedule_id) {
    $current_schedule = getScheduleById($pdo, $schedule_id);
    if (!$current_schedule) {
        $errors[] = "Schedule not found.";
        $action = 'list'; // Revert to list if ID is invalid
    }
}

// Determine which view to show based on action
if ($action === 'create_form' || $action === 'edit_form') {
    $form_action_path = htmlspecialchars($_SERVER["PHP_SELF"]) . "?action=" . ($action === 'create_form' ? 'create_submit' : 'edit_submit');
}

include 'include/header.php'; // Start HTML, body, include common CSS
include 'include/sidebar.php'; // Include the sidebar
?>

<div class="page-wrapper">
    <div class="page-header d-print-none">
        <div class="container-xl">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <h2 class="page-title">
                        <?php echo $page_title; ?>
                    </h2>
                </div>
                <?php if ($action === 'list'): ?>
                <div class="col-auto ms-auto d-print-none">
                    <a href="?action=create_form" class="btn btn-primary">
                        Create New Schedule
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="page-body">
        <div class="container-xl">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p class="mb-0"><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($action === 'list'): ?>
                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Customer</th>
                                    <th>Collection Date</th>
                                    <th>Driver</th>
                                    <th>Vehicle</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                    <th>Location (Lat,Lng)</th>
                                    <th class="w-1">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($schedules)): ?>
                                    <tr><td colspan="9" class="text-center">No schedules found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($schedules as $schedule): ?>
                                    <tr data-lat="<?php echo htmlspecialchars($schedule['address_latitude'] ?? ''); ?>" data-lng="<?php echo htmlspecialchars($schedule['address_longitude'] ?? ''); ?>">
                                        <td><?php echo htmlspecialchars($schedule['schedule_id']); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['customer_first_name'] . ' ' . $schedule['customer_last_name'] . ' (' . $schedule['customer_identifier'] . ')'); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['collection_date']); ?></td>
                                        <td><?php echo htmlspecialchars(($schedule['driver_first_name'] ? $schedule['driver_first_name'] . ' ' . $schedule['driver_last_name'] : 'N/A')); ?></td>
                                        <td><?php echo htmlspecialchars(($schedule['registration_number'] ? $schedule['registration_number'] . ($schedule['vehicle_model'] ? ' - '.$schedule['vehicle_model'] : '') : 'N/A')); ?></td>
                                        <td><span class="badge bg-<?php echo ($schedule['status'] === 'completed' ? 'success' : ($schedule['status'] === 'cancelled' ? 'danger' : 'secondary')); ?>">
                                            <?php echo htmlspecialchars(ucfirst($schedule['status'])); ?>
                                        </span></td>
                                        <td><?php echo nl2br(htmlspecialchars(substr($schedule['notes'] ?? '', 0, 50) . (strlen($schedule['notes'] ?? '') > 50 ? '...' : ''))); ?></td>
                                        <td><?php echo htmlspecialchars(($schedule['address_latitude'] && $schedule['address_longitude'] ? $schedule['address_latitude'].', '.$schedule['address_longitude'] : 'N/A')); ?></td>
                                        <td>
                                            <a href="?action=edit_form&id=<?php echo $schedule['schedule_id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                            <a href="?action=delete&id=<?php echo $schedule['schedule_id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this schedule?');">Delete</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Google Maps Placeholder -->
                <div class="mt-4">
                    <h3>Schedule Locations</h3>
                    <div id="map" style="height: 400px; width: 100%; background-color: #eee;">
                        <!-- Google Map will be embedded here -->
                        <p class="text-center p-5">Map placeholder: Google Maps API key needed. <br>
                           When a schedule row is hovered or clicked, its location should be highlighted here.
                        </p>
                    </div>
                </div>

            <?php elseif ($action === 'create_form' || $action === 'edit_form'):
                $current_data = ($action === 'edit_form' && $current_schedule) ? $current_schedule : [];
            ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><?php echo ($action === 'create_form' ? 'Create New' : 'Edit'); ?> Schedule</h3>
                    </div>
                    <div class="card-body">
                        <form action="<?php echo $form_action_path; ?>" method="POST">
                            <?php if ($action === 'edit_form'): ?>
                                <input type="hidden" name="schedule_id_hidden" value="<?php echo htmlspecialchars($current_data['schedule_id'] ?? ''); ?>">
                                <input type="hidden" name="submit_action" value="edit">
                            <?php else: ?>
                                <input type="hidden" name="submit_action" value="create">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label" for="customer_id">Customer:</label>
                                <select name="customer_id" id="customer_id" class="form-select" required>
                                    <option value="">Select Customer</option>
                                    <?php foreach ($customers as $customer): ?>
                                        <option value="<?php echo $customer['customer_id']; ?>" <?php echo (($current_data['customer_id'] ?? '') == $customer['customer_id'] ? 'selected' : ''); ?>>
                                            <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name'] . ' (' . $customer['customer_identifier'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="driver_id">Driver (Optional):</label>
                                <select name="driver_id" id="driver_id" class="form-select">
                                    <option value="">Select Driver (Optional)</option>
                                    <?php foreach ($drivers as $driver): ?>
                                        <option value="<?php echo $driver['driver_id']; ?>" <?php echo (($current_data['driver_id'] ?? '') == $driver['driver_id'] ? 'selected' : ''); ?>>
                                            <?php echo htmlspecialchars($driver['first_name'] . ' ' . $driver['last_name'] . ' (' . $driver['license_number'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="vehicle_id">Vehicle (Optional):</label>
                                <select name="vehicle_id" id="vehicle_id" class="form-select">
                                    <option value="">Select Vehicle (Optional)</option>
                                    <?php foreach ($vehicles as $vehicle): ?>
                                        <option value="<?php echo $vehicle['vehicle_id']; ?>" <?php echo (($current_data['vehicle_id'] ?? '') == $vehicle['vehicle_id'] ? 'selected' : ''); ?>>
                                            <?php echo htmlspecialchars($vehicle['registration_number'] . ($vehicle['model'] ? ' - ' . $vehicle['model'] : '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="collection_date">Collection Date:</label>
                                <input type="date" name="collection_date" id="collection_date" class="form-control"
                                       value="<?php echo htmlspecialchars($current_data['collection_date'] ?? date('Y-m-d')); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="status">Status:</label>
                                <select name="status" id="status" class="form-select" required>
                                    <option value="scheduled" <?php echo (($current_data['status'] ?? 'scheduled') === 'scheduled' ? 'selected' : ''); ?>>Scheduled</option>
                                    <option value="completed" <?php echo (($current_data['status'] ?? '') === 'completed' ? 'selected' : ''); ?>>Completed</option>
                                    <option value="cancelled" <?php echo (($current_data['status'] ?? '') === 'cancelled' ? 'selected' : ''); ?>>Cancelled</option>
                                    <option value="missed" <?php echo (($current_data['status'] ?? '') === 'missed' ? 'selected' : ''); ?>>Missed</option>
                                    <!-- Add other statuses as needed -->
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="notes">Notes (Optional):</label>
                                <textarea name="notes" id="notes" class="form-control" rows="3"><?php echo htmlspecialchars($current_data['notes'] ?? ''); ?></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <?php echo ($action === 'create_form' ? 'Create' : 'Update'); ?> Schedule
                            </button>
                            <a href="?action=list" class="btn btn-secondary">Cancel</a>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($action === 'list'): ?>
    <script>
        // Basic Google Maps integration placeholder - JavaScript API
        // You will need to include the Google Maps API script in your header.php or here.
        // e.g., <script async defer src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&callback=initMap"></script>
        // Make sure to replace YOUR_API_KEY with your actual API key.

        let map;
        let markers = [];

        function initMap() {
            // Default location (e.g., center of your service area)
            const defaultLocation = { lat: -34.397, lng: 150.644 };
            map = new google.maps.Map(document.getElementById('map'), {
                zoom: 8,
                center: defaultLocation
            });

            // Add markers for each schedule
            const scheduleRows = document.querySelectorAll('table tbody tr[data-lat][data-lng]');
            scheduleRows.forEach(row => {
                const lat = parseFloat(row.dataset.lat);
                const lng = parseFloat(row.dataset.lng);
                const customerInfo = row.cells[1].textContent; // Customer name/identifier

                if (!isNaN(lat) && !isNaN(lng)) {
                    const marker = new google.maps.Marker({
                        position: { lat, lng },
                        map: map,
                        title: customerInfo
                    });
                    markers.push(marker);

                    // Highlight marker on row hover (optional)
                    row.addEventListener('mouseover', () => {
                        marker.setAnimation(google.maps.Animation.BOUNCE);
                    });
                    row.addEventListener('mouseout', () => {
                        marker.setAnimation(null);
                    });
                     // Center map on marker when row is clicked
                    row.addEventListener('click', () => {
                        map.setCenter(marker.getPosition());
                        map.setZoom(12); // Zoom in closer
                    });
                }
            });
        }
        // If you are not using the callback in the API script URL, call initMap manually after the page loads
        // window.onload = initMap;
        // Or if Google API is loaded with callback, it will call initMap automatically.
        // For now, since the API key is not there, this won't fully work.
        // We need to ensure Google Maps API is loaded before calling initMap.
        // A simple check:
        if (typeof google !== 'undefined' && typeof google.maps !== 'undefined') {
            initMap();
        } else {
            // Fallback or message if API isn't loaded (e.g. API key missing)
            document.getElementById('map').innerHTML += '<p class="text-danger text-center">Google Maps API could not be loaded. Please ensure API key is correct and script is included.</p>';
        }
    </script>
    <?php endif; ?>

</div> <!-- .page-wrapper -->

<?php include 'include/footer.php'; // Include common footer, JS scripts ?>
