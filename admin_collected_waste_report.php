<?php
require_once 'auth/auth.php';
require_once 'auth/db_connect.php'; // PDO $pdo is available here
require_once 'include/helpers.php'; // Helper functions

require_login_and_role(['admin', 'staff'], 'auth/login.php', 'index.php');

$page_title = "Collected Waste Report";

// Get filter parameters
$filter_date_from = $_GET['date_from'] ?? null;
$filter_date_to = $_GET['date_to'] ?? null;
$filter_customer_id = isset($_GET['customer_id']) && $_GET['customer_id'] !== '' ? (int)$_GET['customer_id'] : null;
$filter_driver_id = isset($_GET['driver_id']) && $_GET['driver_id'] !== '' ? (int)$_GET['driver_id'] : null;
$filter_waste_type_id = isset($_GET['waste_type_id']) && $_GET['waste_type_id'] !== '' ? (int)$_GET['waste_type_id'] : null;

// Fetch data for filters
$customers = getAllCustomers($pdo);
$drivers = getAllDrivers($pdo);
$waste_types = getWasteTypes($pdo);

// Fetch collected waste details with filters
$collected_waste_records = getAllCollectedWasteDetails(
    $pdo,
    $filter_date_from,
    $filter_date_to,
    $filter_customer_id,
    $filter_driver_id,
    $filter_waste_type_id
);

// Calculate summary
$total_quantity_by_type = [];
foreach ($collected_waste_records as $record) {
    $typeName = $record['waste_type_name'];
    if (!isset($total_quantity_by_type[$typeName])) {
        $total_quantity_by_type[$typeName] = 0;
    }
    $total_quantity_by_type[$typeName] += $record['quantity'];
}
arsort($total_quantity_by_type); // Sort by quantity descending


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
            <!-- Filter Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">Filter Report</h3>
                </div>
                <div class="card-body">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="GET" class="row gx-3 gy-2 align-items-end">
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label" for="date_from">Date From:</label>
                            <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo htmlspecialchars($filter_date_from ?? ''); ?>">
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label" for="date_to">Date To:</label>
                            <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo htmlspecialchars($filter_date_to ?? ''); ?>">
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label" for="customer_id">Customer:</label>
                            <select name="customer_id" id="customer_id" class="form-select">
                                <option value="">All Customers</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo $customer['customer_id']; ?>" <?php echo ($filter_customer_id == $customer['customer_id'] ? 'selected' : ''); ?>>
                                        <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name'] . ' (' . $customer['customer_identifier'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label" for="driver_id">Driver:</label>
                            <select name="driver_id" id="driver_id" class="form-select">
                                <option value="">All Drivers</option>
                                <?php foreach ($drivers as $driver): ?>
                                    <option value="<?php echo $driver['driver_id']; ?>" <?php echo ($filter_driver_id == $driver['driver_id'] ? 'selected' : ''); ?>>
                                        <?php echo htmlspecialchars($driver['first_name'] . ' ' . $driver['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label" for="waste_type_id">Waste Type:</label>
                            <select name="waste_type_id" id="waste_type_id" class="form-select">
                                <option value="">All Waste Types</option>
                                <?php foreach ($waste_types as $type): ?>
                                    <option value="<?php echo $type['waste_type_id']; ?>" <?php echo ($filter_waste_type_id == $type['waste_type_id'] ? 'selected' : ''); ?>>
                                        <?php echo htmlspecialchars($type['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="btn btn-secondary">Clear Filters</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Summary Section -->
            <?php if (!empty($total_quantity_by_type) && (empty($filter_customer_id) && empty($filter_driver_id)) ): // Show summary if not too narrowly filtered ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">Summary: Total Quantity by Waste Type (for current filter)</h3>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <?php foreach ($total_quantity_by_type as $type => $total): ?>
                            <dt class="col-sm-3"><?php echo htmlspecialchars($type); ?>:</dt>
                            <dd class="col-sm-9"><?php echo htmlspecialchars(number_format($total, 2)); ?> units</dd>
                        <?php endforeach; ?>
                    </dl>
                </div>
            </div>
            <?php endif; ?>

            <!-- Report Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Collected Waste Records</h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table table-striped">
                        <thead>
                            <tr>
                                <th>Record ID</th>
                                <th>Schedule ID</th>
                                <th>Customer</th>
                                <th>Driver</th>
                                <th>Vehicle</th>
                                <th>Waste Type</th>
                                <th>Quantity</th>
                                <th>Collection Date</th>
                                <th>Collection Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($collected_waste_records)): ?>
                                <tr><td colspan="9" class="text-center">No records found matching your criteria.</td></tr>
                            <?php else: ?>
                                <?php foreach ($collected_waste_records as $record): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($record['collected_waste_id']); ?></td>
                                    <td><a href="admin_schedule.php?action=edit_form&id=<?php echo $record['schedule_id']; ?>"><?php echo htmlspecialchars($record['schedule_id']); ?></a></td>
                                    <td><?php echo htmlspecialchars($record['customer_first_name'] . ' ' . $record['customer_last_name'] . ' (' . $record['customer_identifier'] . ')'); ?></td>
                                    <td><?php echo htmlspecialchars(($record['driver_first_name'] ? $record['driver_first_name'] . ' ' . $record['driver_last_name'] : 'N/A')); ?></td>
                                    <td><?php echo htmlspecialchars($record['vehicle_registration'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($record['waste_type_name']); ?></td>
                                    <td><?php echo htmlspecialchars(number_format($record['quantity'], 2)); ?></td>
                                    <td><?php echo htmlspecialchars(date("Y-m-d", strtotime($record['collection_time']))); ?></td>
                                    <td><?php echo htmlspecialchars(date("H:i:s", strtotime($record['collection_time']))); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Basic Pagination (Placeholder - server-side needed for real pagination) -->
                <?php if (count($collected_waste_records) > 20): // Simple conditional display, not actual pagination ?>
                <div class="card-footer d-flex align-items-center">
                    <p class="m-0 text-secondary">Showing <span>1</span> to <span><?php echo count($collected_waste_records); ?></span> of <span><?php echo count($collected_waste_records); ?></span> entries (Pagination not fully implemented)</p>
                    <ul class="pagination m-0 ms-auto">
                        <li class="page-item disabled"><a class="page-link" href="#">Prev</a></li>
                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                        <li class="page-item disabled"><a class="page-link" href="#">Next</a></li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'include/footer.php'; ?>
