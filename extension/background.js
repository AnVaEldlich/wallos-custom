importScripts('tracking.js', 'cancellation.js');

const WATCHLIST_TTL_MS = 3 * 60 * 60 * 1000;

const HEARTBEAT_MS = 1000;

const IDLE_AFTER_SECONDS = 180;

const MAX_SESSION_GAP_SECONDS = 180;

const SESSION_KEY = 'currentSession';

let watchlist = [];
let watchlistAt = 0;
let current = null;
let tickInFlight = false;
let heartbeatTimer = null;

// Versions released before the project was renamed to Diamon saved the server
// address as "wallosUrl". It is still read, and moved to the new key on first use,
// so an upgraded extension stays connected without being set up again.
const LEGACY_URL_KEY = 'wallosUrl';

async function diamonConfig() {
  const stored = await chrome.storage.local.get({ diamonUrl: '', [LEGACY_URL_KEY]: '', apiKey: '' });
  if (!stored.diamonUrl && stored[LEGACY_URL_KEY]) {
    stored.diamonUrl = stored[LEGACY_URL_KEY];
    await chrome.storage.local.set({ diamonUrl: stored.diamonUrl });
    await chrome.storage.local.remove(LEGACY_URL_KEY);
  }
  return { diamonUrl: stored.diamonUrl, apiKey: stored.apiKey };
}

async function refreshWatchlist(force) {
  const { diamonUrl, apiKey } = await diamonConfig();
  if (!diamonUrl || !apiKey) {
    watchlist = [];
    return;
  }
  if (!force && watchlistAt && Date.now() - watchlistAt < WATCHLIST_TTL_MS) {
    return;
  }
  const url = diamonUrl.replace(/\/$/, '') + '/api/usage/get_watchlist.php?api_key=' + encodeURIComponent(apiKey);
  const response = await fetch(url);
  const data = await response.json();
  if (data.success) {
    watchlist = data.subscriptions || [];
    watchlistAt = Date.now();
  }
}

async function focusedHostname() {
  const [tab] = await chrome.tabs.query({ active: true, lastFocusedWindow: true });
  if (!tab || !tab.url) {
    return null;
  }
  return hostnameFromUrl(tab.url);
}

async function audibleTabs() {
  const tabs = await chrome.tabs.query({ audible: true });
  return tabs
    .map((tab) => ({
      tabId: tab.id,
      hostname: hostnameFromUrl(tab.url),
    }))
    .filter((tab) => tab.hostname);
}

async function resolveTrackedSubscription() {
  const idleState = await chrome.idle.queryState(IDLE_AFTER_SECONDS);
  const focused = await focusedHostname();
  const audible = await audibleTabs();
  return resolveTrackedMatch({
    idleState,
    focusedHostname: focused,
    audibleTabs: audible,
    watchlist,
  });
}

async function restoreCurrent() {
  if (current) {
    return;
  }
  const stored = await chrome.storage.session.get(SESSION_KEY);
  current = stored[SESSION_KEY] || null;
}

async function persistCurrent() {
  if (!current) {
    await chrome.storage.session.remove(SESSION_KEY);
    return;
  }
  await chrome.storage.session.set({ [SESSION_KEY]: current });
}

async function flushCurrent(now, isActive) {
  if (!current) {
    return;
  }
  const { diamonUrl, apiKey } = await diamonConfig();
  if (!diamonUrl || !apiKey) {
    return;
  }
  await fetch(diamonUrl.replace(/\/$/, '') + '/api/usage/add_usage.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      api_key: apiKey,
      id: current.sessionId,
      subscription_id: current.subscriptionId,
      started_at: current.startedAt,
      ended_at: now,
      source: 'extension',
      device: navigator.platform || '',
      is_active: isActive,
    }),
  });
}

async function tick() {
  if (tickInFlight) {
    return;
  }
  tickInFlight = true;
  try {
    await refreshWatchlist(false);
    await restoreCurrent();
    const now = Math.floor(Date.now() / 1000);
    const match = await resolveTrackedSubscription();
    const sameVisit = match
      && current
      && current.subscriptionId === match.id
      && sessionCanContinue(current.lastSeen, now, MAX_SESSION_GAP_SECONDS);

    if (!sameVisit && current) {
      await flushCurrent(sessionEndedAt(current.lastSeen, now, MAX_SESSION_GAP_SECONDS), false);
      current = null;
      await persistCurrent();
    }
    if (!match) {
      return;
    }
    if (!current) {
      current = {
        subscriptionId: match.id,
        sessionId: crypto.randomUUID(),
        startedAt: now,
        lastSeen: now,
      };
      await persistCurrent();
      await flushCurrent(now, true);
      return;
    }
    current.lastSeen = now;
    await persistCurrent();
    await flushCurrent(now, true);
  } catch (error) {
    console.error(error);
  } finally {
    tickInFlight = false;
  }
}

