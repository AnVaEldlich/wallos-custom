// Pure helpers for noticing that a service confirmed a cancellation the user
// started from Diamon. Loaded by background.js (importScripts) and by the
// Node tests (require). Uses hostMatches()/hostnameFromUrl() from tracking.js.

var cancellationHostMatches = typeof hostMatches === 'function'
  ? hostMatches
  : require('./tracking.js').hostMatches;
var cancellationHostnameFromUrl = typeof hostnameFromUrl === 'function'
  ? hostnameFromUrl
  : require('./tracking.js').hostnameFromUrl;

/**
 * The pending cancellations whose service this tab belongs to.
 */
function cancellationsForUrl(url, cancellations) {
  const hostname = cancellationHostnameFromUrl(url);
  if (!hostname) {
    return [];
  }
  return (cancellations || []).filter((item) =>
    (item.hosts || []).some((host) => cancellationHostMatches(hostname, host))
  );
}

/**
 * True when the tab URL alone shows the confirmation page.
 */
function cancellationUrlConfirms(url, item) {
  return (item.url_patterns || []).some((pattern) => {
    try {
      return new RegExp(pattern, 'i').test(String(url || ''));
    } catch (error) {
      return false;
    }
  });
}

function normalizeCancellationText(text) {
  return String(text || '')
    .toLowerCase()
    .replace(/[‘’ʼ]/g, "'")
    .replace(/\s+/g, ' ');
}

/**
 * True when the page text contains one of the confirmation phrases.
 */
function cancellationTextConfirms(text, phrases) {
  const haystack = normalizeCancellationText(text);
  if (!haystack) {
    return false;
  }
  return (phrases || []).some((phrase) => {
    const needle = normalizeCancellationText(phrase).trim();
    return needle !== '' && haystack.includes(needle);
  });
}

if (typeof module !== 'undefined' && module.exports) {
  module.exports = {
    cancellationsForUrl,
    cancellationUrlConfirms,
    cancellationTextConfirms,
  };
}
