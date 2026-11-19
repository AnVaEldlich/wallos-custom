function hostMatches(hostname, host) {
  hostname = String(hostname || '').replace(/\.$/, '').toLowerCase();
  host = String(host || '').toLowerCase();
  return hostname === host || hostname.endsWith('.' + host);
}

function hostnameFromUrl(url) {
  if (!url) {
    return null;
  }
  try {
    return new URL(url).hostname;
  } catch (error) {
    return null;
  }
}

function matchSubscription(hostname, watchlist) {
  if (!hostname) {
    return null;
  }
  const hits = (watchlist || []).filter((item) =>
    (item.hosts || []).some((host) => hostMatches(hostname, host))
  );
  return hits.length === 1 ? hits[0] : null;
}

/**
 * Pick which subscription the extension should report for this tick.
 *
 * Priority:
 * 1. Focused tab matching the watchlist, only when the user is not idle.
 *    Silent services (Duolingo, Notion, and the rest) use this path: the
 *    active tab counts while the user is working in it, with no audio required.
 * 2. Otherwise the lowest-id audible tab that matches the watchlist
 *    (background playback; idle does not block this path).
 */
function resolveTrackedMatch({ idleState, focusedHostname, audibleTabs, watchlist }) {
  const list = watchlist || [];

  if (focusedHostname && idleState === 'active') {
    const focusedMatch = matchSubscription(focusedHostname, list);
    if (focusedMatch) {
      return focusedMatch;
    }
  }

  const audible = (audibleTabs || [])
    .map((tab) => ({
      tabId: Number(tab.tabId),
      hostname: tab.hostname || hostnameFromUrl(tab.url),
    }))
    .filter((tab) => tab.hostname)
    .sort((a, b) => a.tabId - b.tabId);

  for (let i = 0; i < audible.length; i++) {
    const match = matchSubscription(audible[i].hostname, list);
    if (match) {
      return match;
    }
  }

  return null;
}

/**
 * The service worker sleeps between alarms. A gap up to maxGapSeconds is still
 * the same visit and should be counted. A longer gap is time we did not see,
 * so the session ends at the last heartbeat.
 */
function sessionEndedAt(lastSeen, now, maxGapSeconds) {
  lastSeen = Number(lastSeen || 0);
  now = Number(now || 0);
  maxGapSeconds = Number(maxGapSeconds || 0);
  if (lastSeen <= 0 || now <= lastSeen || now - lastSeen > maxGapSeconds) {
    return lastSeen > 0 && now - lastSeen > maxGapSeconds ? lastSeen : now;
  }
  return now;
}

function sessionCanContinue(lastSeen, now, maxGapSeconds) {
  lastSeen = Number(lastSeen || 0);
  now = Number(now || 0);
  return lastSeen > 0 && now >= lastSeen && now - lastSeen <= Number(maxGapSeconds || 0);
}

if (typeof module !== 'undefined' && module.exports) {
  module.exports = {
    hostMatches,
    hostnameFromUrl,
    matchSubscription,
    resolveTrackedMatch,
    sessionEndedAt,
    sessionCanContinue,
  };
}
