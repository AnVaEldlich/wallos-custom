<?php
/*
  Dia, the assistant's mascot, is drawn by one PHP function and its mood is
  switched by styles keyed on data-dia-state. These cases pin the contract
  that styles/styles.css and scripts/minibot.js rely on.
*/

require_once DIAMON_ROOT . '/includes/dia_mascot.php';

diamon_test('every state renders with all of its parts', function () {
    foreach (DIA_MASCOT_STATES as $state) {
        $svg = dia_mascot_svg(40, $state);
        assert_contains('data-dia-state="' . $state . '"', $svg, $state . ' is set on the wrapper');
        foreach (['dia-triangle', 'dia-eye-white', 'dia-pupil-track', 'dia-pupil-core', 'dia-eye-happy', 'dia-eye-closed', 'dia-dot', 'dia-zz'] as $part) {
            assert_contains('class="' . $part . '"', $svg, $state . ' carries ' . $part);
        }
    }
});

diamon_test('an unknown state falls back to idle and the size is clamped', function () {
    $svg = dia_mascot_svg(99999, '"><script>');
    assert_contains('data-dia-state="idle"', $svg, 'an unknown state becomes idle');
    assert_contains('--dia-size: 512px', $svg, 'the size is capped');
    assert_not_contains('<script>', $svg, 'nothing from the state reaches the markup');
});

diamon_test('extra classes cannot break out of the attribute', function () {
    $svg = dia_mascot_svg(26, 'idle', 'dia-static" onload="x');
    assert_not_contains('onload=', $svg, 'quotes and equals signs are stripped from the classes');
    assert_contains('class="dia-mascot dia-static onloadx"', $svg, 'the safe characters are kept');
});

diamon_test('the mascot is decorative and every state has styles', function () {
    assert_contains('aria-hidden="true"', dia_mascot_svg(), 'buttons around Dia carry the label');

    $css = file_get_contents(DIAMON_ROOT . '/styles/styles.css');
    foreach (DIA_MASCOT_STATES as $state) {
        if ($state === 'idle') {
            continue;
        }
        assert_contains('[data-dia-state="' . $state . '"]', $css, $state . ' has styles');
    }
    assert_contains('prefers-reduced-motion', $css, 'motion can be turned off');
});
