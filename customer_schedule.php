<?php
require_once 'auth/auth.php';
require_once 'auth/db_connect.php'; // PDO $pdo is available here
require_once 'include/helpers.php'; // Helper functions

// Accessible only to 'customer' role
require_login_and_role('customer', 'auth/login.php', 'index.php');

$page_title = "My Collection Schedules";

// Get the logged-in customer's ID
$customer_id = getLoggedInCustomerId($pdo);
$schedules = [];
$customer_location = null;

if ($customer_id) {
    $schedules = getCustomerSchedules($pdo, $customer_id);
    $customer_location = getLoggedInCustomerAddressGeo($pdo); // Fetch lat/lng
} else {
    // This case should ideally not happen if require_login_and_role works correctly
    // and every 'customer' user has a corresponding entry in the Customers table.
    $_SESSION['error_message'] = "Could not retrieve your customer details. Please contact support.";
    // Potentially redirect or show an error message prominently
}

include 'include/header.php';
include 'include/sidebar.php';
?>

<div class="page-wrapper">
    <div class="page-header d-print-none">
        <div class="container-xl">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <h2 class="page-title">
                        <?php echo htmlspecialchars($page_title); ?>
                    </h2>
                </div>
            </div>
        </div>
    </div>

    <div class="page-body">
        <div class="container-xl">
            <?php if (!$customer_id): ?>
                <div class="alert alert-warning">
                    Your customer information could not be found. Please ensure your profile is complete or contact support.
                </div>
            <?php else: ?>
                <div class="row row-cards">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Upcoming Collections</h3>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-vcenter card-table">
                                    <thead>
                                        <tr>
                                            <th>Schedule ID</th>
                                            <th>Collection Date</th>
                                            <th>Status</th>
                                            <th>Driver</th>
                                            <th>Vehicle</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($schedules)): ?>
                                            <tr><td colspan="6" class="text-center">You have no upcoming collection schedules.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($schedules as $schedule): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($schedule['schedule_id']); ?></td>
                                                <td><?php echo htmlspecialchars($schedule['collection_date']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php
                                                        echo ($schedule['status'] === 'completed' ? 'success' :
                                                             ($schedule['status'] === 'cancelled' ? 'danger' :
                                                             ($schedule['status'] === 'missed' ? 'warning' : 'info')));
                                                    ?>">
                                                        <?php echo htmlspecialchars(ucfirst($schedule['status'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars(($schedule['driver_first_name'] ? $schedule['driver_first_name'] . ' ' . $schedule['driver_last_name'] : 'N/A')); ?></td>
                                                <td><?php echo htmlspecialchars(($schedule['registration_number'] ? $schedule['registration_number'] . ($schedule['vehicle_model'] ? ' - '.$schedule['vehicle_model'] : '') : 'N/A')); ?></td>
                                                <td><?php echo nl2br(htmlspecialchars($schedule['notes'] ?? '')); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">My Registered Address</h3>
                            </div>
                            <div class="card-body">
                                <div id="customerMap" style="height: 300px; width: 100%; background-color: #eee;">
                                    <!-- Google Map will be embedded here -->
                                    <?php if ($customer_location && $customer_location['latitude'] && $customer_location['longitude']): ?>
                                        <p class="text-center p-3">Map loading... <br> If it doesn't load, ensure Google Maps API key is set.</p>
                                    <?php else: ?>
                                        <p class="text-center p-3">Your address is not set or is incomplete for map display. Please update your profile.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($customer_location && $customer_location['latitude'] && $customer_location['longitude']): ?>
    <script>
        // Google Maps JavaScript API for customer's address
        // Ensure Google Maps API script is loaded (e.g., in header.php)
        // <script async defer src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&callback=initCustomerMap"></script>

        let customerMapInstance;

        function initCustomerMap() {
            const location = {
                lat: <?php echo htmlspecialchars($customer_location['latitude']); ?>,
                lng: <?php echo htmlspecialchars($customer_location['longitude']); ?>
            };
            customerMapInstance = new google.maps.Map(document.getElementById('customerMap'), {
                zoom: 15,
                center: location
            });
            new google.maps.Marker({
                position: location,
                map: customerMapInstance,
                title: 'My Address'
            });
        }

        // Check if Google Maps API is loaded
        if (typeof google !== 'undefined' && typeof google.maps !== 'undefined') {
            // If using callback in API script URL, it calls initCustomerMap.
            // Otherwise, call it manually:
            // initCustomerMap();
            // For safety, if the API script has `&callback=initCustomerMap` this is not strictly needed,
            // but can be a fallback if that's missed.
            // However, to avoid multiple initializations, it's best to rely on the API callback.
            // If no callback is in the URL, uncomment:
            // window.onload = initCustomerMap;
        } else {
            document.getElementById('customerMap').innerHTML = '<p class="text-danger text-center p-3">Google Maps API could not be loaded. Please ensure API key is correct and script is included in the page.</p>';
        }
    </script>
    <?php endif; ?>

</div> <!-- .page-wrapper -->

<?php include 'include/footer.php'; ?>

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

    <?php if (isset($_SESSION['error_message'])): ?>
        showToast("<?php echo htmlspecialchars($_SESSION['error_message']); ?>", 'danger');
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success_message'])): ?>
        showToast("<?php echo htmlspecialchars($_SESSION['success_message']); ?>", 'success');
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
});
</script>
