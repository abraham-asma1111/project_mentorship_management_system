 <?php

// Function to log a security event to a file (optional, but good practice)
function log_security_event($message) {
    $log_file = 'security.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[{$timestamp}] {$message}" . PHP_EOL, FILE_APPEND);
}

// Function to log a failed login attempt to the database
// We now pass the user_id if the user exists but the password was wrong.
// If the user doesn't exist, we pass NULL for user_id.
function log_failed_attempt_to_db($username_or_email, $pdo, $user_id = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    
    // The created_at timestamp will be handled automatically by the database.
    // The `user_id` is passed directly; if it's null, PDO will insert NULL.
    $stmt = $pdo->prepare("INSERT INTO failed_logins (user_id, username, ip_address)
                           VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $username_or_email, $ip_address]);
}

?>
