<?php
// Ensure this path is correct relative to customer_dashboard.php
require_once 'auth/auth.php';

// Require login for accessing this page
require_login();

// Redirect non-customer users to their respective dashboards
if (isset($_SESSION['role_name']) && $_SESSION['role_name'] !== 'customer') {
    // Assuming you have other dashboards like admin_dashboard.php or driver_dashboard.php
    $role_dashboard = $_SESSION['role_name'] . '_dashboard.php';
    if (file_exists($role_dashboard)) {
        header("Location: $role_dashboard");
        exit;
    } else {
        // Fallback to a generic page or index if their specific dashboard doesn't exist
        header("Location: index.php");
        exit;
    }
}


// If we reach here, the user is logged in.
// Proceed with including other files and displaying content.
include 'include/header.php'; // HTML head
// Moved db_connect include higher as it's needed for notifications in header
require_once 'auth/db_connect.php';
require_once 'include/helpers.php'; // For notification helpers

// If the user is logged in, fetch their notifications
$user_notifications = [];
$unread_notification_count = 0;
if (is_logged_in() && isset($_SESSION['user_id'])) {
    $current_user_id_for_notif = $_SESSION['user_id'];
    $user_notifications = getUserNotifications($pdo, $current_user_id_for_notif, 5); // Get 5 recent
    $unread_notification_count = getUnreadNotificationCount($pdo, $current_user_id_for_notif);
}

include 'include/sidebar.php';
?>

<div class="page-wrapper">
    <!-- Page header -->
    <div class="page-header d-print-none">
        <div class="container-xl">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <h2 class="page-title">
                        Customer Dashboard
                    </h2>
                </div>
            </div>
        </div>
    </div>
    <!-- Page body -->
    <div class="page-body">
        <div class="container-xl">
            <div class="row row-deck row-cards">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h3 class="card-title">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h3>
                            <p class="text-secondary">This is your central hub for managing your waste collection services.</p>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-4">
                    <div class="card">
                        <div class="card-body">
                            <h3 class="card-title">Waste Collection Schedule</h3>
                            <p class="text-secondary">View your upcoming collection dates and schedule new pickups.</p>
                            <a href="customer_schedule.php" class="btn btn-primary">View Schedule</a>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-4">
                    <div class="card">
                        <div class="card-body">
                            <h3 class="card-title">Billing & Payments</h3>
                            <p class="text-secondary">Check your billing history and make payments securely.</p>
                            <a href="customer_billing.php" class="btn btn-primary">View Billing</a>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-4">
                    <div class="card">
                        <div class="card-body">
                            <h3 class="card-title">Submit a Query</h3>
                            <p class="text-secondary">Have a question or concern? Let us know.</p>
                            <a href="customer_submit_query.php" class="btn btn-primary">Submit Query</a>
                        </div>
                    </div>
                </div>
                 <div class="col-sm-6 col-lg-4">
                    <div class="card">
                        <div class="card-body">
                            <h3 class="card-title">View Your Queries</h3>
                            <p class="text-secondary">Check the status of your submitted queries.</p>
                            <a href="customer_view_queries.php" class="btn btn-primary">View Queries</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include 'include/footer.php' ?>
</div>
