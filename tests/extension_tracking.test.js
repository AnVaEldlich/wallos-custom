// Run with: node --test tests/extension_tracking.test.js
//
// Manual checklist after loading the extension folder in Chrome:
// 1. Play Spotify Web, switch to another tab — usage on Spotify should keep rising.
// 2. Pause Spotify in the background — usage should stop after the next tick.
// 3. Focus Spotify while paused — usage should count again (in-service).
// 4. Focus Netflix while Spotify is audible — Netflix wins until you leave it.
const { test } = require('node:test');
const assert = require('node:assert/strict');
const {
  hostMatches,
  matchSubscription,
  resolveTrackedMatch,
  sessionEndedAt,
  sessionCanContinue,
} = require('../extension/tracking.js');

const watchlist = [
  { id: 1, hosts: ['spotify.com', 'open.spotify.com'] },
  { id: 2, hosts: ['netflix.com'] },
];

test('host matching accepts subdomains without suffix collisions', () => {
  assert.equal(hostMatches('open.spotify.com', 'spotify.com'), true);
  assert.equal(hostMatches('spotify.com', 'spotify.com'), true);
  assert.equal(hostMatches('notspotify.com', 'spotify.com'), false);
});

test('focused Spotify counts while the user is active', () => {
  const match = resolveTrackedMatch({
    idleState: 'active',
    focusedHostname: 'open.spotify.com',
    audibleTabs: [],
    watchlist,
  });
  assert.equal(match.id, 1);
});

test('background Spotify audio counts when focus is elsewhere', () => {
  const match = resolveTrackedMatch({
    idleState: 'active',
    focusedHostname: 'docs.google.com',
    audibleTabs: [{ tabId: 40, hostname: 'open.spotify.com' }],
    watchlist,
  });
  assert.equal(match.id, 1);
});

test('focused Netflix wins over background Spotify audio', () => {
  const match = resolveTrackedMatch({
    idleState: 'active',
    focusedHostname: 'www.netflix.com',
    audibleTabs: [{ tabId: 10, hostname: 'open.spotify.com' }],
    watchlist,
  });
  assert.equal(match.id, 2);
});

test('paused Spotify with no focus does not count', () => {
  const match = resolveTrackedMatch({
    idleState: 'active',
    focusedHostname: 'docs.google.com',
    audibleTabs: [],
    watchlist,
  });
  assert.equal(match, null);
});

test('idle blocks focused tracking but still allows audible playback', () => {
  const onlyFocused = resolveTrackedMatch({
    idleState: 'idle',
    focusedHostname: 'open.spotify.com',
    audibleTabs: [],
    watchlist,
  });
  assert.equal(onlyFocused, null);

  const idleWithAudio = resolveTrackedMatch({
    idleState: 'locked',
    focusedHostname: 'docs.google.com',
    audibleTabs: [{ tabId: 7, hostname: 'open.spotify.com' }],
    watchlist,
  });
  assert.equal(idleWithAudio.id, 1);
});

test('audible candidates prefer the lowest tab id for stability', () => {
  const match = resolveTrackedMatch({
    idleState: 'active',
    focusedHostname: 'example.com',
    audibleTabs: [
      { tabId: 22, hostname: 'open.spotify.com' },
      { tabId: 9, hostname: 'www.netflix.com' },
    ],
    watchlist,
  });
  assert.equal(match.id, 2);
});

test('a focused silent service counts without audio', () => {
  const lessons = watchlist.concat([{ id: 3, hosts: ['duolingo.com'] }]);
  const match = resolveTrackedMatch({
    idleState: 'active',
    focusedHostname: 'www.duolingo.com',
    audibleTabs: [],
    watchlist: lessons,
  });
  assert.equal(match.id, 3);
});

test('a quiet minute on the same tab still belongs to the session', () => {
  assert.equal(sessionCanContinue(1000, 1060, 180), true);
  assert.equal(sessionEndedAt(1000, 1060, 180), 1060);
});

test('a long gap ends the session at the last heartbeat', () => {
  assert.equal(sessionCanContinue(1000, 2000, 180), false);
  assert.equal(sessionEndedAt(1000, 2000, 180), 1000);
});

test('ambiguous host matches are ignored', () => {
  const ambiguous = [
    { id: 1, hosts: ['example.com'] },
    { id: 2, hosts: ['example.com'] },
  ];
  assert.equal(matchSubscription('example.com', ambiguous), null);
});
