<?php
require_once 'auth/auth.php';
require_once 'auth/db_connect.php';
require_once 'include/helpers.php';

require_login_and_role(['customer'], 'auth/login.php', 'index.php');

$page_title = "My Billing & Payments";
$customer_id = getLoggedInCustomerId($pdo);

if (!$customer_id) {
    $_SESSION['error_message'] = "Your customer account could not be found. Please contact support.";
    header("Location: index.php");
    exit;
}

$action = $_GET['action'] ?? 'list_invoices'; // list_invoices, view_invoice
$billing_id_to_view = isset($_GET['billing_id']) ? (int)$_GET['billing_id'] : null;

$invoices = [];
$payments = [];
$invoice_to_view_details = null;
$payments_for_invoice_to_view = [];

if ($action === 'list_invoices') {
    $invoices = getCustomerInvoices($pdo, $customer_id);
    $payments = getCustomerPayments($pdo, $customer_id); // For a separate payment history tab/section
} elseif ($action === 'view_invoice' && $billing_id_to_view) {
    $invoice_to_view_details = getInvoiceById($pdo, $billing_id_to_view);
    // Security check: Ensure the invoice belongs to the logged-in customer
    if (!$invoice_to_view_details || $invoice_to_view_details['customer_id'] != $customer_id) {
        $_SESSION['error_message'] = "Invoice not found or access denied.";
        header("Location: customer_billing.php"); exit;
    }
    $payments_for_invoice_to_view = getPaymentsForInvoice($pdo, $billing_id_to_view);
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
                 <?php if ($action === 'view_invoice'): ?>
                    <div class="col-auto ms-auto d-print-none">
                        <a href="customer_billing.php" class="btn btn-outline-secondary">Back to My Invoices</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="page-body">
        <div class="container-xl">
            <?php if ($action === 'list_invoices'): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">My Invoices</h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table table-striped">
                        <thead><tr><th>Invoice ID</th><th>Description</th><th>Amount</th><th>Billing Date</th><th>Due Date</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php if (empty($invoices)): ?>
                                <tr><td colspan="7" class="text-center">You have no invoices.</td></tr>
                            <?php else: foreach ($invoices as $invoice): ?>
                                <tr id="invoice-<?php echo $invoice['billing_id']; ?>">
                                    <td>#<?php echo htmlspecialchars($invoice['billing_id']); ?></td>
                                    <td><?php echo htmlspecialchars($invoice['description']); ?></td>
                                    <td>$<?php echo htmlspecialchars(number_format($invoice['amount'], 2)); ?></td>
                                    <td><?php echo htmlspecialchars($invoice['billing_date']); ?></td>
                                    <td><?php echo htmlspecialchars($invoice['due_date']); ?></td>
                                    <td><span class="badge bg-<?php echo ($invoice['status'] === 'Paid' ? 'success' : ($invoice['status'] === 'Pending' ? 'warning' : ($invoice['status'] === 'Overdue' ? 'danger' : 'secondary'))); ?>"><?php echo htmlspecialchars(ucfirst($invoice['status'])); ?></span></td>
                                    <td><a href="?action=view_invoice&billing_id=<?php echo $invoice['billing_id']; ?>" class="btn btn-sm btn-outline-primary">View Details</a></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h3 class="card-title">My Payment History</h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table table-striped">
                        <thead><tr><th>Payment ID</th><th>Invoice ID</th><th>Invoice Desc.</th><th>Payment Date</th><th>Amount Paid</th><th>Method</th></tr></thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                                <tr><td colspan="6" class="text-center">You have no payment records.</td></tr>
                            <?php else: foreach ($payments as $payment): ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars($payment['payment_id']); ?></td>
                                    <td><a href="?action=view_invoice&billing_id=<?php echo $payment['billing_id']; ?>">#<?php echo htmlspecialchars($payment['billing_id']); ?></a></td>
                                    <td><?php echo htmlspecialchars($payment['invoice_description']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['payment_date']); ?></td>
                                    <td>$<?php echo htmlspecialchars(number_format($payment['amount_paid'], 2)); ?></td>
                                    <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php elseif ($action === 'view_invoice' && $invoice_to_view_details): ?>
            <div class="card" id="invoice-<?php echo $invoice_to_view_details['billing_id']; ?>">
                <div class="card-header">
                    <h3 class="card-title">Invoice #<?php echo htmlspecialchars($invoice_to_view_details['billing_id']); ?> Details</h3>
                    <div class="ms-auto">
                         <button onclick="printInvoice()" class="btn btn-outline-secondary d-print-none"><i class="ti ti-printer"></i> Print Invoice</button>
                    </div>
                </div>
                <div class="card-body" id="invoice-print-area">
                    <div class="row mb-4">
                        <div class="col-6">
                            <h4><?php echo htmlspecialchars($invoice_to_view_details['first_name'] . ' ' . $invoice_to_view_details['last_name']); ?></h4>
                            <p><?php echo nl2br(htmlspecialchars($invoice_to_view_details['customer_address'] ?? 'Address not available')); ?></p>
                            <p>Email: <?php echo htmlspecialchars($invoice_to_view_details['customer_email'] ?? 'N/A'); ?></p>
                            <p>Customer ID: <?php echo htmlspecialchars($invoice_to_view_details['customer_identifier']); ?></p>
                        </div>
                        <div class="col-6 text-end">
                            <h2>INVOICE #<?php echo htmlspecialchars($invoice_to_view_details['billing_id']); ?></h2>
                            <p><strong>Billing Date:</strong> <?php echo htmlspecialchars($invoice_to_view_details['billing_date']); ?></p>
                            <p><strong>Due Date:</strong> <?php echo htmlspecialchars($invoice_to_view_details['due_date']); ?></p>
                            <p><strong>Status:</strong> <span class="badge bg-<?php echo ($invoice_to_view_details['status'] === 'Paid' ? 'success' : ($invoice_to_view_details['status'] === 'Pending' ? 'warning' : ($invoice_to_view_details['status'] === 'Overdue' ? 'danger' : 'secondary'))); ?>"><?php echo htmlspecialchars(ucfirst($invoice_to_view_details['status'])); ?></span></p>
                        </div>
                    </div>

                    <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($invoice_to_view_details['description'])); ?></p>

                    <table class="table table-bordered mt-4">
                        <thead><tr><th>Item</th><th class="text-end">Amount</th></tr></thead>
                        <tbody><tr><td><?php echo nl2br(htmlspecialchars($invoice_to_view_details['description'])); ?></td><td class="text-end">$<?php echo htmlspecialchars(number_format($invoice_to_view_details['amount'], 2)); ?></td></tr></tbody>
                        <tfoot>
                            <tr><th colspan="1" class="text-end">Total Amount Due:</th><th class="text-end">$<?php echo htmlspecialchars(number_format($invoice_to_view_details['amount'], 2)); ?></th></tr>
                        </tfoot>
                    </table>

                    <?php if (!empty($payments_for_invoice_to_view)): ?>
                    <h4 class="mt-4">Payments Made:</h4>
                    <table class="table table-sm table-striped">
                        <thead><tr><th>Date</th><th>Amount Paid</th><th>Method</th><th>Transaction ID</th></tr></thead>
                        <tbody>
                        <?php
                            $total_paid_on_invoice = 0;
                            foreach($payments_for_invoice_to_view as $payment):
                            $total_paid_on_invoice += $payment['amount_paid'];
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($payment['payment_date']); ?></td>
                                <td>$<?php echo htmlspecialchars(number_format($payment['amount_paid'], 2)); ?></td>
                                <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                <td><?php echo htmlspecialchars($payment['transaction_id'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                         <tfoot>
                            <tr><th colspan="1" class="text-end">Total Paid:</th><th colspan="3">$<?php echo htmlspecialchars(number_format($total_paid_on_invoice, 2)); ?></th></tr>
                            <tr><th colspan="1" class="text-end">Amount Remaining:</th><th colspan="3">$<?php echo htmlspecialchars(number_format($invoice_to_view_details['amount'] - $total_paid_on_invoice, 2)); ?></th></tr>
                        </tfoot>
                    </table>
                    <?php else: ?>
                    <p class="mt-3"><em>No payments recorded for this invoice yet.</em></p>
                    <?php endif; ?>

                    <div class="mt-4 d-print-none">
                        <?php if($invoice_to_view_details['status'] === 'Pending' || $invoice_to_view_details['status'] === 'Partially Paid' || $invoice_to_view_details['status'] === 'Overdue'): ?>
                            <p><em>Payment instructions or link to a payment gateway would go here.</em></p>
                            <button class="btn btn-success" disabled>Pay Now (Feature not implemented)</button>
                        <?php elseif($invoice_to_view_details['status'] === 'Paid'): ?>
                            <p class="text-success"><em>This invoice has been fully paid. Thank you!</em></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <script>
                function printInvoice() {
                    const printContents = document.getElementById('invoice-print-area').innerHTML;
                    const originalContents = document.body.innerHTML;
                    document.body.innerHTML = printContents;
                    window.print();
                    document.body.innerHTML = originalContents;
                    // Re-initialize any JS if needed, or simply reload
                    window.location.reload();
                }
            </script>
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
