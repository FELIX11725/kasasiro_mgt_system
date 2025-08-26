<?php
require_once 'auth/auth.php';
require_once 'auth/db_connect.php';
require_once 'include/helpers.php';

require_login_and_role(['customer'], 'auth/login.php', 'index.php');

$page_title = "Submit New Query";
$errors = [];
$success_message = '';

$customer_id = getLoggedInCustomerId($pdo);

if (!$customer_id) {
    // Should not happen if role is 'customer' and DB is consistent
    $_SESSION['error_message'] = "Your customer account could not be found. Please contact support.";
    header("Location: index.php"); // Or a more appropriate error page
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($subject)) {
        $errors[] = "Subject is required.";
    }
    if (empty($description)) {
        $errors[] = "Description is required.";
    }

    if (empty($errors)) {
        if (submitCustomerQuery($pdo, $customer_id, $subject, $description)) {
            $success_message = "Your query has been submitted successfully! We will get back to you soon.";
            // Optionally, create a notification for the admin/staff
            // Example: (This requires knowing which admin/staff to notify, or a general pool)
            // $adminUsers = getUsersByRole($pdo, 'admin'); // You'd need this helper
            // foreach($adminUsers as $admin) {
            //    createNotification($pdo, $admin['user_id'], "New customer query submitted: " . substr($subject, 0, 50), "admin_manage_queries.php?action=view&id=" . $pdo->lastInsertId());
            // }
        } else {
            $errors[] = "There was an error submitting your query. Please try again.";
        }
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
            <?php if (!$success_message): // Hide form on success ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Submit your query</h3>
                </div>
                <div class="card-body">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                        <div class="mb-3">
                            <label class="form-label" for="subject">Subject:</label>
                            <input type="text" name="subject" id="subject" class="form-control" value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="description">Description:</label>
                            <textarea name="description" id="description" class="form-control" rows="6" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Submit Query</button>
                    </form>
                </div>
            </div>
            <?php else: ?>
                 <p><a href="customer_view_queries.php" class="btn btn-primary">View Your Queries</a></p>
            <?php endif; ?>
        </div>
    </div>
</div>

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

    <?php if (!empty($errors)): ?>
        <?php foreach ($errors as $error): ?>
            showToast("<?php echo htmlspecialchars($error); ?>", 'danger');
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($success_message): ?>
        showToast("<?php echo htmlspecialchars($success_message); ?>", 'success');
    <?php endif; ?>
});
</script>
