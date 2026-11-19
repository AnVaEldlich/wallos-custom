// Run with: node --test tests/extension_cancellation.test.js
//
// Manual checklist after loading the extension folder in Chrome:
// 1. In Diamon, cancel a catalogue subscription (e.g. Spotify): its cancel page
//    opens and Diamon shows the "Did you cancel…?" dialog.
// 2. Finish cancelling on the service. Within a few seconds of its
//    confirmation page loading, the dialog closes by itself and the card moves
//    to "Cancelled subscriptions".
const { test } = require('node:test');
const assert = require('node:assert/strict');
const {
  cancellationsForUrl,
  cancellationUrlConfirms,
  cancellationTextConfirms,
} = require('../extension/cancellation.js');

const pending = [
  { attempt_id: 7, hosts: ['spotify.com'], url_patterns: [] },
  { attempt_id: 8, hosts: ['netflix.com'], url_patterns: ['netflix\\.com/cancelplan/(confirm|complete|done)'] },
];

const phrases = ['subscription has been cancelled', "you've cancelled your", 'tu suscripción ha sido cancelada'];

test('tabs are matched to the attempt of their service only', () => {
  assert.deepEqual(cancellationsForUrl('https://www.spotify.com/account/subscription/', pending).map((i) => i.attempt_id), [7]);
  assert.deepEqual(cancellationsForUrl('https://notspotify.com/', pending), []);
  assert.deepEqual(cancellationsForUrl('chrome://extensions', pending), []);
  assert.deepEqual(cancellationsForUrl('', pending), []);
});

test('a confirmation URL counts on its own', () => {
  assert.equal(cancellationUrlConfirms('https://www.netflix.com/cancelplan/confirm', pending[1]), true);
  assert.equal(cancellationUrlConfirms('https://www.netflix.com/cancelplan', pending[1]), false);
  assert.equal(cancellationUrlConfirms('https://www.spotify.com/', pending[0]), false);
});

test('an invalid URL pattern is ignored instead of throwing', () => {
  assert.equal(cancellationUrlConfirms('https://x.com/', { url_patterns: ['('] }), false);
});

test('confirmation phrases match regardless of case, spacing and curly apostrophes', () => {
  assert.equal(cancellationTextConfirms('Done!\nYour Subscription  has been\ncancelled.', phrases), true);
  assert.equal(cancellationTextConfirms('You’ve cancelled your Premium plan', phrases), true);
  assert.equal(cancellationTextConfirms('Listo: tu suscripción ha sido cancelada', phrases), true);
});

test('a page that only offers cancelling is not a confirmation', () => {
  assert.equal(cancellationTextConfirms('Cancel subscription. Are you sure you want to cancel?', phrases), false);
  assert.equal(cancellationTextConfirms('', phrases), false);
  assert.equal(cancellationTextConfirms('anything', []), false);
});
