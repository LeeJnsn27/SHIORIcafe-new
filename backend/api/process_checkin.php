<?php
// backend/api/process-checkin.php - Guest Check-In Backend Handler
header('Content-Type: application/json');

require_once __DIR__ . '/../auth/auth-guard.php';
require_role(['admin', 'cashier']); // Protect endpoint: Staff access only

require_once __DIR__ . '/../config/db.php';
/** @var PDO $pdo */

$db = isset($pdo) ? $pdo : (isset($conn) ? $conn : null);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // 1. Verify CSRF Token
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (empty($submittedToken) || !hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken)) {
        header("Location: /SHIORIcafe/admin/cashier/checkin.php?error=" . urlencode("Invalid security token."));
        exit();
    }

    $res_id = intval($_POST['reservation_id'] ?? 0);

    if ($res_id > 0) {
        try {
            $db->beginTransaction();

            // Row-level lock to prevent concurrent check-ins
            $lock_stmt = $db->prepare("
                SELECT status 
                FROM shioricafe_schema.reservation 
                WHERE reservation_id = :res_id 
                FOR UPDATE
            ");
            $lock_stmt->execute([':res_id' => $res_id]);
            $reservation = $lock_stmt->fetch(PDO::FETCH_ASSOC);

            if ($reservation) {
                // Update reservation status to Occupied and set check-in timestamp
                $upd_stmt = $db->prepare("
                    UPDATE shioricafe_schema.reservation 
                    SET status = 'Occupied', 
                        checked_in_at = CURRENT_TIMESTAMP 
                    WHERE reservation_id = :res_id
                ");
                $upd_stmt->execute([':res_id' => $res_id]);

                $db->commit();
                header("Location: /SHIORIcafe/admin/cashier/checkin.php?msg=" . urlencode("Guest successfully checked in!"));
                exit();
            } else {
                $db->rollBack();
                header("Location: /SHIORIcafe/admin/cashier/checkin.php?error=" . urlencode("Reservation not found."));
                exit();
            }

        } catch (Exception $e) {
            if ($db && $db->inTransaction()) {
                $db->rollBack();
            }
            header("Location: /SHIORIcafe/admin/cashier/checkin.php?error=" . urlencode("Check-in error: " . $e->getMessage()));
            exit();
        }
    }
}

// Fallback redirect
header("Location: /SHIORIcafe/admin/cashier/checkin.php");
exit();