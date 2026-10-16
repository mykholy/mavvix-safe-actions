<?php

return [
    // Keep preflight bounded until end-to-end selection and transactional tests pass.
    'max_explicit_selection' => 500,
    'preflight_ttl_seconds' => 300,
];
