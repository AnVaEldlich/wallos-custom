// Run with: node --test tests/price_input.test.js
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { formatPriceTyping, parsePriceValue, formatCanonicalPrice, validateSubscriptionPrice } = require('../scripts/price-input.js');

test('COP groups thousands with a comma and does not invent decimals', () => {
  assert.equal(formatPriceTyping('3000', 'COP'), '3,000');
  assert.equal(formatPriceTyping('3,000', 'COP'), '3,000');
  assert.equal(parsePriceValue('3,000', 'COP'), '3000');
});

test('USD keeps a whole amount and only shows decimals the user typed', () => {
  assert.equal(formatPriceTyping('150', 'USD'), '150');
  assert.equal(formatPriceTyping('1500', 'USD'), '1,500');
  assert.equal(formatPriceTyping('150.5', 'USD'), '150.5');
  assert.equal(formatPriceTyping('150.', 'USD'), '150.');
  assert.equal(parsePriceValue('1,500.50', 'USD'), '1500.50');
  assert.equal(parsePriceValue('150', 'USD'), '150');
});

test('COP rejects amounts below the smallest coin and keeps real prices', () => {
  assert.equal(validateSubscriptionPrice('10', 'COP').valid, false);
  assert.equal(validateSubscriptionPrice('49', 'COP').valid, false);
  assert.equal(validateSubscriptionPrice('50', 'COP').valid, true);
  assert.equal(validateSubscriptionPrice('3000', 'COP').valid, true);
  assert.equal(validateSubscriptionPrice('10.50', 'COP').valid, false);
  assert.equal(formatPriceTyping('10.50', 'COP'), '10');
  assert.equal(formatPriceTyping('3000.50', 'COP'), '3,000');
});

test('each currency keeps its own smallest amount', () => {
  assert.equal(validateSubscriptionPrice('10', 'USD').valid, true);
  assert.equal(validateSubscriptionPrice('0.01', 'USD').valid, true);
  assert.equal(validateSubscriptionPrice('0.001', 'USD').valid, false);
  assert.equal(validateSubscriptionPrice('10', 'JPY').valid, true);
  assert.equal(validateSubscriptionPrice('9', 'KRW').valid, false);
  assert.equal(validateSubscriptionPrice('10', 'KRW').valid, true);
  assert.equal(validateSubscriptionPrice('50', 'IDR').valid, false);
  assert.equal(validateSubscriptionPrice('100', 'IDR').valid, true);
  assert.equal(validateSubscriptionPrice('4', 'HUF').valid, false);
  assert.equal(validateSubscriptionPrice('5', 'HUF').valid, true);
  assert.equal(validateSubscriptionPrice('0', 'COP').valid, true);
});

test('EUR uses a dot for thousands and a comma for decimals', () => {
  assert.equal(formatPriceTyping('1500', 'EUR'), '1.500');
  assert.equal(formatPriceTyping('1500,5', 'EUR'), '1.500,5');
  assert.equal(parsePriceValue('1.500,50', 'EUR'), '1500.50');
  assert.equal(formatCanonicalPrice('1500.5', 'EUR'), '1.500,5');
});
