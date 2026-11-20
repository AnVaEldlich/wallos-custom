// Currencies that group thousands with "." and use "," as the decimal mark.
// COP and USD stay on "," for thousands so 3000 is 3,000 and 150 stays 150.
const DECIMAL_COMMA_CURRENCIES = new Set([
  'EUR', 'BGN', 'CZK', 'DKK', 'HUF', 'PLN', 'RON', 'SEK', 'NOK', 'ISK',
  'RUB', 'TRY', 'BRL', 'UAH',
]);

let activeSubscriptionCurrencyCode = null;

function separatorsForCurrency(currencyCode) {
  const code = String(currencyCode || 'USD').toUpperCase();
  if (code === 'CHF') {
    return { group: "'", decimal: '.' };
  }
  if (DECIMAL_COMMA_CURRENCIES.has(code)) {
    return { group: '.', decimal: ',' };
  }
  return { group: ',', decimal: '.' };
}

function groupIntegerDigits(integer, group) {
  return integer.replace(/\B(?=(\d{3})+(?!\d))/g, group);
}

// Keep this table aligned with includes/currency_amount.php.
function currencyAmountRule(currencyCode) {
  const code = String(currencyCode || 'USD').toUpperCase();
  const rules = {
    COP: { fractionDigits: 0, minorUnit: 1, minimum: 50 },
    IDR: { fractionDigits: 0, minorUnit: 1, minimum: 100 },
    KRW: { fractionDigits: 0, minorUnit: 1, minimum: 10 },
    HUF: { fractionDigits: 0, minorUnit: 1, minimum: 5 },
    JPY: { fractionDigits: 0, minorUnit: 1, minimum: 1 },
    ISK: { fractionDigits: 0, minorUnit: 1, minimum: 1 },
    CZK: { fractionDigits: 0, minorUnit: 1, minimum: 1 },
    TWD: { fractionDigits: 0, minorUnit: 1, minimum: 1 },
  };
  return rules[code] || { fractionDigits: 2, minorUnit: 0.01, minimum: 0.01 };
}

function formatMinimumAmount(minimum) {
  const text = Number(minimum).toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
  return text === '' ? '0' : text;
}

function splitTypedAmount(raw, separators, fractionDigits) {
  const digits = fractionDigits == null ? 2 : fractionDigits;
  const cleaned = String(raw == null ? '' : raw).replace(/\s/g, '');
  if (cleaned === '') {
    return { integer: '', fraction: null };
  }

  let integer = '';
  let fraction = null;
  for (let i = 0; i < cleaned.length; i++) {
    const ch = cleaned[i];
    if (ch >= '0' && ch <= '9') {
      if (fraction === null) {
        integer += ch;
      } else if (fraction.length < digits) {
        fraction += ch;
      }
    } else if (ch === separators.decimal && fraction === null) {
      if (digits === 0) {
        break;
      }
      fraction = '';
    }
  }

  integer = integer.replace(/^0+(?=\d)/, '');
  if (integer === '') {
    integer = fraction === null ? '' : '0';
  }
  return { integer, fraction };
}

function formatPriceTyping(raw, currencyCode) {
  const separators = separatorsForCurrency(currencyCode);
  const rule = currencyAmountRule(currencyCode);
  const { integer, fraction } = splitTypedAmount(raw, separators, rule.fractionDigits);
  if (integer === '' && fraction === null) {
    return '';
  }
  let formatted = groupIntegerDigits(integer, separators.group);
  if (fraction !== null) {
    formatted += separators.decimal + fraction;
  }
  return formatted;
}

function parsePriceValue(raw, currencyCode) {
  const separators = separatorsForCurrency(currencyCode);
  const rule = currencyAmountRule(currencyCode);
  const { integer, fraction } = splitTypedAmount(raw, separators, rule.fractionDigits);
  if (integer === '' && fraction === null) {
    return '';
  }
  const whole = integer === '' ? '0' : integer;
  if (fraction === null || fraction === '') {
    return whole;
  }
  return whole + '.' + fraction;
}

function formatCanonicalPrice(canonical, currencyCode) {
  if (canonical === null || canonical === undefined || String(canonical).trim() === '') {
    return '';
  }
  const separators = separatorsForCurrency(currencyCode);
  const rule = currencyAmountRule(currencyCode);
  const parts = String(canonical).trim().split('.');
  let integer = parts[0].replace(/\D/g, '').replace(/^0+(?=\d)/, '');
  if (integer === '') {
    integer = '0';
  }
  let formatted = groupIntegerDigits(integer, separators.group);
  if (rule.fractionDigits > 0 && parts.length > 1) {
    formatted += separators.decimal + parts[1].replace(/\D/g, '').slice(0, rule.fractionDigits);
  }
  return formatted;
}

function droppedNonZeroToZero(raw, currencyCode) {
  const canonical = parsePriceValue(raw, currencyCode);
  if (canonical !== '' && Number(canonical) !== 0) {
    return false;
  }
  const digits = String(raw == null ? '' : raw).replace(/\D/g, '').replace(/^0+/, '');
  return digits.length > 0;
}

