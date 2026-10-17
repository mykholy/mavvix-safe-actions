<?php

declare(strict_types=1);

return [
    'modal' => [
        'description' => 'Review the verified record count before confirming. Permissions are checked again during execution.',
        'confirm' => 'Confirm changes',
        'record_count' => 'Records to change',
    ],
    'notifications' => [
        'preview_failed' => 'Unable to preview the selected records',
        'execute_failed' => 'Safe action was not executed',
    ],
    'errors' => [
        'invalid' => 'The selection contains invalid or duplicate record keys.',
        'limit_exceeded' => 'The selection exceeds the configured safe limit.',
        'scope_mismatch' => 'Some selected records are not available within the permitted scope.',
        'unsupported' => 'This Filament selection mode is not supported safely.',
        'unauthorized' => 'One or more selected records are not authorized.',
        'unavailable' => 'The preflight is missing, expired, already used or belongs to another context.',
        'unbound' => 'This preflight was not bound to a resource query and cannot be executed.',
        'scope_changed' => 'The permitted query changed after the preflight. Start a new preview.',
        'selection_changed' => 'The selected records changed after the preflight. Start a new preview.',
        'connection_mismatch' => 'Preflight and selected records must use the same database connection.',
        'unsupported_scope' => 'A paginated or limited query cannot safely execute this selection.',
    ],
];
