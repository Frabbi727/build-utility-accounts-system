<?php

return [
    // Shared dialog chrome
    'confirm' => 'Yes, continue',
    'cancel' => 'Cancel',
    'this_cannot_be_undone' => 'This cannot be undone.',

    // Deleting master data
    'delete_title' => 'Delete this record?',
    'delete_message' => 'The record will be removed permanently. Anything already posted to the ledger stays where it is.',
    'delete_confirm' => 'Yes, delete',
    'record' => 'Record',

    // Ledger failures surfaced on the form
    'period_locked' => 'The accounting period :period is locked, so nothing can be posted into it. Choose a different date, or post an adjusting entry.',
    'period_locked_generic' => 'That accounting period is locked, so nothing can be posted into it.',
    'post_conflict' => 'Someone else changed this at the same moment. Check the current figures and try again.',
];
