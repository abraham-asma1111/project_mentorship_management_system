<?php
// admin_dashboard.php - Admin-only dashboard to manage locked accounts.

session_start();

// ------------------------------------------
// Include necessary files
// ------------------------------------------
require_once 'config.php';
require_once 'rate_limiter.php'; // New file for rate limiting
require_once 'logger.php';

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Administrator') {
    $_SESSION['auth_message'] = "You do not have permission to access this page.";
    redirect('auth_portal.php');
    exit();
}

$message = '';

// Check for a form submission to reset a lock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_key'])) {
    $reset_key = trim($_POST['reset_key']);
    if (reset_specific_lock($reset_key)) {
        $message = "Lock for key '{$reset_key}' has been successfully reset.";
        log_security_event("Admin '{$_SESSION['username']}' reset login lock for key: {$reset_key}.");
    } else {
        $message = "Failed to reset lock for key '{$reset_key}'. Key not found or file error.";
        log_security_event("Admin '{$_SESSION['username']}' failed to reset login lock for key: {$reset_key}.");
    }
}

// Get the list of all currently locked accounts
$locked_accounts = get_locked_accounts();

// ------------------------------------------
// HTML section for the Admin Dashboard
// ------------------------------------------
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMMS - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-br from-[#3e227a] to-[#ff33c2] min-h-screen p-8">
    <div class="max-w-4xl mx-auto bg-white bg-opacity-10 backdrop-blur-lg p-8 rounded-xl shadow-2xl text-gray-200">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-white">Admin Dashboard</h1>
            <a href="dashboard.php" class="text-white hover:text-blue-200 transition duration-300 ease-in-out">
                &larr; Back to Dashboard
            </a>
        </div>
        <p class="mb-6 text-gray-300">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?>. This is your admin panel for managing user security.</p>
        
        <?php
        if (!empty($message)) {
            echo '<div class="mb-4 p-3 rounded-lg text-white font-medium bg-green-600 bg-opacity-70">' . htmlspecialchars($message) . '</div>';
        }
        ?>

        <h2 class="text-xl font-semibold text-white mb-4">Locked Accounts</h2>
        <p class="text-gray-300 mb-4">The following accounts have been locked due to too many failed login attempts.</p>

        <?php if (empty($locked_accounts)): ?>
            <div class="bg-gray-700 bg-opacity-50 p-6 rounded-lg text-center">
                <p class="text-gray-400">No accounts are currently locked.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto bg-gray-700 bg-opacity-50 rounded-lg shadow">
                <table class="min-w-full leading-normal">
                    <thead>
                        <tr class="bg-gray-800 text-gray-400 uppercase text-sm">
                            <th class="px-5 py-3 border-b-2 border-gray-600 text-left">Key (User/IP)</th>
                            <th class="px-5 py-3 border-b-2 border-gray-600 text-left">Failed Attempts</th>
                            <th class="px-5 py-3 border-b-2 border-gray-600 text-left">Last Attempt</th>
                            <th class="px-5 py-3 border-b-2 border-gray-600">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($locked_accounts as $account): ?>
                            <tr class="hover:bg-gray-600 transition-colors duration-200">
                                <td class="px-5 py-5 border-b border-gray-700 text-sm"><?php echo htmlspecialchars($account['key']); ?></td>
                                <td class="px-5 py-5 border-b border-gray-700 text-sm"><?php echo htmlspecialchars($account['attempts']); ?></td>
                                <td class="px-5 py-5 border-b border-gray-700 text-sm"><?php echo htmlspecialchars($account['last_attempt']); ?></td>
                                <td class="px-5 py-5 border-b border-gray-700 text-sm text-center">
                                    <form action="admin_dashboard.php" method="POST" onsubmit="return confirm('Are you sure you want to reset this lock?');">
                                        <input type="hidden" name="reset_key" value="<?php echo htmlspecialchars($account['key']); ?>">
                                        <button type="submit" class="bg-green-500 hover:bg-green-600 text-white font-bold py-1 px-3 rounded-lg text-xs transition duration-200">
                                            Reset Lock
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
