<?php
require_once 'auth/auth.php';
require_once 'auth/db_connect.php';
require_once 'include/helpers.php';

require_login_and_role(['admin', 'staff'], 'auth/login.php', 'index.php');

$page_title = "Manage Customer Queries";
$errors = [];
$success_message = '';

$action = $_GET['action'] ?? 'list'; // list, view, update_status, respond
$query_id_to_manage = isset($_GET['id']) ? (int)$_GET['id'] : null;
$current_user_id = $_SESSION['user_id']; // For logging who responded

// Handle POST requests for updating status and adding response
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $query_id_to_manage) {
    if (isset($_POST['update_status_submit'])) {
        $new_status = $_POST['status'] ?? null;
        if ($new_status) {
            if (updateQueryStatus($pdo, $query_id_to_manage, $new_status)) {
                $success_message = "Query status updated successfully.";
                // Create notification for customer
                $query_details_for_notif = getQueryById($pdo, $query_id_to_manage);
                if($query_details_for_notif) {
                    $customer_user_id = getUserIdByCustomerId($pdo, $query_details_for_notif['customer_id']); // Need this helper
                    if ($customer_user_id) {
                         createNotification(
                            $pdo,
                            $customer_user_id,
                            "Status of your query (ID: {$query_id_to_manage} - " . substr($query_details_for_notif['subject'], 0, 30) . "...) has been updated to: {$new_status}.",
                            "customer_view_queries.php#query-" . $query_id_to_manage // Link to specific query if possible
                        );
                    }
                }
                $action = 'view'; // Refresh view
            } else {
                $errors[] = "Failed to update query status.";
            }
        } else {
            $errors[] = "No status provided for update.";
        }
    } elseif (isset($_POST['add_response_submit'])) {
        $response_text = trim($_POST['response_text'] ?? '');
        if (!empty($response_text)) {
            if (addOrUpdateQueryResponse($pdo, $query_id_to_manage, $response_text, $current_user_id)) {
                $success_message = "Response added successfully.";
                 // Optionally update status to 'In Progress' or 'Responded' if it was 'Open'
                $current_query_for_status_update = getQueryById($pdo, $query_id_to_manage);
                if ($current_query_for_status_update && $current_query_for_status_update['status'] === 'Open') {
                    updateQueryStatus($pdo, $query_id_to_manage, 'In Progress');
                }
                 // Create notification for customer
                $query_details_for_notif = getQueryById($pdo, $query_id_to_manage);
                 if($query_details_for_notif) {
                    $customer_user_id = getUserIdByCustomerId($pdo, $query_details_for_notif['customer_id']); // Need this helper
                     if ($customer_user_id) {
                        createNotification(
                            $pdo,
                            $customer_user_id,
                            "A response has been posted to your query (ID: {$query_id_to_manage} - " . substr($query_details_for_notif['subject'], 0, 30) . "...).",
                            "customer_view_queries.php#query-" . $query_id_to_manage
                        );
                    }
                }
                $action = 'view'; // Refresh view
            } else {
                $errors[] = "Failed to add response.";
            }
        } else {
            $errors[] = "Response text cannot be empty.";
        }
    }
}


$all_queries = [];
$query_details = null;
$possible_statuses = ['Open', 'In Progress', 'Resolved', 'Closed', 'On Hold'];


if ($action === 'list') {
    $all_queries = getAllCustomerQueries($pdo);
} elseif ($action === 'view' && $query_id_to_manage) {
    $query_details = getQueryById($pdo, $query_id_to_manage);
    if (!$query_details) {
        $_SESSION['error_message'] = "Query not found.";
        header("Location: admin_manage_queries.php");
        exit;
    }
}

// getUserIdByCustomerId is now in helpers.php

include 'include/header.php';
include 'include/sidebar.php';
?>

