importScripts('tracking.js');

const WATCHLIST_TTL_MS = 3 * 60 * 60 * 1000;

const HEARTBEAT_MS = 1000;

let watchlist = [];
let watchlistAt = 0;
let current = null;
let tickInFlight = false;
let heartbeatTimer = null;

async function wallosConfig() {
  return chrome.storage.local.get({ wallosUrl: '', apiKey: '' });
}

async function refreshWatchlist(force) {
  const { wallosUrl, apiKey } = await wallosConfig();
  if (!wallosUrl || !apiKey) {
    watchlist = [];
    return;
  }
  if (!force && watchlistAt && Date.now() - watchlistAt < WATCHLIST_TTL_MS) {
    return;
  }
  const url = wallosUrl.replace(/\/$/, '') + '/api/usage/get_watchlist.php?api_key=' + encodeURIComponent(apiKey);
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
  const idleState = await chrome.idle.queryState(60);
  const focused = await focusedHostname();
  const audible = await audibleTabs();
  return resolveTrackedMatch({
    idleState,
    focusedHostname: focused,
    audibleTabs: audible,
    watchlist,
  });
}

async function flushCurrent(now, isActive) {
  if (!current) {
    return;
  }
  const { wallosUrl, apiKey } = await wallosConfig();
  if (!wallosUrl || !apiKey) {
    return;
  }
  await fetch(wallosUrl.replace(/\/$/, '') + '/api/usage/add_usage.php', {
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
    const now = Math.floor(Date.now() / 1000);
    const match = await resolveTrackedSubscription();
    if (!match) {
      await flushCurrent(now, false);
      current = null;
      return;
    }
    if (!current || current.subscriptionId !== match.id) {
      await flushCurrent(now, false);
      current = {
        subscriptionId: match.id,
        sessionId: crypto.randomUUID(),
        startedAt: now,
      };
      await flushCurrent(now, true);
      return;
    }
    await flushCurrent(now, true);
  } catch (error) {
    console.error(error);
  } finally {
    tickInFlight = false;
  }
}

function startHeartbeat() {
  if (heartbeatTimer != null) {
    return;
  }
  heartbeatTimer = setInterval(() => {
    tick();
  }, HEARTBEAT_MS);
}

function wakeAndTick() {
  startHeartbeat();
  tick();
}

chrome.runtime.onInstalled.addListener(() => {
  // Alarm keeps the service worker waking; the real heartbeat is 1s via setInterval.
  chrome.alarms.create('wallos-usage', { periodInMinutes: 1 });
  wakeAndTick();
});
chrome.runtime.onStartup.addListener(() => wakeAndTick());
chrome.alarms.onAlarm.addListener((alarm) => {
  if (alarm.name === 'wallos-usage') {
    wakeAndTick();
  }
});
chrome.tabs.onActivated.addListener(() => wakeAndTick());
chrome.tabs.onUpdated.addListener((tabId, changeInfo) => {
  if (changeInfo.url || typeof changeInfo.audible === 'boolean') {
    wakeAndTick();
  }
});
chrome.windows.onFocusChanged.addListener(() => wakeAndTick());
chrome.idle.onStateChanged.addListener(() => wakeAndTick());
