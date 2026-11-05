<?php
// admin/inquiries.php - Manage customer inquiries (view, mark read, delete)
require_once "../backend/auth/auth-guard.php";
require_once "../backend/config/db.php";

/** @var mysqli $conn */

$currentPage = basename($_SERVER['PHP_SELF']);

// Handle POST actions: toggle status or delete a single inquiry
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'], $_POST['id'])) {

    // Always cast the ID to an integer for safety
    $inquiryId = (int)$_POST['id'];

    if ($_POST['action'] === 'toggle_status') {
        // Flip status between Read and Unread using a CASE expression
        $sql = "UPDATE inquiries SET status = CASE WHEN status = 'Read' THEN 'Unread' ELSE 'Read' END WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $inquiryId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } elseif ($_POST['action'] === 'delete') {
        // Permanently remove the inquiry
        $sql = "DELETE FROM inquiries WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $inquiryId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    // Redirect to avoid duplicate submissions on refresh
    header("Location: inquiries.php");
    exit();
}

// Fetch all inquiries, newest first
$result = mysqli_query($conn, "SELECT id, name, email, subject, message, status, created_at FROM inquiries ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SHIORI Café Admin | Inquiries</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../frontend/assets/css/admin.css">
</head>
<body>

    <!-- Unified Admin Navigation Bar matching your theme -->
    <nav class="navbar navbar-expand-md admin-navbar">
        <div class="container">
            <a class="navbar-brand" href="index.php">栞 SHIORI Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav" style="border-color: rgba(255,255,255,0.3);">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="adminNav">
                <ul class="navbar-nav ms-auto align-items-md-center">
                    <li class="nav-item"><a class="nav-link <?php echo ($currentPage === 'index.php') ? 'active' : ''; ?>" href="index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($currentPage === 'inquiries.php') ? 'active' : ''; ?>" href="inquiries.php">Inquiries</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($currentPage === 'orders.php') ? 'active' : ''; ?>" href="orders.php">Orders</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($currentPage === 'reservations.php') ? 'active' : ''; ?>" href="reservations.php">Reservations</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($currentPage === 'audit-logs.php') ? 'active' : ''; ?>" href="audit-logs.php">Audit Logs</a></li>
                    <li class="nav-item"><a class="nav-link back-to-site" href="../index.php"><i class="bi bi-box-arrow-left"></i> Back to Site</a></li>
                    <li class="nav-item"><a class="nav-link" href="../logout.php" style="color: #C1694F !important;"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <h1 class="admin-page-title">Customer Inquiries</h1>
        <p class="admin-page-subtitle">Manage and review contact messages sent from the public site.</p>

        <div class="admin-card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table admin-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Subject</th>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && mysqli_num_rows($result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td><?php echo htmlspecialchars($row['subject']); ?></td>
                                    <td class="text-muted text-break" style="max-width: 250px;"><?php echo htmlspecialchars($row['message']); ?></td>
                                    <td>
                                        <?php if ($row['status'] === 'Read'): ?>
                                            <span class="badge bg-secondary">Read</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Unread</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted"><?php echo htmlspecialchars($row['created_at']); ?></td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <!-- Toggle status form -->
                                            <form action="inquiries.php" method="POST" class="d-inline">
                                                <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                    <?php echo $row['status'] === 'Read' ? 'Mark Unread' : 'Mark Read'; ?>
                                                </button>
                                            </form>
                                            <!-- Delete form with confirmation -->
                                            <form action="inquiries.php" method="POST" class="d-inline" onsubmit="return confirm('Delete this inquiry?');">
                                                <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No inquiries found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>