<div class="page-wrapper">
    <div class="page-header d-print-none">
        <div class="container-xl">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <h2 class="page-title"><?php echo htmlspecialchars($page_title); ?></h2>
                    <?php if ($action !== 'list'): ?>
                        <a href="admin_manage_queries.php" class="btn btn-outline-secondary btn-sm mt-2">Back to Query List</a>
                    <?php endif; ?>
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
            <div class="card">
                <div class="card-header"><h3 class="card-title">All Customer Queries</h3></div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table table-striped">
                        <thead><tr><th>ID</th><th>Customer</th><th>Subject</th><th>Status</th><th>Submitted</th><th>Responded By</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php if (empty($all_queries)): ?>
                                <tr><td colspan="7" class="text-center">No customer queries found.</td></tr>
                            <?php else: foreach ($all_queries as $query): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($query['query_id']); ?></td>
                                    <td><?php echo htmlspecialchars($query['customer_first_name'] . ' ' . $query['customer_last_name'] . ' (' . $query['customer_identifier'] . ')'); ?></td>
                                    <td><?php echo htmlspecialchars(substr($query['subject'],0,50)).(strlen($query['subject'])>50 ? '...' : ''); ?></td>
                                    <td><span class="badge bg-<?php
                                        $status_class = 'secondary';
                                        if ($query['status'] === 'Open') $status_class = 'info';
                                        elseif ($query['status'] === 'In Progress') $status_class = 'warning';
                                        elseif (in_array($query['status'], ['Resolved', 'Closed'])) $status_class = 'success';
                                        echo $status_class;
                                    ?>"><?php echo htmlspecialchars(ucfirst($query['status'])); ?></span></td>
                                    <td><?php echo htmlspecialchars(date("Y-m-d H:i", strtotime($query['created_at']))); ?></td>
                                    <td><?php echo htmlspecialchars($query['responder_username'] ?? 'N/A'); ?></td>
                                    <td><a href="?action=view&id=<?php echo $query['query_id']; ?>" class="btn btn-sm btn-outline-primary">View/Respond</a></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php elseif ($action === 'view' && $query_details): ?>
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Query Details (ID: <?php echo htmlspecialchars($query_details['query_id']); ?>)</h3></div>
                        <div class="card-body">
                            <p><strong>Customer:</strong> <?php echo htmlspecialchars($query_details['customer_first_name'] . ' ' . $query_details['customer_last_name'] . ' (' . $query_details['customer_identifier'] . ')'); ?></p>
                            <p><strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($query_details['customer_email']); ?>"><?php echo htmlspecialchars($query_details['customer_email']); ?></a></p>
                            <p><strong>Subject:</strong> <?php echo htmlspecialchars($query_details['subject']); ?></p>
                            <p><strong>Submitted:</strong> <?php echo htmlspecialchars(date("Y-m-d H:i:s", strtotime($query_details['created_at']))); ?></p>
                            <hr>
                            <p><strong>Description:</strong></p>
                            <div class="p-2 bg-light border rounded mb-3">
                                <?php echo nl2br(htmlspecialchars($query_details['description'])); ?>
                            </div>

                            <?php if ($query_details['response']): ?>
                            <hr>
                            <p><strong>Current Response (by <?php echo htmlspecialchars($query_details['responder_username'] ?? 'Unknown'); ?> at <?php echo $query_details['responded_at'] ? htmlspecialchars(date("Y-m-d H:i", strtotime($query_details['responded_at']))) : ''; ?>):</strong></p>
                            <div class="p-2 bg-light border rounded" style="background-color: #e9ecef !important;">
                                <?php echo nl2br(htmlspecialchars($query_details['response'])); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Manage Query</h3></div>
                        <div class="card-body">
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?action=view&id=<?php echo $query_details['query_id']; ?>" method="POST" class="mb-4">
                                <input type="hidden" name="query_id" value="<?php echo $query_details['query_id']; ?>">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Update Status:</label>
                                    <select name="status" id="status" class="form-select">
                                        <?php foreach ($possible_statuses as $status_opt): ?>
                                        <option value="<?php echo $status_opt; ?>" <?php echo ($query_details['status'] === $status_opt ? 'selected' : ''); ?>>
                                            <?php echo ucfirst($status_opt); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" name="update_status_submit" class="btn btn-primary">Update Status</button>
                            </form>
                            <hr>
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?action=view&id=<?php echo $query_details['query_id']; ?>" method="POST">
                                <input type="hidden" name="query_id" value="<?php echo $query_details['query_id']; ?>">
                                <div class="mb-3">
                                    <label for="response_text" class="form-label"><?php echo $query_details['response'] ? 'Update Response:' : 'Add Response:'; ?></label>
                                    <textarea name="response_text" id="response_text" class="form-control" rows="5" required><?php echo htmlspecialchars($query_details['response'] ?? ''); ?></textarea>
                                </div>
                                <button type="submit" name="add_response_submit" class="btn btn-success">
                                    <?php echo $query_details['response'] ? 'Update Response' : 'Submit Response'; ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'include/footer.php'; ?>
