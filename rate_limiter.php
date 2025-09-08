 <?php
// rate_limiter.php - A robust rate-limiting system for login attempts.

// Configuration for rate limiting
const MAX_ATTEMPTS = 3;
const COOLDOWN_SECONDS = 300; // 5 minutes

// ------------------------------------------
// Function to check if a user is rate-limited
// ------------------------------------------
function is_rate_limited(string $ip_address, string $login_input): bool {
    $data_file = 'data/login_attempts.json';

    // Ensure the data directory exists
    if (!is_dir('data')) {
        mkdir('data', 0755, true);
    }
    
    // Check if the data file exists and read its content
    $data = [];
    if (file_exists($data_file)) {
        $data = json_decode(file_get_contents($data_file), true);
        if ($data === null) {
            $data = [];
        }
    }
    
    // Determine the correct key to check
    $key_user = "user_" . md5($login_input);
    $key_ip = "ip_" . $ip_address;
    
    // Check for a specific user lock first
    if (isset($data[$key_user])) {
        if ($data[$key_user]['count'] >= MAX_ATTEMPTS) {
            $last_attempt = $data[$key_user]['last_attempt'];
            if (time() - $last_attempt < COOLDOWN_SECONDS) {
                return true; // The user is locked
            } else {
                // Cooldown period is over, clear the user lock
                unset($data[$key_user]);
                file_put_contents($data_file, json_encode($data, JSON_PRETTY_PRINT));
            }
        }
    }
    
    // Check for an IP-based lock as a fallback (for non-existent users)
    if (isset($data[$key_ip])) {
        if ($data[$key_ip]['count'] >= MAX_ATTEMPTS) {
            $last_attempt = $data[$key_ip]['last_attempt'];
            if (time() - $last_attempt < COOLDOWN_SECONDS) {
                return true; // The IP is locked
            } else {
                // Cooldown period is over, clear the IP lock
                unset($data[$key_ip]);
                file_put_contents($data_file, json_encode($data, JSON_PRETTY_PRINT));
            }
        }
    }
    
    return false;
}

// ------------------------------------------
// Function to increment the failed attempt count
// ------------------------------------------
function increment_rate_limit(string $ip_address, string $login_input): void {
    $data_file = 'data/login_attempts.json';
    
    // Read the current data
    $data = [];
    if (file_exists($data_file)) {
        $data = json_decode(file_get_contents($data_file), true);
        if ($data === null) {
            $data = [];
        }
    }
    
    $is_valid_user = !empty($login_input);

    if ($is_valid_user) {
        $key = "user_" . md5($login_input);
    } else {
        $key = "ip_" . $ip_address;
    }

    // Initialize or increment the count for the determined key
    if (!isset($data[$key])) {
        $data[$key] = [
            'count' => 0,
            'last_attempt' => time()
        ];
    }
    
    $data[$key]['count']++;
    $data[$key]['last_attempt'] = time();
    
    // Write the updated data back to the file
    file_put_contents($data_file, json_encode($data, JSON_PRETTY_PRINT));
}

// ------------------------------------------
// Function to reset the failed attempt count on a successful login
// ------------------------------------------
function reset_rate_limit(string $ip_address, string $login_input): void {
    $data_file = 'data/login_attempts.json';

    // Read the current data
    $data = [];
    if (file_exists($data_file)) {
        $data = json_decode(file_get_contents($data_file), true);
        if ($data === null) {
            return; // No data to reset
        }
    }
    
    // Determine the key and remove it from the data
    $key_user = "user_" . md5($login_input);
    $key_ip = "ip_" . $ip_address;
    
    if (isset($data[$key_user])) {
        unset($data[$key_user]);
    }
    
    if (isset($data[$key_ip])) {
        unset($data[$key_ip]);
    }
    
    // Write the updated data back to the file
    file_put_contents($data_file, json_encode($data, JSON_PRETTY_PRINT));
}

// ------------------------------------------
// New: Function to get a list of locked accounts for the admin panel
// ------------------------------------------
function get_locked_accounts(): array {
    $data_file = 'data/login_attempts.json';
    $locked_accounts = [];

    if (file_exists($data_file)) {
        $data = json_decode(file_get_contents($data_file), true);
        if ($data !== null) {
            foreach ($data as $key => $details) {
                // Check if the account is currently locked out
                if ($details['count'] >= MAX_ATTEMPTS && time() - $details['last_attempt'] < COOLDOWN_SECONDS) {
                    $locked_accounts[] = [
                        'key' => $key,
                        'attempts' => $details['count'],
                        'last_attempt' => date('Y-m-d H:i:s', $details['last_attempt'])
                    ];
                }
            }
        }
    }
    return $locked_accounts;
}

// ------------------------------------------
// New: Function to reset a specific lock
// ------------------------------------------
function reset_specific_lock(string $key_to_reset): bool {
    $data_file = 'data/login_attempts.json';
    
    if (!file_exists($data_file)) {
        return false;
    }
    
    $data = json_decode(file_get_contents($data_file), true);
    if ($data === null) {
        return false;
    }

    if (isset($data[$key_to_reset])) {
        unset($data[$key_to_reset]);
        file_put_contents($data_file, json_encode($data, JSON_PRETTY_PRINT));
        return true;
    }

    return false;
}
?>
