<?php
// Ensure db_connect.php is available. Adjust path if helpers.php is moved.
// Assuming helpers.php is in 'include/', and db_connect.php is in 'auth/'
require_once __DIR__ . '/../auth/db_connect.php';

/**
 * Fetches all customers for dropdowns.
 *
 * @param PDO $pdo PDO database connection object.
 * @return array An array of customers (customer_id, user_id, first_name, last_name, customer_identifier).
 */
function getAllCustomers(PDO $pdo): array {
    // Fetches customer_id, their user_id, and name from Users table, and customer_identifier
    $stmt = $pdo->query("SELECT c.customer_id, c.user_id, u.first_name, u.last_name, c.customer_identifier
                         FROM Customers c
                         JOIN Users u ON c.user_id = u.user_id
                         ORDER BY u.last_name, u.first_name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetches all drivers for dropdowns.
 *
 * @param PDO $pdo PDO database connection object.
 * @return array An array of drivers (driver_id, user_id, first_name, last_name, license_number).
 */
function getAllDrivers(PDO $pdo): array {
    // Fetches driver_id, their user_id, and name from Users table, and license_number
    $stmt = $pdo->query("SELECT d.driver_id, d.user_id, u.first_name, u.last_name, d.license_number
                         FROM Drivers d
                         JOIN Users u ON d.user_id = u.user_id
                         ORDER BY u.last_name, u.first_name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetches all available vehicles for dropdowns.
 *
 * @param PDO $pdo PDO database connection object.
 * @return array An array of vehicles (vehicle_id, registration_number, model).
 */
function getAllVehicles(PDO $pdo): array {
    $stmt = $pdo->query("SELECT vehicle_id, registration_number, model
                         FROM Vehicles
                         WHERE status = 'available' OR status IS NULL OR status = '' -- Consider what statuses mean 'available'
                         ORDER BY registration_number");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetches a specific schedule's details for editing.
 *
 * @param PDO $pdo
 * @param int $schedule_id
 * @return array|false
 */
function getScheduleById(PDO $pdo, int $schedule_id) {
    $stmt = $pdo->prepare("SELECT cs.*, c.customer_identifier, u_cust.first_name as customer_fname, u_cust.last_name as customer_lname,
                                  u_driver.first_name as driver_fname, u_driver.last_name as driver_lname, v.registration_number
                           FROM CollectionSchedules cs
                           JOIN Customers c ON cs.customer_id = c.customer_id
                           JOIN Users u_cust ON c.user_id = u_cust.user_id
                           LEFT JOIN Drivers d ON cs.driver_id = d.driver_id
                           LEFT JOIN Users u_driver ON d.user_id = u_driver.user_id
                           LEFT JOIN Vehicles v ON cs.vehicle_id = v.vehicle_id
                           WHERE cs.schedule_id = :schedule_id");
    $stmt->bindParam(':schedule_id', $schedule_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Fetches all collection schedules with joined data for display.
 *
 * @param PDO $pdo
 * @return array
 */
function getAllCollectionSchedules(PDO $pdo): array {
    $sql = "SELECT cs.schedule_id, cs.collection_date, cs.status, cs.notes,
                   c.customer_identifier, cust_user.first_name AS customer_first_name, cust_user.last_name AS customer_last_name,
                   c.address_latitude, c.address_longitude,
                   d.license_number, driver_user.first_name AS driver_first_name, driver_user.last_name AS driver_last_name,
                   v.registration_number, v.model AS vehicle_model
            FROM CollectionSchedules cs
            JOIN Customers c ON cs.customer_id = c.customer_id
            JOIN Users cust_user ON c.user_id = cust_user.user_id
            LEFT JOIN Drivers d ON cs.driver_id = d.driver_id
            LEFT JOIN Users driver_user ON d.user_id = driver_user.user_id
            LEFT JOIN Vehicles v ON cs.vehicle_id = v.vehicle_id
            ORDER BY cs.collection_date DESC, cs.schedule_id DESC";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetches collection schedules for a specific customer.
 *
 * @param PDO $pdo
 * @param int $customer_id
 * @return array
 */
function getCustomerSchedules(PDO $pdo, int $customer_id): array {
    $sql = "SELECT cs.schedule_id, cs.collection_date, cs.status, cs.notes,
                   d.license_number, driver_user.first_name AS driver_first_name, driver_user.last_name AS driver_last_name,
                   v.registration_number, v.model AS vehicle_model
            FROM CollectionSchedules cs
            LEFT JOIN Drivers d ON cs.driver_id = d.driver_id
            LEFT JOIN Users driver_user ON d.user_id = driver_user.user_id
            LEFT JOIN Vehicles v ON cs.vehicle_id = v.vehicle_id
            WHERE cs.customer_id = :customer_id
            ORDER BY cs.collection_date DESC, cs.schedule_id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Retrieves the customer_id for the currently logged-in user.
 * Assumes user_id is stored in $_SESSION['user_id'].
 *
 * @param PDO $pdo PDO database connection object.
 * @return int|null The customer_id if found, otherwise null.
 */
function getLoggedInCustomerId(PDO $pdo): ?int {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT customer_id FROM Customers WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? (int)$result['customer_id'] : null;
}

/**
 * Retrieves the address (latitude, longitude) for the currently logged-in customer.
 *
 * @param PDO $pdo
 * @return array|null ['latitude' => val, 'longitude' => val] or null
 */
function getLoggedInCustomerAddressGeo(PDO $pdo): ?array {
    $customer_id = getLoggedInCustomerId($pdo);
    if (!$customer_id) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT address_latitude, address_longitude FROM Customers WHERE customer_id = :customer_id");
    $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result && !empty($result['address_latitude']) && !empty($result['address_longitude'])) {
        return ['latitude' => $result['address_latitude'], 'longitude' => $result['address_longitude']];
    }
    return null;
}


/**
 * Retrieves the driver_id for the currently logged-in user.
 * Assumes user_id is stored in $_SESSION['user_id'].
 *
 * @param PDO $pdo PDO database connection object.
 * @return int|null The driver_id if found, otherwise null.
 */
function getLoggedInDriverId(PDO $pdo): ?int {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT driver_id FROM Drivers WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? (int)$result['driver_id'] : null;
}

/**
 * Fetches collection schedules assigned to a specific driver.
 * Includes customer name and address details.
 *
 * @param PDO $pdo
 * @param int $driver_id
 * @return array
 */
function getDriverSchedules(PDO $pdo, int $driver_id): array {
    $sql = "SELECT cs.schedule_id, cs.collection_date, cs.status, cs.notes,
                   c.customer_identifier, cust_user.first_name AS customer_first_name, cust_user.last_name AS customer_last_name,
                   cust_user.address AS customer_address, c.address_latitude, c.address_longitude,
                   v.registration_number, v.model AS vehicle_model
            FROM CollectionSchedules cs
            JOIN Customers c ON cs.customer_id = c.customer_id
            JOIN Users cust_user ON c.user_id = cust_user.user_id
            LEFT JOIN Vehicles v ON cs.vehicle_id = v.vehicle_id
            WHERE cs.driver_id = :driver_id
            ORDER BY cs.collection_date ASC, cs.schedule_id ASC"; // Show oldest upcoming first
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':driver_id', $driver_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetches all waste types for dropdowns.
 *
 * @param PDO $pdo PDO database connection object.
 * @return array An array of waste types (waste_type_id, name).
 */
function getWasteTypes(PDO $pdo): array {
    $stmt = $pdo->query("SELECT waste_type_id, name FROM WasteTypes ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Inserts a new record into CollectedWaste.
 *
 * @param PDO $pdo
 * @param int $schedule_id
 * @param int $waste_type_id
 * @param float $quantity
 * @param string|null $collection_time (YYYY-MM-DD HH:MM:SS or HH:MM:SS format, defaults to current time if null)
 * @return bool True on success, false on failure.
 */
function recordCollectedWaste(PDO $pdo, int $schedule_id, int $waste_type_id, float $quantity, ?string $collection_time = null): bool {
    if ($collection_time === null) {
        $collection_time = date('Y-m-d H:i:s');
    } elseif (strlen($collection_time) <= 8) { // Check if only time 'H:i:s' is provided
         // Get date part from schedule
        $sch_stmt = $pdo->prepare("SELECT collection_date FROM CollectionSchedules WHERE schedule_id = :schedule_id");
        $sch_stmt->bindParam(':schedule_id', $schedule_id, PDO::PARAM_INT);
        $sch_stmt->execute();
        $sch_date_row = $sch_stmt->fetch(PDO::FETCH_ASSOC);
        if ($sch_date_row) {
            $collection_time = $sch_date_row['collection_date'] . ' ' . $collection_time;
        } else { // Fallback if schedule date not found, though unlikely
            $collection_time = date('Y-m-d') . ' ' . $collection_time;
        }
    }

    $sql = "INSERT INTO CollectedWaste (schedule_id, waste_type_id, quantity, collection_time)
            VALUES (:schedule_id, :waste_type_id, :quantity, :collection_time)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':schedule_id', $schedule_id, PDO::PARAM_INT);
    $stmt->bindParam(':waste_type_id', $waste_type_id, PDO::PARAM_INT);
    $stmt->bindParam(':quantity', $quantity); // PDO will handle float
    $stmt->bindParam(':collection_time', $collection_time);

    return $stmt->execute();
}

/**
 * Updates the status of a collection schedule.
 *
 * @param PDO $pdo
 * @param int $schedule_id
 * @param string $status
 * @return bool True on success, false on failure.
 */
function updateCollectionScheduleStatus(PDO $pdo, int $schedule_id, string $status): bool {
    $sql = "UPDATE CollectionSchedules SET status = :status WHERE schedule_id = :schedule_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':status', $status, PDO::PARAM_STR);
    $stmt->bindParam(':schedule_id', $schedule_id, PDO::PARAM_INT);
    return $stmt->execute();
}

/**
 * Fetches all collected waste records with necessary joins for the admin report.
 *
 * @param PDO $pdo
 * @param string|null $date_from Filter by collection date (from)
 * @param string|null $date_to Filter by collection date (to)
 * @param int|null $customer_id_filter
 * @param int|null $driver_id_filter
 * @param int|null $waste_type_id_filter
 * @return array
 */
function getAllCollectedWasteDetails(PDO $pdo, ?string $date_from = null, ?string $date_to = null, ?int $customer_id_filter = null, ?int $driver_id_filter = null, ?int $waste_type_id_filter = null): array {
    $sql = "SELECT cw.collected_waste_id, cw.quantity, cw.collection_time,
                   cs.schedule_id, cs.collection_date,
                   wt.name AS waste_type_name,
                   cust.customer_identifier, cust_user.first_name AS customer_first_name, cust_user.last_name AS customer_last_name,
                   driver_user.first_name AS driver_first_name, driver_user.last_name AS driver_last_name,
                   v.registration_number AS vehicle_registration
            FROM CollectedWaste cw
            JOIN CollectionSchedules cs ON cw.schedule_id = cs.schedule_id
            JOIN WasteTypes wt ON cw.waste_type_id = wt.waste_type_id
            JOIN Customers cust ON cs.customer_id = cust.customer_id
            JOIN Users cust_user ON cust.user_id = cust_user.user_id
            LEFT JOIN Drivers d ON cs.driver_id = d.driver_id
            LEFT JOIN Users driver_user ON d.user_id = driver_user.user_id
            LEFT JOIN Vehicles v ON cs.vehicle_id = v.vehicle_id
            WHERE 1=1"; // Base condition

    $params = [];
    if ($date_from) {
        $sql .= " AND DATE(cw.collection_time) >= :date_from";
        $params[':date_from'] = $date_from;
    }
    if ($date_to) {
        $sql .= " AND DATE(cw.collection_time) <= :date_to";
        $params[':date_to'] = $date_to;
    }
    if ($customer_id_filter) {
        $sql .= " AND cs.customer_id = :customer_id";
        $params[':customer_id'] = $customer_id_filter;
    }
    if ($driver_id_filter) {
        $sql .= " AND cs.driver_id = :driver_id";
        $params[':driver_id'] = $driver_id_filter;
    }
    if ($waste_type_id_filter) {
        $sql .= " AND cw.waste_type_id = :waste_type_id";
        $params[':waste_type_id'] = $waste_type_id_filter;
    }

    $sql .= " ORDER BY cw.collection_time DESC, cs.schedule_id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// --- Waste Types Management Helper Functions ---

/**
 * Fetches all waste types (including description and guidelines).
 *
 * @param PDO $pdo PDO database connection object.
 * @return array An array of waste types.
 */
function getAllWasteTypes(PDO $pdo): array {
    $stmt = $pdo->query("SELECT waste_type_id, name, description, disposal_guidelines FROM WasteTypes ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetches a single waste type by its ID.
 *
 * @param PDO $pdo
 * @param int $waste_type_id
 * @return array|false
 */
function getWasteTypeById(PDO $pdo, int $waste_type_id) {
    $stmt = $pdo->prepare("SELECT waste_type_id, name, description, disposal_guidelines FROM WasteTypes WHERE waste_type_id = :waste_type_id");
    $stmt->bindParam(':waste_type_id', $waste_type_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Adds a new waste type to the database.
 *
 * @param PDO $pdo
 * @param string $name
 * @param string|null $description
 * @param string|null $disposal_guidelines
 * @return bool True on success, false on failure.
 */
function addWasteType(PDO $pdo, string $name, ?string $description, ?string $disposal_guidelines): bool {
    $sql = "INSERT INTO WasteTypes (name, description, disposal_guidelines) VALUES (:name, :description, :disposal_guidelines)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':name', $name, PDO::PARAM_STR);
    $stmt->bindParam(':description', $description, PDO::PARAM_STR);
    $stmt->bindParam(':disposal_guidelines', $disposal_guidelines, PDO::PARAM_STR);
    return $stmt->execute();
}

/**
 * Updates an existing waste type.
 *
 * @param PDO $pdo
 * @param int $waste_type_id
 * @param string $name
 * @param string|null $description
 * @param string|null $disposal_guidelines
 * @return bool True on success, false on failure.
 */
function updateWasteType(PDO $pdo, int $waste_type_id, string $name, ?string $description, ?string $disposal_guidelines): bool {
    $sql = "UPDATE WasteTypes SET name = :name, description = :description, disposal_guidelines = :disposal_guidelines
            WHERE waste_type_id = :waste_type_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':waste_type_id', $waste_type_id, PDO::PARAM_INT);
    $stmt->bindParam(':name', $name, PDO::PARAM_STR);
    $stmt->bindParam(':description', $description, PDO::PARAM_STR);
    $stmt->bindParam(':disposal_guidelines', $disposal_guidelines, PDO::PARAM_STR);
    return $stmt->execute();
}

/**
 * Checks if a waste type is currently referenced in the CollectedWaste table.
 *
 * @param PDO $pdo
 * @param int $waste_type_id
 * @return bool True if in use, false otherwise.
 */
function isWasteTypeInUse(PDO $pdo, int $waste_type_id): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM CollectedWaste WHERE waste_type_id = :waste_type_id");
    $stmt->bindParam(':waste_type_id', $waste_type_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchColumn() > 0;
}

/**
 * Deletes a waste type.
 * It should ideally check for dependencies first using isWasteTypeInUse().
 *
 * @param PDO $pdo
 * @param int $waste_type_id
 * @return bool True on success, false on failure.
 */
function deleteWasteType(PDO $pdo, int $waste_type_id): bool {
    // Dependency check should be done before calling this function directly for safety
    // if (isWasteTypeInUse($pdo, $waste_type_id)) { return false; }
    $sql = "DELETE FROM WasteTypes WHERE waste_type_id = :waste_type_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':waste_type_id', $waste_type_id, PDO::PARAM_INT);
    return $stmt->execute();
}


// --- Vehicle Management Helper Functions ---

/**
 * Fetches all vehicles with all their details for admin display.
 * (Renamed from previous getAllVehicles to avoid confusion with the dropdown version)
 *
 * @param PDO $pdo PDO database connection object.
 * @return array An array of all vehicles.
 */
function getAllVehiclesDetails(PDO $pdo): array {
    $stmt = $pdo->query("SELECT vehicle_id, registration_number, model, capacity, status FROM Vehicles ORDER BY registration_number");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetches a single vehicle by its ID.
 *
 * @param PDO $pdo
 * @param int $vehicle_id
 * @return array|false
 */
function getVehicleById(PDO $pdo, int $vehicle_id) {
    $stmt = $pdo->prepare("SELECT vehicle_id, registration_number, model, capacity, status FROM Vehicles WHERE vehicle_id = :vehicle_id");
    $stmt->bindParam(':vehicle_id', $vehicle_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Fetches a single vehicle by its registration number.
 *
 * @param PDO $pdo
 * @param string $registration_number
 * @return array|false
 */
function getVehicleByRegistration(PDO $pdo, string $registration_number) {
    $stmt = $pdo->prepare("SELECT vehicle_id, registration_number, model, capacity, status FROM Vehicles WHERE registration_number = :registration_number");
    $stmt->bindParam(':registration_number', $registration_number, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Adds a new vehicle to the database.
 *
 * @param PDO $pdo
 * @param string $registration_number
 * @param string|null $model
 * @param float|null $capacity
 * @param string $status
 * @return bool True on success, false on failure.
 */
function addVehicle(PDO $pdo, string $registration_number, ?string $model, ?float $capacity, string $status): bool {
    $sql = "INSERT INTO Vehicles (registration_number, model, capacity, status) VALUES (:registration_number, :model, :capacity, :status)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':registration_number', $registration_number, PDO::PARAM_STR);
    $stmt->bindParam(':model', $model, PDO::PARAM_STR);
    $stmt->bindParam(':capacity', $capacity); // PDO handles float/null
    $stmt->bindParam(':status', $status, PDO::PARAM_STR);
    return $stmt->execute();
}

/**
 * Updates an existing vehicle.
 *
 * @param PDO $pdo
 * @param int $vehicle_id
 * @param string $registration_number
 * @param string|null $model
 * @param float|null $capacity
 * @param string $status
 * @return bool True on success, false on failure.
 */
function updateVehicle(PDO $pdo, int $vehicle_id, string $registration_number, ?string $model, ?float $capacity, string $status): bool {
    $sql = "UPDATE Vehicles SET registration_number = :registration_number, model = :model, capacity = :capacity, status = :status
            WHERE vehicle_id = :vehicle_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':vehicle_id', $vehicle_id, PDO::PARAM_INT);
    $stmt->bindParam(':registration_number', $registration_number, PDO::PARAM_STR);
    $stmt->bindParam(':model', $model, PDO::PARAM_STR);
    $stmt->bindParam(':capacity', $capacity);
    $stmt->bindParam(':status', $status, PDO::PARAM_STR);
    return $stmt->execute();
}

/**
 * Checks if a vehicle is currently referenced in non-completed/non-cancelled CollectionSchedules.
 *
 * @param PDO $pdo
 * @param int $vehicle_id
 * @return bool True if in use in active schedules, false otherwise.
 */
function isVehicleInUse(PDO $pdo, int $vehicle_id): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM CollectionSchedules
                           WHERE vehicle_id = :vehicle_id
                           AND status NOT IN ('completed', 'cancelled')"); // Check against active/pending schedules
    $stmt->bindParam(':vehicle_id', $vehicle_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchColumn() > 0;
}

/**
 * Deletes a vehicle.
 * Dependency check (isVehicleInUse) should be performed before calling this.
 *
 * @param PDO $pdo
 * @param int $vehicle_id
 * @return bool True on success, false on failure.
 */
function deleteVehicle(PDO $pdo, int $vehicle_id): bool {
    // if (isVehicleInUse($pdo, $vehicle_id)) { return false; } // Safety check
    $sql = "DELETE FROM Vehicles WHERE vehicle_id = :vehicle_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':vehicle_id', $vehicle_id, PDO::PARAM_INT);
    return $stmt->execute();
}


// --- Staff Management Helper Functions ---

/**
 * Fetches all roles for dropdowns.
 * @param PDO $pdo
 * @return array
 */
function getAllRoles(PDO $pdo): array {
    $stmt = $pdo->query("SELECT role_id, role_name FROM Roles ORDER BY role_name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Adds a new user and returns the user_id.
 * This is a generic user creation function.
 *
 * @param PDO $pdo
 * @param string $username
 * @param string $password (raw password, will be hashed)
 * @param int $role_id
 * @param string $first_name
 * @param string $last_name
 * @param string $email
 * @param string|null $phone_number
 * @param string|null $address
 * @return int|false The new user_id on success, false on failure.
 */
function addUser(PDO $pdo, string $username, string $password, int $role_id, string $first_name, string $last_name, string $email, ?string $phone_number, ?string $address) {
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $sql = "INSERT INTO Users (username, password, role_id, first_name, last_name, email, phone_number, address)
            VALUES (:username, :password, :role_id, :first_name, :last_name, :email, :phone_number, :address)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':password', $hashed_password);
    $stmt->bindParam(':role_id', $role_id, PDO::PARAM_INT);
    $stmt->bindParam(':first_name', $first_name);
    $stmt->bindParam(':last_name', $last_name);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':phone_number', $phone_number);
    $stmt->bindParam(':address', $address);

    if ($stmt->execute()) {
        return $pdo->lastInsertId();
    }
    return false;
}

/**
 * Adds a new staff record.
 * @param PDO $pdo
 * @param int $user_id
 * @param string $job_title
 * @return bool
 */
function addStaff(PDO $pdo, int $user_id, string $job_title): bool {
    $sql = "INSERT INTO Staff (user_id, job_title) VALUES (:user_id, :job_title)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':job_title', $job_title);
    return $stmt->execute();
}

/**
 * Adds a new driver record.
 * @param PDO $pdo
 * @param int $user_id
 * @param string $license_number
 * @return bool
 */
function addDriver(PDO $pdo, int $user_id, string $license_number): bool {
    $sql = "INSERT INTO Drivers (user_id, license_number) VALUES (:user_id, :license_number)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':license_number', $license_number);
    return $stmt->execute();
}

/**
 * Fetches all staff members with their user and role details.
 * @param PDO $pdo
 * @return array
 */
function getAllStaffDetails(PDO $pdo): array {
    $sql = "SELECT s.staff_id, u.user_id, u.first_name, u.last_name, u.username, u.email, u.phone_number, u.address,
                   r.role_name, s.job_title, d.license_number, d.driver_id
            FROM Staff s
            JOIN Users u ON s.user_id = u.user_id
            JOIN Roles r ON u.role_id = r.role_id
            LEFT JOIN Drivers d ON u.user_id = d.user_id  -- Left join in case not all staff are drivers
            ORDER BY u.last_name, u.first_name";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetches comprehensive details for a single staff member by staff_id.
 * @param PDO $pdo
 * @param int $staff_id
 * @return array|false
 */
function getStaffDetailsById(PDO $pdo, int $staff_id) {
    $sql = "SELECT s.staff_id, u.user_id, u.first_name, u.last_name, u.username, u.email, u.phone_number, u.address,
                   u.role_id, s.job_title, d.license_number, d.driver_id
            FROM Staff s
            JOIN Users u ON s.user_id = u.user_id
            LEFT JOIN Drivers d ON u.user_id = d.user_id
            WHERE s.staff_id = :staff_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':staff_id', $staff_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Updates core user details. Password update is separate.
 * @param PDO $pdo
 * @param int $user_id
 * @param string $first_name
 * @param string $last_name
 * @param string $email
 * @param string|null $phone_number
 * @param string|null $address
 * @param int $role_id
 * @return bool
 */
function updateUserDetails(PDO $pdo, int $user_id, string $first_name, string $last_name, string $email, ?string $phone_number, ?string $address, int $role_id): bool {
    $sql = "UPDATE Users SET first_name = :first_name, last_name = :last_name, email = :email,
            phone_number = :phone_number, address = :address, role_id = :role_id
            WHERE user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':first_name', $first_name);
    $stmt->bindParam(':last_name', $last_name);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':phone_number', $phone_number);
    $stmt->bindParam(':address', $address);
    $stmt->bindParam(':role_id', $role_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    return $stmt->execute();
}

/**
 * Updates staff-specific details (job title).
 * @param PDO $pdo
 * @param int $staff_id
 * @param string $job_title
 * @return bool
 */
function updateStaffDetails(PDO $pdo, int $staff_id, string $job_title): bool {
    $sql = "UPDATE Staff SET job_title = :job_title WHERE staff_id = :staff_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':job_title', $job_title);
    $stmt->bindParam(':staff_id', $staff_id, PDO::PARAM_INT);
    return $stmt->execute();
}

/**
 * Updates driver-specific details (license number).
 * Fetches driver_id based on user_id if not directly provided.
 * @param PDO $pdo
 * @param int $user_id
 * @param string $license_number
 * @return bool
 */
function updateDriverDetails(PDO $pdo, int $user_id, string $license_number): bool {
    // Check if driver record exists, if not, create one (upsert logic)
    $stmt = $pdo->prepare("SELECT driver_id FROM Drivers WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $driver = $stmt->fetch();

    if ($driver) {
        $sql = "UPDATE Drivers SET license_number = :license_number WHERE user_id = :user_id";
    } else {
        // If user is made a driver, but had no driver record.
        $sql = "INSERT INTO Drivers (user_id, license_number) VALUES (:user_id, :license_number)";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':license_number', $license_number);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    return $stmt->execute();
}

/**
 * Removes a driver record by user_id.
 * @param PDO $pdo
 * @param int $user_id
 * @return bool
 */
function removeDriverRecord(PDO $pdo, int $user_id): bool {
    $stmt = $pdo->prepare("DELETE FROM Drivers WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    return $stmt->execute();
}


/**
 * Checks if a driver (by driver_id) is assigned to any non-completed/non-cancelled schedules.
 * @param PDO $pdo
 * @param int $driver_id
 * @return bool
 */
function isStaffDriverAssignedToSchedules(PDO $pdo, int $driver_id): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM CollectionSchedules
                           WHERE driver_id = :driver_id AND status NOT IN ('completed', 'cancelled')");
    $stmt->bindParam(':driver_id', $driver_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchColumn() > 0;
}


/**
 * Deletes a staff member and their associated user and driver records.
 * Uses a transaction. Checks for active schedules if the staff is a driver.
 *
 * @param PDO $pdo
 * @param int $staff_id The ID from the Staff table.
 * @return bool True on success, false on failure or if dependencies prevent deletion.
 * @throws PDOException If transaction fails.
 */
function deleteStaffUser(PDO $pdo, int $staff_id): bool {
    $staff_details = getStaffDetailsById($pdo, $staff_id);
    if (!$staff_details) {
        return false; // Staff not found
    }
    $user_id = $staff_details['user_id'];
    $driver_id = $staff_details['driver_id']; // This will be null if not a driver

    // Dependency Check: If staff is a driver, check for active schedules
    if ($driver_id && isStaffDriverAssignedToSchedules($pdo, $driver_id)) {
        // Cannot delete if driver has active schedules.
        // An error message should be set by the calling script.
        return false;
    }

    try {
        $pdo->beginTransaction();

        // 1. Delete from Drivers table (if they are a driver)
        if ($driver_id) {
            $stmt_driver = $pdo->prepare("DELETE FROM Drivers WHERE user_id = :user_id");
            $stmt_driver->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt_driver->execute();
        }

        // 2. Delete from Staff table
        $stmt_staff = $pdo->prepare("DELETE FROM Staff WHERE user_id = :user_id");
        $stmt_staff->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt_staff->execute();

        // 3. Delete from Users table
        // Other dependencies on Users table (e.g. Notifications, CustomerQueries if user was also a customer)
        // are not checked here but should be considered in a full system.
        // For now, direct deletion. A soft delete or deactivation of User might be safer.
        $stmt_user = $pdo->prepare("DELETE FROM Users WHERE user_id = :user_id");
        $stmt_user->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt_user->execute();

        $pdo->commit();
        return true;

    } catch (PDOException $e) {
        $pdo->rollBack();
        // Log error $e->getMessage()
        return false;
    }
}


// --- Customer Query and Notification Helper Functions ---

/**
 * Submits a new customer query.
 * @param PDO $pdo
 * @param int $customer_id
 * @param string $subject
 * @param string $description
 * @return bool
 */
function submitCustomerQuery(PDO $pdo, int $customer_id, string $subject, string $description): bool {
    $sql = "INSERT INTO CustomerQueries (customer_id, subject, description, status)
            VALUES (:customer_id, :subject, :description, 'Open')";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
    $stmt->bindParam(':subject', $subject, PDO::PARAM_STR);
    $stmt->bindParam(':description', $description, PDO::PARAM_STR);
    return $stmt->execute();
}

/**
 * Fetches all queries for a specific customer.
 * @param PDO $pdo
 * @param int $customer_id
 * @return array
 */
function getCustomerQueries(PDO $pdo, int $customer_id): array {
    $sql = "SELECT query_id, subject, description, status, created_at, resolved_at, response, responded_at, response_by_user_id
            FROM CustomerQueries
            WHERE customer_id = :customer_id
            ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetches all customer queries for admin/staff view.
 * @param PDO $pdo
 * @return array
 */
function getAllCustomerQueries(PDO $pdo): array {
    $sql = "SELECT cq.query_id, cq.subject, cq.description, cq.status, cq.created_at, cq.resolved_at,
                   cq.response, cq.responded_at,
                   c.customer_identifier, u_cust.first_name AS customer_first_name, u_cust.last_name AS customer_last_name,
                   u_resp.username AS responder_username
            FROM CustomerQueries cq
            JOIN Customers c ON cq.customer_id = c.customer_id
            JOIN Users u_cust ON c.user_id = u_cust.user_id
            LEFT JOIN Users u_resp ON cq.response_by_user_id = u_resp.user_id
            ORDER BY cq.created_at DESC";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetches a single query by its ID, including customer and responder info.
 * @param PDO $pdo
 * @param int $query_id
 * @return array|false
 */
function getQueryById(PDO $pdo, int $query_id) {
    $sql = "SELECT cq.*, c.customer_identifier, u_cust.first_name AS customer_first_name, u_cust.last_name AS customer_last_name, u_cust.email AS customer_email,
                   u_resp.username AS responder_username
            FROM CustomerQueries cq
            JOIN Customers c ON cq.customer_id = c.customer_id
            JOIN Users u_cust ON c.user_id = u_cust.user_id
            LEFT JOIN Users u_resp ON cq.response_by_user_id = u_resp.user_id
            WHERE cq.query_id = :query_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':query_id', $query_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Updates the status of a customer query.
 * Sets resolved_at if status is 'Resolved' or 'Closed'.
 * @param PDO $pdo
 * @param int $query_id
 * @param string $status
 * @return bool
 */
function updateQueryStatus(PDO $pdo, int $query_id, string $status): bool {
    $resolved_at = (in_array($status, ['Resolved', 'Closed'])) ? date('Y-m-d H:i:s') : null;
    $sql = "UPDATE CustomerQueries SET status = :status, resolved_at = :resolved_at WHERE query_id = :query_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':status', $status, PDO::PARAM_STR);
    $stmt->bindParam(':resolved_at', $resolved_at);
    $stmt->bindParam(':query_id', $query_id, PDO::PARAM_INT);
    return $stmt->execute();
}

/**
 * Adds/Updates a response to a customer query.
 * @param PDO $pdo
 * @param int $query_id
 * @param string $response_text
 * @param int $responder_user_id
 * @return bool
 */
function addOrUpdateQueryResponse(PDO $pdo, int $query_id, string $response_text, int $responder_user_id): bool {
    $sql = "UPDATE CustomerQueries
            SET response = :response, responded_at = CURRENT_TIMESTAMP, response_by_user_id = :response_by_user_id
            WHERE query_id = :query_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':response', $response_text, PDO::PARAM_STR);
    $stmt->bindParam(':response_by_user_id', $responder_user_id, PDO::PARAM_INT);
    $stmt->bindParam(':query_id', $query_id, PDO::PARAM_INT);
    return $stmt->execute();
}


/**
 * Creates a new notification.
 * @param PDO $pdo
 * @param int $user_id
 * @param string $message
 * @param string|null $link_url (Optional URL for the notification)
 * @return bool
 */
function createNotification(PDO $pdo, int $user_id, string $message, ?string $link_url = null): bool {
    $sql = "INSERT INTO Notifications (user_id, message, link_url) VALUES (:user_id, :message, :link_url)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':message', $message, PDO::PARAM_STR);
    $stmt->bindParam(':link_url', $link_url, PDO::PARAM_STR, ($link_url ? PDO::PARAM_STR : PDO::PARAM_NULL));
    return $stmt->execute();
}

/**
 * Fetches notifications for a user, most recent first.
 * @param PDO $pdo
 * @param int $user_id
 * @param int $limit Max number of notifications to fetch.
 * @return array
 */
function getUserNotifications(PDO $pdo, int $user_id, int $limit = 5): array {
    $sql = "SELECT notification_id, message, link_url, is_read, created_at
            FROM Notifications
            WHERE user_id = :user_id
            ORDER BY created_at DESC
            LIMIT :limit";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Marks a specific notification as read for a user.
 * @param PDO $pdo
 * @param int $notification_id
 * @param int $user_id (for security, ensure notification belongs to the user)
 * @return bool
 */
function markNotificationAsRead(PDO $pdo, int $notification_id, int $user_id): bool {
    $sql = "UPDATE Notifications SET is_read = TRUE
            WHERE notification_id = :notification_id AND user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':notification_id', $notification_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    return $stmt->execute();
}

/**
 * Gets the count of unread notifications for a user.
 * @param PDO $pdo
 * @param int $user_id
 * @return int
 */
function getUnreadNotificationCount(PDO $pdo, int $user_id): int {
    $sql = "SELECT COUNT(*) FROM Notifications WHERE user_id = :user_id AND is_read = FALSE";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

/**
 * Retrieves the user_id for a given customer_id.
 * @param PDO $pdo
 * @param int $customer_id
 * @return int|null
 */
function getUserIdByCustomerId(PDO $pdo, int $customer_id): ?int {
    $stmt = $pdo->prepare("SELECT user_id FROM Customers WHERE customer_id = :customer_id");
    $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? (int)$result['user_id'] : null;
}

// --- Billing and Payment Helper Functions ---

/**
 * Creates a new invoice (Billing record).
 * @param PDO $pdo
 * @param int $customer_id
 * @param float $amount
 * @param string $billing_date
 * @param string $due_date
 * @param string $description
 * @param string $status (Default: 'Pending')
 * @return int|false The new billing_id on success, false on failure.
 */
function createInvoice(PDO $pdo, int $customer_id, float $amount, string $billing_date, string $due_date, string $description, string $status = 'Pending') {
    $sql = "INSERT INTO Billings (customer_id, amount, billing_date, due_date, description, status)
            VALUES (:customer_id, :amount, :billing_date, :due_date, :description, :status)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
    $stmt->bindParam(':amount', $amount);
    $stmt->bindParam(':billing_date', $billing_date);
    $stmt->bindParam(':due_date', $due_date);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':status', $status);

    if ($stmt->execute()) {
        return $pdo->lastInsertId();
    }
    return false;
}

/**
 * Fetches all invoices with customer details, supports filtering.
 * @param PDO $pdo
 * @param array $filters (e.g., ['status' => 'Pending', 'customer_id' => 1, 'date_from' => 'Y-m-d', 'date_to' => 'Y-m-d'])
 * @return array
 */
function getAllInvoices(PDO $pdo, array $filters = []): array {
    $sql = "SELECT b.*, u.first_name, u.last_name, c.customer_identifier
            FROM Billings b
            JOIN Customers c ON b.customer_id = c.customer_id
            JOIN Users u ON c.user_id = u.user_id
            WHERE 1=1";
    $params = [];

    if (!empty($filters['status'])) {
        $sql .= " AND b.status = :status";
        $params[':status'] = $filters['status'];
    }
    if (!empty($filters['customer_id'])) {
        $sql .= " AND b.customer_id = :customer_id";
        $params[':customer_id'] = $filters['customer_id'];
    }
    if (!empty($filters['date_from'])) {
        $sql .= " AND b.billing_date >= :date_from";
        $params[':date_from'] = $filters['date_from'];
    }
    if (!empty($filters['date_to'])) {
        $sql .= " AND b.billing_date <= :date_to";
        $params[':date_to'] = $filters['date_to'];
    }
    $sql .= " ORDER BY b.billing_date DESC, b.billing_id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetches all invoices for a specific customer.
 * @param PDO $pdo
 * @param int $customer_id
 * @return array
 */
function getCustomerInvoices(PDO $pdo, int $customer_id): array {
    $sql = "SELECT * FROM Billings WHERE customer_id = :customer_id ORDER BY billing_date DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetches a single invoice by its ID, with customer details.
 * @param PDO $pdo
 * @param int $billing_id
 * @return array|false
 */
function getInvoiceById(PDO $pdo, int $billing_id) {
    $sql = "SELECT b.*, u.first_name, u.last_name, c.customer_identifier, u.email as customer_email, u.address as customer_address
            FROM Billings b
            JOIN Customers c ON b.customer_id = c.customer_id
            JOIN Users u ON c.user_id = u.user_id
            WHERE b.billing_id = :billing_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':billing_id', $billing_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Updates the status of an invoice.
 * @param PDO $pdo
 * @param int $billing_id
 * @param string $status
 * @return bool
 */
function updateInvoiceStatus(PDO $pdo, int $billing_id, string $status): bool {
    $sql = "UPDATE Billings SET status = :status WHERE billing_id = :billing_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':billing_id', $billing_id, PDO::PARAM_INT);
    return $stmt->execute();
}

/**
 * Records a payment for an invoice.
 * @param PDO $pdo
 * @param int $billing_id
 * @param string $payment_date
 * @param float $amount_paid
 * @param string $payment_method
 * @param string|null $transaction_id
 * @return bool
 */
function recordPayment(PDO $pdo, int $billing_id, string $payment_date, float $amount_paid, string $payment_method, ?string $transaction_id = null): bool {
    $sql = "INSERT INTO Payments (billing_id, payment_date, amount_paid, payment_method, transaction_id)
            VALUES (:billing_id, :payment_date, :amount_paid, :payment_method, :transaction_id)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':billing_id', $billing_id, PDO::PARAM_INT);
    $stmt->bindParam(':payment_date', $payment_date);
    $stmt->bindParam(':amount_paid', $amount_paid);
    $stmt->bindParam(':payment_method', $payment_method);
    $stmt->bindParam(':transaction_id', $transaction_id, ($transaction_id ? PDO::PARAM_STR : PDO::PARAM_NULL));
    return $stmt->execute();
}

/**
 * Fetches all payments associated with a specific invoice.
 * @param PDO $pdo
 * @param int $billing_id
 * @return array
 */
function getPaymentsForInvoice(PDO $pdo, int $billing_id): array {
    $sql = "SELECT * FROM Payments WHERE billing_id = :billing_id ORDER BY payment_date DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':billing_id', $billing_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetches all payments made by a specific customer.
 * This requires joining Billings and Payments tables.
 * @param PDO $pdo
 * @param int $customer_id
 * @return array
 */
function getCustomerPayments(PDO $pdo, int $customer_id): array {
    $sql = "SELECT p.*, b.description as invoice_description, b.billing_date
            FROM Payments p
            JOIN Billings b ON p.billing_id = b.billing_id
            WHERE b.customer_id = :customer_id
            ORDER BY p.payment_date DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetches invoices that are overdue (due_date is in the past and status is not 'Paid' or 'Cancelled').
 * @param PDO $pdo
 * @return array
 */
function getOverdueInvoices(PDO $pdo): array {
    $today = date('Y-m-d');
    $sql = "SELECT b.*, u.first_name, u.last_name, c.customer_identifier, u.email as customer_email
            FROM Billings b
            JOIN Customers c ON b.customer_id = c.customer_id
            JOIN Users u ON c.user_id = u.user_id
            WHERE b.due_date < :today
            AND b.status NOT IN ('Paid', 'Cancelled', 'Partially Paid') -- Or just 'Paid', 'Cancelled' depending on how 'Partially Paid' is handled
            ORDER BY b.due_date ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':today', $today);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>
