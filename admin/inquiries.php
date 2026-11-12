<?php
// admin/inquiries.php - Manage customer inquiries via PostgreSQL PDO
require_once "../backend/auth/auth-guard.php";
require_once "../backend/config/db.php";
/** @var PDO $pdo */

$currentPage = basename($_SERVER['PHP_SELF']);

// Handle POST actions: toggle status or delete a single inquiry
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'], $_POST['id'])) {
    $inquiryId = (int)$_POST['id'];

    try {
        if ($_POST['action'] === 'toggle_status' || $_POST['action'] === 'mark_read') {
            // Toggles between 'Unread' and 'Resolved' to comply with the database check constraint
            $sql = "UPDATE shioricafe_schema.inquiry 
                    SET status = CASE WHEN status = 'Resolved' THEN 'Unread' ELSE 'Resolved' END 
                    WHERE inquiry_id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $inquiryId]);
        } elseif ($_POST['action'] === 'delete') {
            $sql = "DELETE FROM shioricafe_schema.inquiry WHERE inquiry_id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $inquiryId]);
        }
    } catch (Exception $e) {
        die("Inquiry Update Error: " . $e->getMessage());
    }

    header("Location: inquiries.php");
    exit();
}

// Fetch inquiries using PDO
try {
    $stmt = $pdo->query("SELECT * FROM shioricafe_schema.inquiry ORDER BY created_at DESC"); 
    $inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $inquiries = [];
}
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

    <?php require_once "admin-navbar.php"; ?>

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
                        <?php if (!empty($inquiries)): ?>
                            <?php foreach ($inquiries as $row): 
                                $id = $row['inquiry_id'] ?? 0;
                                $name = $row['name'] ?? $row['customer_name'] ?? 'Guest';
                                $email = $row['email'] ?? 'N/A';
                                $subject = $row['subject'] ?? 'No Subject';
                                $message = $row['message'] ?? '';
                                $status = trim($row['status'] ?? 'Unread');
                                $isResolved = (strcasecmp($status, 'Resolved') === 0);
                                $createdAt = !empty($row['created_at']) ? date('M d, Y h:i A', strtotime($row['created_at'])) : 'N/A';
                            ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($name); ?></td>
                                    <td><?php echo htmlspecialchars($email); ?></td>
                                    <td><?php echo htmlspecialchars($subject); ?></td>
                                    <td class="text-muted text-break" style="max-width: 250px;"><?php echo htmlspecialchars($message); ?></td>
                                    <td>
                                        <?php if ($isResolved): ?>
                                            <span class="badge bg-secondary">Resolved</span>
                                        <?php else: ?>
                                            <span class="badge bg-success"><?php echo htmlspecialchars($status); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted"><?php echo htmlspecialchars($createdAt); ?></td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <form action="inquiries.php" method="POST" class="d-inline">
                                                <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                    <?php echo $isResolved ? 'Mark Unread' : 'Mark Resolved'; ?>
                                                </button>
                                            </form>
                                            <form action="inquiries.php" method="POST" class="d-inline" onsubmit="return confirm('Delete this inquiry?');">
                                                <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
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