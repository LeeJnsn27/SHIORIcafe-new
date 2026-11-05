<?php
session_start();
require_once "../backend/config/db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // CSRF Check...
    $res_id = $_POST['reservation_id'];

    try {
        $conn->beginTransaction();
        
        $lock_stmt = $conn->prepare("SELECT status FROM shioricafe_schema.reservation WHERE reservation_id = ? FOR UPDATE");
        $lock_stmt->execute([$res_id]);
        
        if ($lock_stmt->fetch(PDO::FETCH_ASSOC)) {
            $upd_stmt = $conn->prepare("UPDATE shioricafe_schema.reservation SET status = 'Occupied', checked_in_at = NOW() WHERE reservation_id = ?");
            $upd_stmt->execute([$res_id]);
        }
        
        $conn->commit();
    } catch (PDOException $e) {
        $conn->rollBack();
    }
}
header("Location: checkin.php");
exit();
?>