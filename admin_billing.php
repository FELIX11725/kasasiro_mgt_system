<?php
require_once 'auth/auth.php';
require_once 'auth/db_connect.php';
require_once 'include/helpers.php';

require_login_and_role(['admin', 'staff'], 'auth/login.php', 'index.php');

$page_title = "Billing Management";
$errors = [];
$success_message = '';

// Possible statuses for invoices and payment methods for forms
$invoice_statuses = ['Pending', 'Paid', 'Overdue', 'Cancelled', 'Partially Paid'];
$payment_methods = ['Card', 'Bank Transfer', 'Cash', 'Online Gateway', 'Other'];

$action = $_GET['action'] ?? 'list_invoices'; // list_invoices, create_invoice, view_invoice, record_payment
$billing_id_to_manage = isset($_GET['billing_id']) ? (int)$_GET['billing_id'] : null;
$customer_id_filter = isset($_GET['customer_filter']) ? (int)$_GET['customer_filter'] : null;
$status_filter = $_GET['status_filter'] ?? '';
$date_from_filter = $_GET['date_from_filter'] ?? '';
$date_to_filter = $_GET['date_to_filter'] ?? '';


// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->beginTransaction();
    try {
        if (isset($_POST['generate_invoice_submit'])) {
            $customer_id = $_POST['customer_id'] ?? null;
            $amount = $_POST['amount'] ?? null;
            $billing_date = $_POST['billing_date'] ?? date('Y-m-d');
            $due_date = $_POST['due_date'] ?? null;
            $description = trim($_POST['description'] ?? '');

            if (!$customer_id || !$amount || !$due_date || empty($description)) {
                $errors[] = "Customer, Amount, Due Date, and Description are required for an invoice.";
            } elseif (!is_numeric($amount) || $amount <= 0) {
                $errors[] = "Amount must be a positive number.";
            } else {
                $new_invoice_id = createInvoice($pdo, $customer_id, (float)$amount, $billing_date, $due_date, $description, 'Pending');
                if ($new_invoice_id) {
                    $success_message = "Invoice #{$new_invoice_id} created successfully for customer.";
                     // Notify customer
                    $customer_user_id = getUserIdByCustomerId($pdo, $customer_id);
                    if($customer_user_id){
                        createNotification($pdo, $customer_user_id, "A new invoice #{$new_invoice_id} for an amount of {$amount} has been generated.", "customer_billing.php#invoice-".$new_invoice_id);
                    }
                    $action = 'list_invoices';
                } else {
                    $errors[] = "Failed to create invoice.";
                }
            }
        } elseif (isset($_POST['update_invoice_status_submit']) && $billing_id_to_manage) {
            $new_status = $_POST['invoice_status'] ?? null;
            if ($new_status && in_array($new_status, $invoice_statuses)) {
                if (updateInvoiceStatus($pdo, $billing_id_to_manage, $new_status)) {
                    $success_message = "Invoice #{$billing_id_to_manage} status updated to {$new_status}.";
                    $action = 'view_invoice'; // Refresh view
                } else {
                    $errors[] = "Failed to update invoice status.";
                }
            } else {
                $errors[] = "Invalid status selected.";
            }
        } elseif (isset($_POST['record_payment_submit']) && $billing_id_to_manage) {
            $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
            $amount_paid = $_POST['amount_paid'] ?? null;
            $payment_method = $_POST['payment_method'] ?? null;
            $transaction_id = trim($_POST['transaction_id'] ?? '');
            $invoice_details = getInvoiceById($pdo, $billing_id_to_manage);

            if (!$amount_paid || !$payment_method || !$invoice_details) {
                $errors[] = "Payment Date, Amount Paid, and Payment Method are required.";
            } elseif (!is_numeric($amount_paid) || $amount_paid <= 0) {
                $errors[] = "Amount paid must be a positive number.";
            } else {
                if (recordPayment($pdo, $billing_id_to_manage, $payment_date, (float)$amount_paid, $payment_method, $transaction_id ?: null)) {
                    $success_message = "Payment of {$amount_paid} recorded for invoice #{$billing_id_to_manage}.";

                    // Update invoice status based on payment
                    $total_paid_for_invoice = 0;
                    $payments_for_invoice = getPaymentsForInvoice($pdo, $billing_id_to_manage);
                    foreach ($payments_for_invoice as $p) {
                        $total_paid_for_invoice += $p['amount_paid'];
                    }

                    $new_invoice_status = $invoice_details['status'];
                    if ($total_paid_for_invoice >= $invoice_details['amount']) {
                        $new_invoice_status = 'Paid';
                    } elseif ($total_paid_for_invoice > 0 && $total_paid_for_invoice < $invoice_details['amount']) {
                        $new_invoice_status = 'Partially Paid';
                    }
                    // Only update if status changed
                    if ($new_invoice_status !== $invoice_details['status']) {
                         updateInvoiceStatus($pdo, $billing_id_to_manage, $new_invoice_status);
                    }
                     // Notify customer of payment
                    $customer_user_id_pay = getUserIdByCustomerId($pdo, $invoice_details['customer_id']);
                    if($customer_user_id_pay){
                         createNotification($pdo, $customer_user_id_pay, "A payment of {$amount_paid} was recorded for invoice #{$billing_id_to_manage}.", "customer_billing.php#invoice-".$billing_id_to_manage);
                    }
                    $action = 'view_invoice'; // Refresh view
                } else {
                    $errors[] = "Failed to record payment.";
                }
            }
        }
        if(empty($errors)) $pdo->commit(); else $pdo->rollBack();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $errors[] = "Database Error: " . $e->getMessage();
    }
}


