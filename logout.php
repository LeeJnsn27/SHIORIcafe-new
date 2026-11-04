<?php

// Start the session so we can access and destroy it
session_start();

// Remove all session variables (clears out user_id, user_name, user_role, etc.)
session_unset();

session_destroy();
header("Location: login.php");
exit();