// ---------------------------------------------------------------------------
// Assisted cancellation: while the user is cancelling a subscription that
// Diamon sent them to, watch that service's tabs for its confirmation page and
// tell Diamon, so the subscription is marked cancelled without asking.

// Poll often only while a cancellation is open; otherwise just check now and
// then whether the user started one.
const CANCELLATION_POLL_MS = 5000;
const CANCELLATION_IDLE_POLL_MS = 30000;
const CANCELLATION_TEXT_LIMIT = 200000;

let pendingCancellations = [];
let cancellationPhrases = [];
let cancellationPolledAt = 0;
let cancellationCheckInFlight = false;

async function refreshPendingCancellations() {
  const { diamonUrl, apiKey } = await diamonConfig();
  if (!diamonUrl || !apiKey) {
    pendingCancellations = [];
    return;
  }
  const url = diamonUrl.replace(/\/$/, '') + '/api/cancellations/get_pending.php?api_key=' + encodeURIComponent(apiKey);
  const response = await fetch(url);
  const data = await response.json();
  if (data.success) {
    pendingCancellations = data.cancellations || [];
    cancellationPhrases = data.success_phrases || [];
  }
}

async function reportCancellationDetected(item) {
  const { diamonUrl, apiKey } = await diamonConfig();
  if (!diamonUrl || !apiKey) {
    return;
  }
  await fetch(diamonUrl.replace(/\/$/, '') + '/api/cancellations/report_detected.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ api_key: apiKey, attempt_id: item.attempt_id }),
  });
  pendingCancellations = pendingCancellations.filter((other) => other.attempt_id !== item.attempt_id);
}

async function tabPageText(tabId) {
  try {
    const [injection] = await chrome.scripting.executeScript({
      target: { tabId },
      func: (limit) => (document.body ? document.body.innerText.slice(0, limit) : ''),
      args: [CANCELLATION_TEXT_LIMIT],
    });
    return injection && typeof injection.result === 'string' ? injection.result : '';
  } catch (error) {
    // Pages the extension may not script (chrome://, the Web Store, a tab
    // that is still loading) just do not count as a confirmation.
    return '';
  }
}

async function checkTabForCancellation(tab) {
  if (!tab || !tab.url) {
    return;
  }
  const items = cancellationsForUrl(tab.url, pendingCancellations);
  if (items.length !== 1) {
    // None, or two attempts on the same service: nothing to attribute safely.
    return;
  }
  const item = items[0];
  if (cancellationUrlConfirms(tab.url, item)) {
    await reportCancellationDetected(item);
    return;
  }
  const text = await tabPageText(tab.id);
  if (cancellationTextConfirms(text, cancellationPhrases)) {
    await reportCancellationDetected(item);
  }
}

async function checkCancellations(force) {
  if (cancellationCheckInFlight) {
    return;
  }
  const interval = pendingCancellations.length > 0 ? CANCELLATION_POLL_MS : CANCELLATION_IDLE_POLL_MS;
  if (!force && Date.now() - cancellationPolledAt < interval) {
    return;
  }
  cancellationCheckInFlight = true;
  cancellationPolledAt = Date.now();
  try {
    await refreshPendingCancellations();
    if (pendingCancellations.length === 0) {
      return;
    }
    const tabs = await chrome.tabs.query({});
    for (const tab of tabs) {
      if (pendingCancellations.length === 0) {
        break;
      }
      await checkTabForCancellation(tab);
    }
  } catch (error) {
    console.error(error);
  } finally {
    cancellationCheckInFlight = false;
  }
}

function startHeartbeat() {
  if (heartbeatTimer != null) {
    return;
  }
  heartbeatTimer = setInterval(() => {
    tick();
    checkCancellations(false);
  }, HEARTBEAT_MS);
}

function wakeAndTick() {
  startHeartbeat();
  tick();
  checkCancellations(false);
}

chrome.runtime.onInstalled.addListener(() => {
  // Alarm keeps the service worker waking; the real heartbeat is 1s via setInterval.
  chrome.alarms.create('diamon-usage', { periodInMinutes: 1 });
  wakeAndTick();
});
chrome.runtime.onStartup.addListener(() => wakeAndTick());
chrome.alarms.onAlarm.addListener((alarm) => {
  if (alarm.name === 'diamon-usage') {
    wakeAndTick();
  }
});
chrome.tabs.onActivated.addListener(() => wakeAndTick());
chrome.tabs.onUpdated.addListener((tabId, changeInfo, tab) => {
  if (changeInfo.url || typeof changeInfo.audible === 'boolean') {
    wakeAndTick();
  }
  // A service's confirmation page just finished loading: check it right away
  // against the attempts already known, without waiting for the next poll.
  if (changeInfo.status === 'complete' && pendingCancellations.length > 0) {
    checkTabForCancellation(tab).catch((error) => console.error(error));
  }
});
chrome.windows.onFocusChanged.addListener(() => wakeAndTick());
chrome.idle.onStateChanged.addListener(() => wakeAndTick());
