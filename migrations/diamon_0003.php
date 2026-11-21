<?php

// Colombian peso was missing from the seeded currency list. Add it once per
// account that does not already have a COP row, without touching a code the
// user created themselves.

$db->exec("INSERT INTO currencies (name, symbol, code, rate, user_id)
    SELECT 'Colombian Peso', 'COL$', 'COP', 1, user.id
    FROM user
    WHERE NOT EXISTS (
        SELECT 1 FROM currencies
        WHERE currencies.user_id = user.id AND currencies.code = 'COP'
    )");
