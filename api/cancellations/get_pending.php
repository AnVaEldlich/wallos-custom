<?php
/*
  For the Diamon browser extension: the cancellation the user is in the middle
  of, with the hosts and URL patterns to watch and the confirmation phrases to
  look for. Authenticated with the user's API key, like api/usage/*.
*/

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/usage_api.php';
require_once '../../includes/cancellation.php';

header('Content-Type: application/json; charset=UTF-8');

[$userId, $payload, $error] = usage_api_user($db);
if ($error !== null) {
    echo json_encode($error);
    exit;
}

echo json_encode([
    'success' => true,
    'title' => 'pending cancellations',
    'cancellations' => cancellation_watch_list($db, $userId),
    'success_phrases' => provider_cancellation_success_phrases(),
]);
