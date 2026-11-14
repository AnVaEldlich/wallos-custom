<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/default_names.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);
$language = isset($data['language']) ? (string) $data['language'] : '';

if (!isset($languages[$language])) {
    die(json_encode([
        "success" => false,
        "message" => translate('error', $i18n)
    ]));
}

$stmt = $db->prepare('SELECT language FROM user WHERE id = :userId');
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
$storedLanguage = $row['language'] ?? 'en';

$stmt = $db->prepare('UPDATE user SET language = :language WHERE id = :userId');
$stmt->bindValue(':language', $language, SQLITE3_TEXT);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);

if (!$stmt->execute()) {
    die(json_encode([
        "success" => false,
        "message" => translate('error', $i18n)
    ]));
}

$cookieExpire = time() + (30 * 24 * 60 * 60);
$oldLanguage = isset($_COOKIE['language']) ? resolve_language($_COOKIE['language']) : resolve_language($storedLanguage);
$root = str_replace('/endpoints/settings', '', dirname($_SERVER['PHP_SELF']));
$root = $root == '' ? '/' : $root;
setcookie('language', $language, [
    'path' => $root,
    'expires' => $cookieExpire,
    'samesite' => 'Lax'
]);

localize_default_names_on_language_change($db, $userId, $storedLanguage, $language);

echo json_encode([
    "success" => true,
    "message" => translate('success', $i18n),
    "reload" => $oldLanguage !== $language
]);
