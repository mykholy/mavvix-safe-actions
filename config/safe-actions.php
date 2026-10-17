<?php

return [
    // Keep preflight bounded until end-to-end selection and transactional tests pass.
    'max_explicit_selection' => 500,
    'preflight_ttl_seconds' => 300,

    // Extra columns the declarative update API must never modify.
    // Always include the tenant/ownership keys used by your own application.
    // Core identifier and timestamp restrictions cannot be removed here.
    'protected_update_columns' => [
        'organization_id',
        'workspace_id',
        'team_id',
        'account_id',
        'company_id',
        'owner_id',
    ],
];
