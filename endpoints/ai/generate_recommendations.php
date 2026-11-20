<?php
set_time_limit(300);
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/ssrf_helper.php';
require_once '../../includes/ai_client.php';
require_once '../../includes/ai_subscription_context.php';
require_once '../../includes/subscription_categories.php';

// Load AI settings
$aiSettings = ai_load_settings($db, $userId);

if (!$aiSettings) {
    echo json_encode(["success" => false, "message" => translate('error', $i18n)]);
    exit;
}

// Categories
$stmt = $db->prepare("SELECT * FROM categories WHERE user_id = :user_id");
$stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$categories = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $categories[$row['id']] = $row;
}

$financialContext = ai_user_financial_context($db, $userId);
if ($financialContext === null || $financialContext['currency_code'] === '') {
    echo json_encode(["success" => false, "message" => translate('error', $i18n)]);
    exit;
}
$currencies = $financialContext['currencies'];

// Household members
$stmt = $db->prepare("SELECT * FROM household WHERE user_id = :user_id");
$stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$members = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $members[$row['id']] = $row;
}

// User language
$stmt = $db->prepare("SELECT language FROM user WHERE id = :user_id");
$stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$userLanguage = $result->fetchArray(SQLITE3_ASSOC)['language'] ?? 'en';

// Language name
require_once '../../includes/i18n/languages.php';
$userLanguageName = $languages[$userLanguage]['name'] ?? 'English';

// Subscriptions
$stmt = $db->prepare("SELECT * FROM subscriptions WHERE user_id = :user_id AND inactive = 0");
$stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();

$subscriptions = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $subscriptions[] = $row;
}

if (empty($subscriptions)) {
    echo json_encode(["success" => false, "message" => translate('error', $i18n)]);
    exit;
}

$subscriptionsForAI = [];
$valueFacts = subscription_value_facts_for_subscriptions($db, $userId, $subscriptions);
$costContext = ai_subscription_cost_context(
    $subscriptions,
    $currencies,
    $financialContext['currency_id'],
    $financialContext['currency_rates']
);
$categoriesBySubscription = getCategoriesBySubscription($db, $subscriptions);
$payersBySubscription = getPayersBySubscription($db, $subscriptions);

foreach ($subscriptions as $row) {
    $subscriptionId = (int) $row['id'];
    $facts = $costContext['subscriptions'][$subscriptionId] ?? [];
    $valueFact = $valueFacts[$subscriptionId] ?? [];
    $usageConfidence = (string) ($valueFact['usage_confidence'] ?? 'none');
    $categoryNames = [];
    foreach ($categoriesBySubscription[$subscriptionId] ?? [] as $categoryId) {
        if (isset($categories[$categoryId]['name'])) {
            $categoryNames[] = $categories[$categoryId]['name'];
        }
    }
    $payerNames = [];
    foreach ($payersBySubscription[$subscriptionId] ?? [] as $payerId) {
        if (isset($members[$payerId]['name'])) {
            $payerNames[] = $members[$payerId]['name'];
        }
    }

    $facts['categories'] = $categoryNames;
    $facts['payers'] = $payerNames;
    $facts['usage_seconds_current_billing_period'] = $usageConfidence === 'none'
        ? null
        : (int) ($valueFact['period_usage_seconds'] ?? 0);
    $facts['usage_confidence'] = $usageConfidence;
    $facts['days_until_renewal'] = $valueFact['days_until_renewal'] ?? null;
    $facts['value_assessment'] = $valueFact['value_band'] ?? 'unknown';
    $subscriptionsForAI[] = $facts;
}

$prompt = <<<PROMPT
You are a helpful assistant designed to help users save money on digital subscriptions.

The user's only reporting currency is {$financialContext['currency_code']}. Every monetary amount in your recommendations and savings MUST use {$financialContext['currency_code']}; do not mention or compare any other currency.
Use only the supplied server-calculated prices and totals. Never convert or add prices yourself. If conversion is unavailable or a total is incomplete, do not estimate savings for the affected subscription or claim a complete total.
Monthly and annualized costs are recurring-cost equivalents calculated using Diamon billing-cycle rules. One-time purchases are not recurring costs. A null usage value or confidence "none" means usage is unknown, not zero.

Analyze the data and give 3 to 7 smart and specific recommendations to reduce subscription costs. If possible, include estimated savings for each suggestion.