function validateSubscriptionPrice(raw, currencyCode) {
  const code = String(currencyCode || 'USD').toUpperCase();
  const rule = currencyAmountRule(code);
  const canonical = parsePriceValue(raw, code);
  const minimum = formatMinimumAmount(rule.minimum);
  if (canonical === '') {
    return { valid: false, reason: 'empty', canonical: '', code: code, minimum: minimum };
  }

  const amount = Number(canonical);
  if (!Number.isFinite(amount) || amount < 0) {
    return { valid: false, reason: 'invalid', canonical: canonical, code: code, minimum: minimum };
  }
  if (amount === 0) {
    const dropped = droppedNonZeroToZero(raw, code);
    return {
      valid: !dropped,
      reason: dropped ? 'minimum' : '',
      canonical: canonical,
      code: code,
      minimum: minimum,
    };
  }

  const scale = 10 ** rule.fractionDigits;
  const scaled = Math.round(amount * scale);
  const step = Math.round(rule.minorUnit * scale);
  const minimumScaled = Math.round(rule.minimum * scale);
  const valid = step > 0 && scaled % step === 0 && scaled >= minimumScaled;
  return {
    valid: valid,
    reason: valid ? '' : 'minimum',
    canonical: canonical,
    code: code,
    minimum: minimum,
  };
}

function subscriptionPriceMessage(result) {
  const fallback = 'That amount is not valid for {{currency}}. The smallest amount is {{minimum}}.';
  let template = fallback;
  if (typeof translate === 'function') {
    const translated = translate('invalid_currency_amount');
    if (translated && translated.indexOf('Translation Missing') === -1 && translated.indexOf('i18n String Missing') === -1) {
      template = translated;
    }
  }
  return template.replace('{{currency}}', result.code).replace('{{minimum}}', result.minimum);
}

function clearSubscriptionPriceError() {
  const input = document.getElementById('price');
  const error = document.getElementById('price-error');
  if (input) {
    input.classList.remove('price-invalid');
    input.removeAttribute('aria-invalid');
  }
  if (error) {
    error.hidden = true;
    error.textContent = '';
  }
}

function showSubscriptionPriceError(result) {
  const input = document.getElementById('price');
  const error = document.getElementById('price-error');
  if (input) {
    input.classList.add('price-invalid');
    input.setAttribute('aria-invalid', 'true');
  }
  if (error) {
    error.hidden = false;
    error.textContent = subscriptionPriceMessage(result);
  }
}

function subscriptionPriceError(raw, currencyCode) {
  const result = validateSubscriptionPrice(raw, currencyCode || selectedSubscriptionCurrencyCode());
  if (result.reason === 'empty' || result.valid) {
    clearSubscriptionPriceError();
    return '';
  }
  showSubscriptionPriceError(result);
  return subscriptionPriceMessage(result);
}

function cursorAfterCanonicalChars(formatted, canonicalLength, separators) {
  if (canonicalLength <= 0) {
    return 0;
  }
  let seen = 0;
  for (let i = 0; i < formatted.length; i++) {
    if (formatted[i] === separators.group) {
      continue;
    }
    seen++;
    if (seen >= canonicalLength) {
      return i + 1;
    }
  }
  return formatted.length;
}

function selectedSubscriptionCurrencyCode() {
  const select = document.getElementById('currency');
  if (!select) {
    return 'USD';
  }
  const option = select.options[select.selectedIndex];
  return (option && option.dataset.code) || 'USD';
}

function rememberSubscriptionCurrency() {
  activeSubscriptionCurrencyCode = selectedSubscriptionCurrencyCode();
}

function formatStoredSubscriptionPrice(value, currencyCode) {
  return formatCanonicalPrice(value, currencyCode || selectedSubscriptionCurrencyCode());
}

function normalizeSubscriptionPrice(value, currencyCode) {
  return parsePriceValue(value, currencyCode || selectedSubscriptionCurrencyCode());
}

function applySubscriptionPriceFormatting(input) {
  if (!input) {
    return;
  }
  const code = selectedSubscriptionCurrencyCode();
  const separators = separatorsForCurrency(code);
  const cursor = input.selectionStart == null ? input.value.length : input.selectionStart;
  const canonicalBefore = parsePriceValue(input.value.slice(0, cursor), code);
  const formatted = formatPriceTyping(input.value, code);
  if (formatted === input.value) {
    return;
  }
  input.value = formatted;
  const nextCursor = cursorAfterCanonicalChars(formatted, canonicalBefore.length, separators);
  input.setSelectionRange(nextCursor, nextCursor);
}

function reformatSubscriptionPriceForCurrency() {
  const input = document.getElementById('price');
  if (!input) {
    rememberSubscriptionCurrency();
    return;
  }
  const previous = activeSubscriptionCurrencyCode || selectedSubscriptionCurrencyCode();
  const canonical = parsePriceValue(input.value, previous);
  rememberSubscriptionCurrency();
  input.value = canonical === '' ? '' : formatCanonicalPrice(canonical, activeSubscriptionCurrencyCode);
  if (input.value !== '') {
    subscriptionPriceError(input.value);
  } else {
    clearSubscriptionPriceError();
  }
}

function initSubscriptionPriceInput() {
  const input = document.getElementById('price');
  const currency = document.getElementById('currency');
  if (!input || !currency) {
    return;
  }
  rememberSubscriptionCurrency();
  input.addEventListener('input', function () {
    clearSubscriptionPriceError();
    applySubscriptionPriceFormatting(input);
  });
  input.addEventListener('blur', function () {
    subscriptionPriceError(input.value);
  });
  currency.addEventListener('change', reformatSubscriptionPriceForCurrency);
}

if (typeof document !== 'undefined') {
  document.addEventListener('DOMContentLoaded', initSubscriptionPriceInput);
}

if (typeof module !== 'undefined' && module.exports) {
  module.exports = {
    formatPriceTyping,
    parsePriceValue,
    formatCanonicalPrice,
    separatorsForCurrency,
    currencyAmountRule,
    validateSubscriptionPrice,
  };
}
