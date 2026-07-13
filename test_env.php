<?php
// test_env.php - Safe environment variable diagnostic utility
header("Content-Type: text/plain");

echo "DB_HOST: " . (getenv('DB_HOST') ?: 'NOT SET') . "\n";
echo "DB_PORT: " . (getenv('DB_PORT') ?: 'NOT SET') . "\n";
echo "DB_NAME: " . (getenv('DB_NAME') ?: 'NOT SET') . "\n";
echo "DB_USER: " . (getenv('DB_USER') ?: 'NOT SET') . "\n";

$pass = getenv('DB_PASS');
if ($pass === false) {
    echo "DB_PASS: NOT SET (false)\n";
} elseif ($pass === '') {
    echo "DB_PASS: EMPTY STRING\n";
} else {
    // Obfuscate the password but show length and boundary characters for diagnostic validation
    echo "DB_PASS: SET (Length: " . strlen($pass) . ", Prefix: '" . substr($pass, 0, 5) . "', Suffix: '" . substr($pass, -5) . "')\n";
}
