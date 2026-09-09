<?php

namespace App\Policies;

use App\Models\PaymentSubmission;
use App\Models\User;

class PaymentSubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff() || $user->isResident();
    }

    public function view(User $user, PaymentSubmission $submission): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        if ($user->id === $submission->user_id) {
            return true;
        }

        if ($user->isResident()) {
            if ($user->isOwner()) {
                return $user->owner?->flats()->where('flats.id', $submission->flat_id)->exists() ?? false;
            }

            if ($user->isTenant()) {
                return $user->tenant?->flat_id === $submission->flat_id;
            }
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isStaff() || $user->isResident();
    }

    public function manage(User $user, PaymentSubmission $submission): bool
    {
        return $user->canManageMoney();
    }

    public function update(User $user, PaymentSubmission $submission): bool
    {
        return $user->canManageMoney();
    }

    public function delete(User $user, PaymentSubmission $submission): bool
    {
        return $user->canManageMoney();
    }
}
