</main>

<?php
$minibotShow = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true && !empty($userId);
$minibotAiReady = false;
if ($minibotShow) {
  // Some pages close $db before the footer, so open a short-lived connection here.
  $minibotDb = null;
  try {
    $minibotDb = new SQLite3(__DIR__ . '/../db/wallos.db');
    $minibotDb->busyTimeout(2000);
    $minibotAiStmt = $minibotDb->prepare('SELECT enabled, model FROM ai_settings WHERE user_id = :userId LIMIT 1');
    if ($minibotAiStmt) {
      $minibotAiStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
      $minibotAiResult = $minibotAiStmt->execute();
      $minibotAiRow = $minibotAiResult ? $minibotAiResult->fetchArray(SQLITE3_ASSOC) : false;
      $minibotAiReady = $minibotAiRow
        && !empty($minibotAiRow['enabled'])
        && trim((string) ($minibotAiRow['model'] ?? '')) !== '';
    }
  } catch (Throwable $minibotError) {
    $minibotAiReady = false;
  }
  if ($minibotDb instanceof SQLite3) {
    $minibotDb->close();
  }
}
if ($minibotShow) {
  require_once __DIR__ . '/dia_mascot.php';
  $minibotMood = $minibotAiReady ? 'idle' : 'sleeping';
?>

<div class="diamon-minibot<?= $minibotAiReady ? '' : ' is-disabled' ?>" id="diamon-minibot"
  data-user-id="<?= (int) $userId ?>"
  data-ai-ready="<?= $minibotAiReady ? '1' : '0' ?>"
  data-welcome="<?= htmlspecialchars(translate('minibot_welcome', $i18n), ENT_QUOTES, 'UTF-8') ?>"
  data-thinking="<?= htmlspecialchars(translate('minibot_thinking', $i18n), ENT_QUOTES, 'UTF-8') ?>"
  data-error="<?= htmlspecialchars(translate('minibot_error', $i18n), ENT_QUOTES, 'UTF-8') ?>"
  data-disabled="<?= htmlspecialchars(translate('minibot_disabled', $i18n), ENT_QUOTES, 'UTF-8') ?>">
  <div class="diamon-minibot-panel" id="diamon-minibot-panel" hidden>
    <div class="diamon-minibot-header">
      <span class="diamon-minibot-avatar">
        <?= dia_mascot_svg(32, $minibotMood, 'dia-mood') ?>
      </span>
      <span class="diamon-minibot-heading">
        <span class="diamon-minibot-name"><?= translate('minibot_title', $i18n) ?></span>
        <span class="diamon-minibot-subtitle"><?= translate('minibot_subtitle', $i18n) ?></span>
      </span>
      <div class="diamon-minibot-header-actions">
        <button type="button" class="diamon-minibot-clear" id="diamon-minibot-clear"
          title="<?= translate('minibot_clear', $i18n) ?>"
          aria-label="<?= translate('minibot_clear', $i18n) ?>">
          <i class="fa-solid fa-eraser"></i>
        </button>
        <button type="button" class="diamon-minibot-close" id="diamon-minibot-close" aria-label="<?= translate('minibot_close', $i18n) ?>"
          title="<?= translate('minibot_close', $i18n) ?>">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>
    </div>
    <div class="diamon-minibot-messages" id="diamon-minibot-messages" aria-live="polite"></div>
    <?php if ($minibotAiReady) { ?>
      <form class="diamon-minibot-composer" id="diamon-minibot-form">
        <label for="diamon-minibot-input" class="sr-only"><?= translate('minibot_message_label', $i18n) ?></label>
        <input type="text" id="diamon-minibot-input" maxlength="1000" autocomplete="off"
          placeholder="<?= htmlspecialchars(translate('minibot_placeholder', $i18n), ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" class="diamon-minibot-send" aria-label="<?= translate('minibot_send', $i18n) ?>">
          <i class="fa-solid fa-paper-plane"></i>
        </button>
      </form>
    <?php } else { ?>
      <div class="diamon-minibot-disabled">
        <p><?= translate('minibot_disabled', $i18n) ?></p>
        <a class="button thin" href="settings.php"><?= translate('minibot_open_settings', $i18n) ?></a>
      </div>
    <?php } ?>
  </div>
  <div class="diamon-minibot-launcher">
    <button type="button" class="diamon-minibot-fab" id="diamon-minibot-fab"
      aria-expanded="false" aria-controls="diamon-minibot-panel"
      aria-label="<?= htmlspecialchars(translate('minibot_open', $i18n), ENT_QUOTES, 'UTF-8') ?>"
      data-label-open="<?= htmlspecialchars(translate('minibot_open', $i18n), ENT_QUOTES, 'UTF-8') ?>"
      data-label-close="<?= htmlspecialchars(translate('minibot_close', $i18n), ENT_QUOTES, 'UTF-8') ?>"
      title="<?= translate('minibot_title', $i18n) ?>">
      <?= dia_mascot_svg(64, $minibotMood, 'dia-mood dia-follows has-shadow') ?>
    </button>
    <?php if ($minibotAiReady) { ?>
      <button type="button" class="diamon-minibot-greeting" id="diamon-minibot-greeting" hidden>
        <?= translate('minibot_greeting', $i18n) ?>
      </button>
    <?php } ?>
  </div>
  <template id="diamon-minibot-avatar-template">
    <?= dia_mascot_svg(26, 'idle', 'diamon-minibot-bubble-avatar dia-static') ?>
  </template>
</div>
<script type="text/javascript" src="scripts/minibot.js?<?= $version ?>"></script>
<?php } ?>

<div class="toast" id="errorToast">
  <div class="toast-content">
    <i class="fas fa-solid fa-x toast-icon error"></i>
    <div class="message">
      <span class="text text-1"><?= translate("error", $i18n) ?></span>
      <span class="text text-2 errorMessage"></span>
    </div>
  </div>
  <i class="fa-solid fa-xmark close close-error"></i>
  <div class="progress error"></div>
</div>

<div class="toast" id="successToast">
  <div class="toast-content">
    <i class="fas fa-solid fa-check toast-icon success"></i>
    <div class="message">
      <span class="text text-1"><?= translate("success", $i18n) ?></span>
      <span class="text text-2 successMessage"></span>
    </div>
  </div>
  <i class="fa-solid fa-xmark close close-success"></i>
  <div class="progress success"></div>
</div>

<?php
if (isset($db)) {
  $db->close();
}
?>

</body>

</html>