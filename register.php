 <?php
// register.php - Displays the registration form
require_once 'config.php'; // For $pdo and session_start()

// Check for messages from process_registration.php (after a form submission attempt)
$message = $_SESSION['registration_message'] ?? '';
unset($_SESSION['registration_message']); // Clear message after displaying

// Retain form data if validation failed in process_registration.php
$old_first_name = $_SESSION['old_registration_data']['first_name'] ?? '';
$old_last_name = $_SESSION['old_registration_data']['last_name'] ?? '';
$old_username = $_SESSION['old_registration_data']['username'] ?? '';
$old_email = $_SESSION['old_registration_data']['email'] ?? '';
// Set 'Mentee' as the default role if no previous role was selected
$old_role_name = $_SESSION['old_registration_data']['role'] ?? 'Mentee';
unset($_SESSION['old_registration_data']); // Clear old data

// The list of roles is hardcoded to match the database values
$roles = ['Mentee', 'Mentor','Coordinator'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMMS - Register</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-br from-[#1E293B] to-[#0F172A] flex items-center justify-center min-h-screen p-4">
    <div class="bg-white bg-opacity-5 backdrop-blur-lg p-8 md:p-12 rounded-xl shadow-2xl w-full max-w-md mx-auto transform transition duration-500 hover:scale-105">
        <h2 class="text-3xl font-bold text-center text-white mb-6">Register for PMMS</h2>

        <?php if (!empty($message)) echo '<div class="text-center mb-4">' . $message . '</div>'; ?>

        <form action="process_registration.php" method="POST" class="space-y-6">
            <div class="form-group">
                <label for="first_name" class="block text-sm font-medium text-gray-200">First Name:</label>
                <input type="text" id="first_name" name="first_name" required value="<?php echo htmlspecialchars($old_first_name); ?>"
                       class="mt-1 block w-full p-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 transition duration-300">
            </div>
            <div class="form-group">
                <label for="last_name" class="block text-sm font-medium text-gray-200">Last Name:</label>
                <input type="text" id="last_name" name="last_name" required value="<?php echo htmlspecialchars($old_last_name); ?>"
                       class="mt-1 block w-full p-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 transition duration-300">
            </div>
            <div class="form-group">
                <label for="username" class="block text-sm font-medium text-gray-200">Username:</label>
                <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($old_username); ?>"
                       class="mt-1 block w-full p-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 transition duration-300">
            </div>
            <div class="form-group">
                <label for="email" class="block text-sm font-medium text-gray-200">Email:</label>
                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($old_email); ?>"
                       class="mt-1 block w-full p-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 transition duration-300">
            </div>
            <div class="form-group">
                <label for="password" class="block text-sm font-medium text-gray-200">Password:</label>
                <input type="password" id="password" name="password" required oninput="validatePassword()"
                       class="mt-1 block w-full p-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 transition duration-300">
                <div id="password-validation" class="text-xs mt-1 text-gray-300"></div>
            </div>
            <div class="form-group">
                <label for="confirm_password" class="block text-sm font-medium text-gray-200">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required oninput="validatePassword()"
                       class="mt-1 block w-full p-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 transition duration-300">
                <div id="confirm-password-validation" class="text-xs mt-1 text-gray-300"></div>
            </div>
            <div class="form-group">
                <label for="role" class="block text-sm font-medium text-gray-200">Register as:</label>
                <select id="role" name="role" class="mt-1 block w-full p-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition duration-300">
                    <?php foreach ($roles as $role_option) : ?>
                        <option value="<?php echo htmlspecialchars($role_option); ?>" <?php echo ($role_option === $old_role_name) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($role_option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div> 
            <button type="submit"
                    class="w-full py-3 px-4 bg-blue-600 text-white font-semibold rounded-lg shadow-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-300 ease-in-out transform hover:scale-105">
                Register
            </button>
        </form>
        <div class="mt-8 text-center text-gray-300">
            Already have an account? <a href="auth_portal.php" class="font-medium text-blue-400 hover:text-blue-200 transition duration-300 ease-in-out">Login here</a>.
        </div>
    </div>

    <script>
        function validatePassword() {
            const password = document.getElementById('password').value;
            const confirm_password = document.getElementById('confirm_password').value;
            const passwordValidationDiv = document.getElementById('password-validation');
            const confirmPasswordValidationDiv = document.getElementById('confirm-password-validation');

            const passwordRegex = /^(?=.*[a-zA-Z])(?=.*\d)(?=.*\W).{8,}$/;
            const isPasswordValid = passwordRegex.test(password);

            const validClass = 'text-green-400';
            const invalidClass = 'text-red-400';

            // Display password validation message
            if (password === '') {
                passwordValidationDiv.textContent = '';
                passwordValidationDiv.className = 'text-xs mt-1 text-gray-300';
            } else if (isPasswordValid) {
                passwordValidationDiv.textContent = 'Password meets requirements.';
                passwordValidationDiv.className = `text-xs mt-1 ${validClass}`;
            } else {
                passwordValidationDiv.textContent = 'Password must be at least 8 chars, 1 letter, 1 number, 1 special character.';
                passwordValidationDiv.className = `text-xs mt-1 ${invalidClass}`;
            }

            // Display confirm password validation message
            if (confirm_password === '') {
                confirmPasswordValidationDiv.textContent = '';
                confirmPasswordValidationDiv.className = 'text-xs mt-1 text-gray-300';
            } else if (password === confirm_password) {
                confirmPasswordValidationDiv.textContent = 'Passwords match.';
                confirmPasswordValidationDiv.className = `text-xs mt-1 ${validClass}`;
            } else {
                confirmPasswordValidationDiv.textContent = 'Passwords do not match.';
                confirmPasswordValidationDiv.className = `text-xs mt-1 ${invalidClass}`;
            }
        }
    </script>
</body>
</html>
