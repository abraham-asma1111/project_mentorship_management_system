<?php

// This line assumes your logger.php file is in the same directory.
require_once 'logger.php';

// This is a test message to trigger the file creation.
// Replace 'username' with a real username if you have one.
$message = "Test log event: A user named 'test_user' successfully performed a login.";

// Call the function you created. This will create the security.log file
// if it doesn't already exist and write the message to it.
log_security_event($message);

// This message will be displayed in your browser to confirm the script ran.
echo "Security event logged! You can now check your directory for the 'security.log' file.";

?>
