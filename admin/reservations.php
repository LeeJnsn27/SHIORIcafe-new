<?php
// admin/reservations.php - Manage reservations via PostgreSQL PDO & Email Notifications
require_once "../backend/auth/auth-guard.php";
require_once "../backend/config/db.php";
/** @var PDO $pdo */

$currentPage = basename($_SERVER['PHP_SELF']);

// Helper function to send email notifications to guests
function sendReservationEmail($toEmail, $guestName, $subject, $messageBody) {
    if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $headers  = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: SHIORI Café <no-reply@shioricafe.com>" . "\r\n";

    $htmlBody = "
    <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #FBF8F3; color: #333;'>
        <h2 style='color: #4A3525;'>栞 SHIORI Café Reservation Update</h2>
        <p>Dear <strong>" . htmlspecialchars($guestName) . "</strong>,</p>
        <p>" . nl2br(htmlspecialchars($messageBody)) . "</p>
        <hr style='border: 0; border-top: 1px solid #ddd; margin: 20px 0;'>
        <p style='font-size: 12px; color: #777;'>If you have any questions, please reply to this email or visit us at 12 Bookmark Lane, San Pedro, Laguna.</p>
    </div>
    ";

    return @mail($toEmail, $subject, $htmlBody, $headers);
}

// Handle Admin Actions (Cancel with reason or Adjust schedule)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'], $_POST['reservation_id'])) {
    $resId = (int)$_POST['reservation_id'];

    try {
        // Fetch guest details before updating to send email
        $infoStmt = $pdo->prepare("
            SELECT r.*, c.full_name as guest_name, c.email as guest_email 
            FROM shioricafe_schema.reservation r 
            LEFT JOIN shioricafe_schema.customer c ON r.customer_id = c.customer_id 
            WHERE r.reservation_id = :id
        ");
        $infoStmt->execute([':id' => $resId]);
        $resData = $infoStmt->fetch(PDO::FETCH_ASSOC);

        $guestEmail = $resData['guest_email'] ?? $resData['email'] ?? '';
        $guestName  = $resData['guest_name'] ?? $resData['name'] ?? 'Valued Guest';

        if ($_POST['action'] === 'cancel_reservation') {
            $reason = trim($_POST['cancellation_reason'] ?? 'No reason provided');

            $stmt = $pdo->prepare("
                UPDATE shioricafe_schema.reservation 
                SET status = 'Cancelled', cancellation_reason = :reason 
                WHERE reservation_id = :id
            ");
            $stmt->execute([':reason' => $reason, ':id' => $resId]);

            // Notify customer via email
            if (!empty($guestEmail)) {
                $subject = "Reservation Cancelled — SHIORI Café";
                $message = "Your study nook reservation (#{$resId}) has been cancelled.\n\nReason: {$reason}";
                sendReservationEmail($guestEmail, $guestName, $subject, $message);
            }

        } elseif ($_POST['action'] === 'adjust_reservation') {
            $newDate = trim($_POST['new_date']);
            $newTime = trim($_POST['new_time']);
            $reason  = trim($_POST['adjustment_reason'] ?? 'Schedule adjusted by admin');

            // Calculate start timestamp and automatically shift end timestamp by +2 hours
            $startTimestamp = strtotime("$newDate $newTime");
            $startAt = date('Y-m-d H:i:s', $startTimestamp);
            $endAt   = date('Y-m-d H:i:s', strtotime("+2 hours", $startTimestamp));

            $stmt = $pdo->prepare("
                UPDATE shioricafe_schema.reservation 
                SET reservation_start_at = :start_at, 
                    reservation_end_at = :end_at, 
                    cancellation_reason = :reason 
                WHERE reservation_id = :id
            ");
            $stmt->execute([
                ':start_at' => $startAt,
                ':end_at'   => $endAt,
                ':reason'   => $reason,
                ':id'       => $resId
            ]);

            // Notify customer via email
            if (!empty($guestEmail)) {
                $formattedNewDate = date('F j, Y', $startTimestamp);
                $formattedNewTime = date('g:i A', $startTimestamp);

                $subject = "Reservation Schedule Updated — SHIORI Café";
                $message = "Your study nook reservation (#{$resId}) schedule has been updated by our staff.\n\nNew Schedule: {$formattedNewDate} at {$formattedNewTime}\nReason: {$reason}";
                sendReservationEmail($guestEmail, $guestName, $subject, $message);
            }
        }
    } catch (Exception $e) {
        die("Adjustment Error: " . $e->getMessage());
    }

    header("Location: reservations.php");
    exit();
}

// Fetch reservations using PDO
try {
    $stmt = $pdo->query("
        SELECT r.*, c.full_name as guest_name, c.email as guest_email 
        FROM shioricafe_schema.reservation r 
        LEFT JOIN shioricafe_schema.customer c ON r.customer_id = c.customer_id 
        ORDER BY r.reservation_start_at DESC
    "); 
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    try {
        $stmt = $pdo->query("SELECT * FROM shioricafe_schema.reservation ORDER BY reservation_start_at DESC");
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $ex) {
        $reservations = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SHIORI Café Admin | Reservations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../frontend/assets/css/admin.css">
</head>
<body>

    <?php require_once "admin-navbar.php"; ?>

    <div class="container py-5">
        <h1 class="admin-page-title">Study Nook Reservations Control</h1>
        <p class="admin-page-subtitle">Review bookings, adjust schedules, or log cancellation feedback.</p>

        <div class="admin-card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table admin-table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nook Details</th>
                            <th>Guest Info</th>
                            <th>Date & Time</th>
                            <th>Status & Notes</th>
                            <th class="text-end">Admin Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($reservations)): ?>
                            <?php foreach ($reservations as $reservation): 
                                $id = $reservation['reservation_id'] ?? 0;
                                $tableId = $reservation['table_id'] ?? '1';
                                $nookName = $reservation['nook_name'] ?? ("Table / Nook #" . $tableId);
                                $nookType = $reservation['nook_type'] ?? 'Standard Nook';
                                $guestName = $reservation['guest_name'] ?? 'Guest';
                                $guestEmail = $reservation['guest_email'] ?? '';
                                
                                $startAt = $reservation['reservation_start_at'] ?? null;
                                $resDate = $startAt ? date('Y-m-d', strtotime($startAt)) : '';
                                $resTime = $startAt ? date('H:i', strtotime($startAt)) : '';
                                $displayTime = $startAt ? date('h:i A', strtotime($startAt)) : '';
                                
                                $status = $reservation['status'] ?? 'Confirmed';
                                $reason = $reservation['cancellation_reason'] ?? '';
                            ?>
                                <tr>
                                    <td><strong>#<?php echo $id; ?></strong></td>
                                    <td>
                                        <div class="nook-name fw-bold"><?php echo htmlspecialchars($nookName); ?></div>
                                        <div class="nook-type text-muted small"><?php echo htmlspecialchars($nookType); ?></div>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($guestName); ?></div>
                                        <div class="guest-email text-muted small"><?php echo htmlspecialchars($guestEmail); ?></div>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($resDate); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($displayTime); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo ($status === 'Cancelled') ? 'bg-danger' : 'bg-warning text-dark'; ?>">
                                            <?php echo htmlspecialchars($status); ?>
                                        </span>
                                        <?php if (!empty($reason)): ?>
                                            <div class="text-muted small mt-1 fst-italic">Note: "<?php echo htmlspecialchars($reason); ?>"</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#adjustModal<?php echo $id; ?>">
                                                Adjust
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal<?php echo $id; ?>">
                                                Cancel
                                            </button>
                                        </div>

                                        <!-- Cancel Modal -->
                                        <div class="modal fade text-start" id="cancelModal<?php echo $id; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form action="reservations.php" method="POST">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Cancel Reservation #<?php echo $id; ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="reservation_id" value="<?php echo $id; ?>">
                                                            <input type="hidden" name="action" value="cancel_reservation">
                                                            <div class="mb-3">
                                                                <label class="form-label">Why is this being cancelled?</label>
                                                                <textarea class="form-control" name="cancellation_reason" rows="3" placeholder="e.g., Customer requested via chat / No show / Maintenance" required></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                                            <button type="submit" class="btn btn-danger btn-sm">Confirm Cancellation</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Adjust Schedule Modal -->
                                        <div class="modal fade text-start" id="adjustModal<?php echo $id; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form action="reservations.php" method="POST">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Adjust Schedule for #<?php echo $id; ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="reservation_id" value="<?php echo $id; ?>">
                                                            <input type="hidden" name="action" value="adjust_reservation">
                                                            <div class="mb-3">
                                                                <label class="form-label">New Date</label>
                                                                <input type="date" class="form-control" name="new_date" value="<?php echo htmlspecialchars($resDate); ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">New Time</label>
                                                                <input type="time" class="form-control" name="new_time" value="<?php echo htmlspecialchars($resTime); ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Reason for Adjustment</label>
                                                                <textarea class="form-control" name="adjustment_reason" rows="2" placeholder="e.g., Customer requested time change" required></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                                            <button type="submit" class="btn btn-success btn-sm">Save Adjustments</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No reservations found in the database.</td>
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