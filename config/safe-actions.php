<?php

return [
    // Keep preflight bounded until end-to-end selection and transactional tests pass.
    'max_explicit_selection' => 500,
    'preflight_ttl_seconds' => 300,

    // Additional tenant/ownership keys for your application.
    // This list is additive: built-in identifiers, timestamps and common
    // ownership fields are always blocked, even if you replace this array.
    'protected_update_columns' => [],
];
