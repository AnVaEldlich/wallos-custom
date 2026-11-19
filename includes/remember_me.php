<?php

if (!defined('DIAMON_LOGIN_COOKIE')) {
    define('DIAMON_LOGIN_COOKIE', 'diamon_login');
    // The name the cookie had before the project was renamed from Wallos. It is
    // still read, so "stay logged in" sessions survive the upgrade, and is
    // swapped for the current name the first time it is used.
    define('DIAMON_LEGACY_LOGIN_COOKIE', 'wallos_login');
}

/**
 * The raw remember-me cookie, under its current or its pre-rename name.
 *
 * @return string|null
 */
function diamon_login_cookie_value()
{
    if (isset($_COOKIE[DIAMON_LOGIN_COOKIE])) {
        return $_COOKIE[DIAMON_LOGIN_COOKIE];
    }

    if (isset($_COOKIE[DIAMON_LEGACY_LOGIN_COOKIE])) {
        return $_COOKIE[DIAMON_LEGACY_LOGIN_COOKIE];
    }

    return null;
}

/**
 * Re-issues a cookie that only exists under the pre-rename name.
 */
function diamon_migrate_legacy_login_cookie()
{
    if (isset($_COOKIE[DIAMON_LOGIN_COOKIE]) || !isset($_COOKIE[DIAMON_LEGACY_LOGIN_COOKIE]) || headers_sent()) {
        return;
    }

    setcookie(DIAMON_LOGIN_COOKIE, $_COOKIE[DIAMON_LEGACY_LOGIN_COOKIE], [
        'expires' => time() + (30 * 24 * 60 * 60),
        'samesite' => 'Lax',
        'httponly' => true,
    ]);
    setcookie(DIAMON_LEGACY_LOGIN_COOKIE, '', time() - 3600);
}

/**
 * Attempts to restore a logged-in session from the persistent "remember me"
 * login cookie (set at login when "stay logged in" is checked).
 *
 * PHP's session data can be garbage-collected (default ~24 minutes of
 * inactivity) long before the remember-me cookie's 30-day lifetime expires.
 * Full page loads recover from this transparently; this function lets
 * AJAX/API endpoints (via connect_endpoint.php) do the same instead of
 * silently behaving as logged-out after an idle period.
 *
 * On success, populates $_SESSION (regenerating the session id) and
 * returns the user's row. On any failure, returns false and leaves
 * $_SESSION untouched.
 */
function restoreSessionFromRememberMeCookie($db)
{
    $cookieValue = diamon_login_cookie_value();
    if ($cookieValue === null) {
        return false;
    }

    $cookie = explode('|', $cookieValue, 3);
    if (count($cookie) !== 3) {
        return false;
    }
    [$username, $token, $main_currency] = $cookie;

    $sql = "SELECT * FROM user WHERE username = :username";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $result = $stmt->execute();

    if (!$result) {
        return false;
    }

    $userData = $result->fetchArray(SQLITE3_ASSOC);
    if (!isset($userData['id'])) {
        return false;
    }

    $userId = $userData['id'];
    $main_currency = $userData['main_currency'];

    $adminQuery = "SELECT login_disabled FROM admin";
    $adminResult = $db->query($adminQuery);
    $adminRow = $adminResult->fetchArray(SQLITE3_ASSOC);

    if ($adminRow['login_disabled'] == 1) {
        $sql = "SELECT * FROM login_tokens WHERE user_id = :userId";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':userId', $userId, SQLITE3_TEXT);
    } else {
        $sql = "SELECT * FROM login_tokens WHERE user_id = :userId AND token = :token";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':userId', $userId, SQLITE3_TEXT);
        $stmt->bindParam(':token', $token, SQLITE3_TEXT);
    }
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);

    if ($row == false) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['username'] = $username;
    $_SESSION['token'] = $token;
    $_SESSION['loggedin'] = true;
    $_SESSION['main_currency'] = $main_currency;
    $_SESSION['userId'] = $userId;

    diamon_migrate_legacy_login_cookie();

    return $userData;
}

?>
