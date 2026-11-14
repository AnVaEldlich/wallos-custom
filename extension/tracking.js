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

if (typeof module !== 'undefined' && module.exports) {
  module.exports = {
    hostMatches,
    hostnameFromUrl,
    matchSubscription,
    resolveTrackedMatch,
  };
}
