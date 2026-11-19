<?php

/*
  Dia, the Diamon assistant's mascot.

  Dia is the triangle from the "a" in the Diamon wordmark: the hollow of the
  "a" becomes its eye and the blue dot becomes its companion. The SVG always
  carries every part; styles/styles.css shows the right ones for the state in
  `data-dia-state`, so scripts change Dia's mood by changing that attribute.
*/

const DIA_MASCOT_STATES = ['idle', 'listening', 'thinking', 'happy', 'confused', 'sleeping'];

/**
 * @param int    $size  Rendered width and height in CSS pixels.
 * @param string $state One of DIA_MASCOT_STATES; anything else falls back to idle.
 * @param string $class Extra classes for the wrapper (e.g. "dia-static" to stop motion).
 * @return string
 */
function dia_mascot_svg($size = 40, $state = 'idle', $class = '')
{
    $size = max(12, min(512, (int) $size));
    if (!in_array($state, DIA_MASCOT_STATES, true)) {
        $state = 'idle';
    }

    $classes = trim('dia-mascot ' . preg_replace('/[^A-Za-z0-9_ -]/', '', (string) $class));

    return '<span class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '"'
        . ' data-dia-state="' . $state . '"'
        . ' style="--dia-size: ' . $size . 'px" aria-hidden="true">'
        . '<svg class="dia-svg" viewBox="510 20 780 780" focusable="false">'
        . '<ellipse class="dia-shadow" cx="893" cy="784" rx="280" ry="14" />'
        . '<g class="dia-tilt"><g class="dia-body">'
        . '<path class="dia-triangle" d="M973.15 83.24 L1246.15 571.43 A120 120 0 0 1 1141.41 750 L644.15 750'
        . ' A120 120 0 0 1 540.55 569.44 L825.58 81.83 A85 85 0 0 1 973.15 83.24 Z" />'
        . '<g class="dia-eye">'
        . '<ellipse class="dia-eye-white" cx="901.5" cy="490" rx="133.5" ry="145" />'
        . '<g class="dia-pupil-track"><g class="dia-pupil">'
        . '<circle class="dia-pupil-core" cx="901.5" cy="490" r="64" />'
        . '<circle class="dia-glint" cx="924.5" cy="465.7" r="18" />'
        . '</g></g>'
        . '</g>'
        . '<path class="dia-eye-happy" d="M818 524 Q901.5 404 985 524" />'
        . '<path class="dia-eye-closed" d="M812 492 Q901.5 568 991 492" />'
        . '<circle class="dia-dot" cx="1148.5" cy="634" r="126" />'
        . '</g>'
        . '<g class="dia-zz"><text x="1080" y="250" font-size="120">z</text>'
        . '<text x="1175" y="150" font-size="80">z</text></g>'
        . '</g></svg></span>';
}