Follow these guidelines:
- Do NOT suggest switching to family or group plans unless two or more different household members are paying for the same or similar service.
- Recognize known feature overlaps, such as:
• YouTube Premium includes YouTube Music.
• Amazon Prime includes Prime Video.
• Google One, iCloud+, and Proton all offer cloud storage.
• Real Debrid, All Debrid, and Premiumize offer similar download capabilities.
- Suggest rotating or cancelling subscriptions that serve similar purposes (e.g. multiple streaming or IPTV services).
- Recommend switching from monthly to yearly plans only if it provides clear savings and the user is likely to keep the service long-term.
- Suggest looking for promo or new customer deals if a service appears overpriced.
- Only recommend cancelling rarely used services if they do not provide unique value.
- Do not recommend cancelling a subscription whose value assessment is keep.
- Prioritize subscriptions whose value assessment is review.

Return the result as a JSON array. Each item in the array should have:
- "title": a short summary of the suggestion
- "description": a longer explanation with reasoning
- "savings": a rough estimate using only {$financialContext['currency_code']} (if supported by the supplied data)

If possible, all text should be in the user's language: {$userLanguageName}. Otherwise, use English.

Do not include any other text, just the JSON output. Absolutely no additional comments or explanations.

Here is the user's authoritative account data. All monetary values are in {$financialContext['currency_code']}:
PROMPT;

$prompt .= "\n\n" . json_encode([
    'currency' => $financialContext['currency_code'],
    'totals' => $costContext['totals'],
    'budget' => ai_user_budget_context($financialContext),
    'subscriptions' => $subscriptionsForAI,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Run the completion against the configured provider
$aiResult = ai_complete($aiSettings, $prompt, $db, $i18n, $userId);

if (!$aiResult['success']) {
    echo json_encode($aiResult);
    exit;
}

$recommendations = ai_extract_json($aiResult['content']);

if (isset($recommendations['recommendations']) && is_array($recommendations['recommendations'])) {
    $recommendations = $recommendations['recommendations'];
}

if (is_array($recommendations)) {
    $normalizedRecommendations = [];

    foreach ($recommendations as $rec) {
        if (!is_array($rec)) {
            continue;
        }

        $title = $rec['title'] ?? null;
        $description = $rec['description'] ?? null;
        $savings = $rec['savings'] ?? '';

        if (!is_scalar($title) || !is_scalar($description)
            || ($savings !== null && !is_scalar($savings))) {
            continue;
        }

        $title = trim((string) $title);
        $description = trim((string) $description);
        $savings = $savings === null ? '' : trim((string) $savings);

        if ($title === '' || $description === '') {
            continue;
        }

        $normalizedRecommendations[] = [
            'title' => $title,
            'description' => $description,
            'savings' => $savings,
        ];
    }

    $recommendations = $normalizedRecommendations;
}

if (!empty($recommendations)) {
    // Clear old recommendations
    $stmt = $db->prepare("DELETE FROM ai_recommendations WHERE user_id = :user_id");
    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
    $stmt->execute();

    // Insert new recommendations
    $insert = $db->prepare("
        INSERT INTO ai_recommendations (user_id, type, title, description, savings)
        VALUES (:user_id, :type, :title, :description, :savings)
    ");

    foreach ($recommendations as $rec) {
        $insert->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $insert->bindValue(':type', 'subscription', SQLITE3_TEXT);
        $insert->bindValue(':title', $rec['title'] ?? '', SQLITE3_TEXT);
        $insert->bindValue(':description', $rec['description'] ?? '', SQLITE3_TEXT);
        $insert->bindValue(':savings', $rec['savings'] ?? '', SQLITE3_TEXT);
        $insert->execute();
    }

    echo json_encode([
        "success"         => true,
        "message"         => translate('success', $i18n),
        "recommendations" => $recommendations,
    ]);
    exit;
}

$jsonError = json_last_error_msg();
ai_log_failure($aiSettings['type'] ?? '', $userId, 'invalid_completion_json', [
    'response_length' => strlen($aiResult['content']),
    'json_error' => $jsonError,
]);
echo json_encode([
    "success"    => false,
    "message"    => translate('ai_invalid_json_response', $i18n),
    "json_error" => $jsonError,
]);
exit;
