<?php
require_once 'auth/auth.php';
require_once 'auth/db_connect.php';
require_once 'include/helpers.php';

require_login_and_role(['customer'], 'auth/login.php', 'index.php');

$page_title = "My Queries";
$errors = []; // Not actively used on this page yet, but good practice

$customer_id = getLoggedInCustomerId($pdo);
$queries = [];

if (!$customer_id) {
    $_SESSION['error_message'] = "Your customer account could not be found. Please contact support.";
    header("Location: index.php");
    exit;
}

$queries = getCustomerQueries($pdo, $customer_id);

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
                <div class="col-auto ms-auto d-print-none">
                    <a href="customer_submit_query.php" class="btn btn-primary">
                        Submit New Query
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="page-body">
        <div class="container-xl">
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">My Submitted Queries</h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table table-striped">
                        <thead>
                            <tr>
                                <th>Query ID</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Last Update/Resolved</th>
                                <th>Response</th>
                                <!-- <th class="w-1">Actions</th> -->
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($queries)): ?>
                                <tr><td colspan="6" class="text-center">You have not submitted any queries yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($queries as $query): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($query['query_id']); ?></td>
                                    <td>
                                        <a href="#" data-bs-toggle="modal" data-bs-target="#queryDetailModal_<?php echo $query['query_id']; ?>">
                                            <?php echo htmlspecialchars($query['subject']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php
                                            $status_class = 'secondary';
                                            if ($query['status'] === 'Open') $status_class = 'info';
                                            elseif ($query['status'] === 'In Progress') $status_class = 'warning';
                                            elseif (in_array($query['status'], ['Resolved', 'Closed'])) $status_class = 'success';
                                            echo $status_class;
                                        ?>">
                                            <?php echo htmlspecialchars(ucfirst($query['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars(date("Y-m-d H:i", strtotime($query['created_at']))); ?></td>
                                    <td>
                                        <?php
                                        if ($query['responded_at']) {
                                            echo htmlspecialchars(date("Y-m-d H:i", strtotime($query['responded_at']))) . " (Responded)";
                                        } elseif ($query['resolved_at']) {
                                            echo htmlspecialchars(date("Y-m-d H:i", strtotime($query['resolved_at']))) . " (Resolved)";
                                        } else {
                                            echo 'Pending';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($query['response'])): ?>
                                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#queryDetailModal_<?php echo $query['query_id']; ?>">View Response</button>
                                        <?php else: echo 'No response yet.'; endif; ?>
                                    </td>
                                    <!-- <td> <a href="#" class="btn btn-sm">View</a> </td> -->
                                </tr>

                                <!-- Modal for Query Details -->
                                <div class="modal fade" id="queryDetailModal_<?php echo $query['query_id']; ?>" tabindex="-1" aria-labelledby="queryDetailModalLabel_<?php echo $query['query_id']; ?>" aria-hidden="true">
                                  <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                      <div class="modal-header">
                                        <h5 class="modal-title" id="queryDetailModalLabel_<?php echo $query['query_id']; ?>">Query Details: <?php echo htmlspecialchars($query['subject']); ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                      </div>
                                      <div class="modal-body">
                                        <p><strong>Status:</strong> <?php echo htmlspecialchars(ucfirst($query['status'])); ?></p>
                                        <p><strong>Submitted:</strong> <?php echo htmlspecialchars(date("Y-m-d H:i:s", strtotime($query['created_at']))); ?></p>
                                        <hr>
                                        <p><strong>My Query:</strong></p>
                                        <p><?php echo nl2br(htmlspecialchars($query['description'])); ?></p>

                                        <?php if (!empty($query['response'])): ?>
                                        <hr>
                                        <p><strong>Response from Support (<?php echo $query['responded_at'] ? htmlspecialchars(date("Y-m-d H:i", strtotime($query['responded_at']))) : ''; ?>):</strong></p>
                                        <div class="p-2 bg-light border rounded">
                                            <?php echo nl2br(htmlspecialchars($query['response'])); ?>
                                        </div>
                                        <?php endif; ?>
                                      </div>
                                      <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                      </div>
                                    </div>
                                  </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'include/footer.php'; ?>