// Fetch data for views
$customers_for_dropdown = getAllCustomers($pdo);
$invoices = [];
$invoice_to_view = null;
$payments_for_invoice_view = [];

if ($action === 'list_invoices') {
    $filters = [];
    if($customer_id_filter) $filters['customer_id'] = $customer_id_filter;
    if($status_filter) $filters['status'] = $status_filter;
    if($date_from_filter) $filters['date_from'] = $date_from_filter;
    if($date_to_filter) $filters['date_to'] = $date_to_filter;
    $invoices = getAllInvoices($pdo, $filters);
} elseif ($action === 'view_invoice' && $billing_id_to_manage) {
    $invoice_to_view = getInvoiceById($pdo, $billing_id_to_manage);
    if ($invoice_to_view) {
        $payments_for_invoice_view = getPaymentsForInvoice($pdo, $billing_id_to_manage);
    } else {
        $_SESSION['error_message'] = "Invoice not found.";
        header("Location: admin_billing.php"); exit;
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
                <div class="col-auto ms-auto d-print-none">
                    <?php if ($action === 'list_invoices'): ?>
                        <a href="?action=create_invoice" class="btn btn-primary">Generate New Invoice</a>
                    <?php else: ?>
                        <a href="?action=list_invoices" class="btn btn-outline-secondary">Back to Invoice List</a>
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
                <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <?php if ($action === 'create_invoice'): ?>
            <div class="card">
                <div class="card-header"><h3 class="card-title">Generate New Invoice</h3></div>
                <div class="card-body">
                    <form action="?action=create_invoice" method="POST">
                        <div class="mb-3">
                            <label class="form-label" for="customer_id">Customer:</label>
                            <select name="customer_id" id="customer_id" class="form-select" required>
                                <option value="">Select Customer</option>
                                <?php foreach ($customers_for_dropdown as $customer): ?>
                                <option value="<?php echo $customer['customer_id']; ?>"><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name'] . ' (' . $customer['customer_identifier'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3"><label class="form-label" for="amount">Amount:</label><input type="number" step="0.01" name="amount" id="amount" class="form-control" required></div>
                            <div class="col-md-4 mb-3"><label class="form-label" for="billing_date">Billing Date:</label><input type="date" name="billing_date" id="billing_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div>
                            <div class="col-md-4 mb-3"><label class="form-label" for="due_date">Due Date:</label><input type="date" name="due_date" id="due_date" class="form-control" required></div>
                        </div>
                        <div class="mb-3"><label class="form-label" for="description">Description:</label><textarea name="description" id="description" class="form-control" rows="3" required></textarea></div>
                        <button type="submit" name="generate_invoice_submit" class="btn btn-primary">Generate Invoice</button>
                    </form>
                </div>
            </div>

            <?php elseif ($action === 'list_invoices'): ?>
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title">Filter Invoices</h3></div>
                <div class="card-body">
                    <form action="" method="GET" class="row g-3">
                        <input type="hidden" name="action" value="list_invoices">
                        <div class="col-md-3"><label for="customer_filter" class="form-label">Customer</label><select name="customer_filter" id="customer_filter" class="form-select"><option value="">All Customers</option><?php foreach ($customers_for_dropdown as $c): ?><option value="<?php echo $c['customer_id']; ?>" <?php echo ($customer_id_filter == $c['customer_id'] ? 'selected' : ''); ?>><?php echo htmlspecialchars($c['first_name'].' '.$c['last_name']); ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-3"><label for="status_filter" class="form-label">Status</label><select name="status_filter" id="status_filter" class="form-select"><option value="">All Statuses</option><?php foreach ($invoice_statuses as $s): ?><option value="<?php echo $s; ?>" <?php echo ($status_filter == $s ? 'selected' : ''); ?>><?php echo ucfirst($s); ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-2"><label for="date_from_filter" class="form-label">From</label><input type="date" name="date_from_filter" id="date_from_filter" class="form-control" value="<?php echo htmlspecialchars($date_from_filter); ?>"></div>
                        <div class="col-md-2"><label for="date_to_filter" class="form-label">To</label><input type="date" name="date_to_filter" id="date_to_filter" class="form-control" value="<?php echo htmlspecialchars($date_to_filter); ?>"></div>
                        <div class="col-md-2 align-self-end"><button type="submit" class="btn btn-primary w-100">Filter</button></div>
                    </form>
                </div>
            </div>
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-vcenter card-table table-striped">
                        <thead><tr><th>ID</th><th>Customer</th><th>Amount</th><th>Billing Date</th><th>Due Date</th><th>Status</th><th>Description</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php if (empty($invoices)): ?>
                                <tr><td colspan="8" class="text-center">No invoices found.</td></tr>
                            <?php else: foreach ($invoices as $invoice): ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars($invoice['billing_id']); ?></td>
                                    <td><?php echo htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name'] . ' (' . $invoice['customer_identifier'] . ')'); ?></td>
                                    <td><?php echo htmlspecialchars(number_format($invoice['amount'], 2)); ?></td>
                                    <td><?php echo htmlspecialchars($invoice['billing_date']); ?></td>
                                    <td><?php echo htmlspecialchars($invoice['due_date']); ?></td>
                                    <td><span class="badge bg-<?php echo ($invoice['status'] === 'Paid' ? 'success' : ($invoice['status'] === 'Pending' ? 'warning' : ($invoice['status'] === 'Overdue' ? 'danger' : 'secondary'))); ?>"><?php echo htmlspecialchars(ucfirst($invoice['status'])); ?></span></td>
                                    <td><?php echo htmlspecialchars(substr($invoice['description'], 0, 50) . (strlen($invoice['description']) > 50 ? '...' : '')); ?></td>
                                    <td><a href="?action=view_invoice&billing_id=<?php echo $invoice['billing_id']; ?>" class="btn btn-sm btn-outline-primary">View/Manage</a></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php elseif ($action === 'view_invoice' && $invoice_to_view): ?>
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Invoice #<?php echo htmlspecialchars($invoice_to_view['billing_id']); ?> Details</h3></div>
                        <div class="card-body">
                            <p><strong>Customer:</strong> <?php echo htmlspecialchars($invoice_to_view['first_name'] . ' ' . $invoice_to_view['last_name'] . ' (' . $invoice_to_view['customer_identifier'] . ')'); ?></p>
                            <p><strong>Amount:</strong> $<?php echo htmlspecialchars(number_format($invoice_to_view['amount'], 2)); ?></p>
                            <p><strong>Billing Date:</strong> <?php echo htmlspecialchars($invoice_to_view['billing_date']); ?></p>
                            <p><strong>Due Date:</strong> <?php echo htmlspecialchars($invoice_to_view['due_date']); ?></p>
                            <p><strong>Current Status:</strong> <span class="badge bg-<?php echo ($invoice_to_view['status'] === 'Paid' ? 'success' : ($invoice_to_view['status'] === 'Pending' ? 'warning' : ($invoice_to_view['status'] === 'Overdue' ? 'danger' : 'secondary'))); ?>"><?php echo htmlspecialchars(ucfirst($invoice_to_view['status'])); ?></span></p>
                            <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($invoice_to_view['description'])); ?></p>
                        </div>
                        <div class="card-footer">
                             <!-- Basic HTML Print View -->
                             <button onclick="window.print();" class="btn btn-outline-secondary">Print Invoice</button>
                        </div>
                    </div>
                    <div class="card mt-4">
                        <div class="card-header"><h3 class="card-title">Payments for Invoice #<?php echo htmlspecialchars($invoice_to_view['billing_id']); ?></h3></div>
                        <div class="table-responsive">
                            <table class="table table-vcenter card-table">
                                <thead><tr><th>Payment ID</th><th>Date</th><th>Amount Paid</th><th>Method</th><th>Transaction ID</th></tr></thead>
                                <tbody>
                                    <?php if(empty($payments_for_invoice_view)): ?>
                                        <tr><td colspan="5" class="text-center">No payments recorded for this invoice yet.</td></tr>
                                    <?php else: foreach($payments_for_invoice_view as $payment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($payment['payment_id']); ?></td>
                                            <td><?php echo htmlspecialchars($payment['payment_date']); ?></td>
                                            <td>$<?php echo htmlspecialchars(number_format($payment['amount_paid'], 2)); ?></td>
                                            <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                            <td><?php echo htmlspecialchars($payment['transaction_id'] ?? 'N/A'); ?></td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card mb-4">
                        <div class="card-header"><h3 class="card-title">Update Invoice Status</h3></div>
                        <div class="card-body">
                            <form action="?action=view_invoice&billing_id=<?php echo $invoice_to_view['billing_id']; ?>" method="POST">
                                <div class="mb-3">
                                    <label for="invoice_status" class="form-label">Status:</label>
                                    <select name="invoice_status" id="invoice_status" class="form-select">
                                        <?php foreach ($invoice_statuses as $status_opt): ?>
                                        <option value="<?php echo $status_opt; ?>" <?php echo ($invoice_to_view['status'] === $status_opt ? 'selected' : ''); ?>><?php echo ucfirst($status_opt); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" name="update_invoice_status_submit" class="btn btn-primary">Update Status</button>
                            </form>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Record New Payment</h3></div>
                        <div class="card-body">
                            <form action="?action=view_invoice&billing_id=<?php echo $invoice_to_view['billing_id']; ?>" method="POST">
                                <div class="mb-3"><label class="form-label" for="payment_date">Payment Date:</label><input type="date" name="payment_date" id="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div>
                                <div class="mb-3"><label class="form-label" for="amount_paid">Amount Paid:</label><input type="number" step="0.01" name="amount_paid" id="amount_paid" class="form-control" required></div>
                                <div class="mb-3">
                                    <label class="form-label" for="payment_method">Payment Method:</label>
                                    <select name="payment_method" id="payment_method" class="form-select" required>
                                        <?php foreach ($payment_methods as $method): ?>
                                        <option value="<?php echo $method; ?>"><?php echo $method; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3"><label class="form-label" for="transaction_id">Transaction ID (Optional):</label><input type="text" name="transaction_id" id="transaction_id" class="form-control"></div>
                                <button type="submit" name="record_payment_submit" class="btn btn-success">Record Payment</button>
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
