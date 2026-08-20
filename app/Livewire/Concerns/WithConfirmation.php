<?php

namespace App\Livewire\Concerns;

/**
 * The two-step shape behind every irreversible action.
 *
 * A screen asks with `askConfirm('approve', $id)`, the view renders `<x-ui.confirm-dialog>`
 * while `$pendingConfirm` is set, and the dialog's confirm button calls `runConfirmed()`,
 * which dispatches to the named method.
 *
 * A Livewire action is a public HTTP entry point, so the dispatch is restricted to the
 * methods a component names in `confirmableActions()`. Without that allowlist, a crafted
 * request could confirm its way into any public method on the component.
 */
trait WithConfirmation
{
    /**
     * The action awaiting confirmation, or null when nothing is pending.
     *
     * @var array{action: string, id: int|null}|null
     */
    public ?array $pendingConfirm = null;

    /**
     * The actions `runConfirmed()` is allowed to dispatch to.
     *
     * @return list<string>
     */
    abstract protected function confirmableActions(): array;

    public function askConfirm(string $action, ?int $id = null): void
    {
        if (! in_array($action, $this->confirmableActions(), true)) {
            return;
        }

        $this->pendingConfirm = ['action' => $action, 'id' => $id];
    }

    public function cancelConfirm(): void
    {
        $this->pendingConfirm = null;
    }

    public function runConfirmed(): void
    {
        $pending = $this->pendingConfirm;

        if ($pending === null || ! in_array($pending['action'], $this->confirmableActions(), true)) {
            $this->pendingConfirm = null;

            return;
        }

        // Cleared first: the action may throw, and a stale dialog over an error is worse
        // than no dialog. Authorization still happens inside the action itself.
        $this->pendingConfirm = null;

        $pending['id'] === null
            ? $this->{$pending['action']}()
            : $this->{$pending['action']}($pending['id']);
    }

    /**
     * Whether a specific action (optionally for a specific record) is awaiting confirmation.
     */
    public function isConfirming(string $action, ?int $id = null): bool
    {
        if ($this->pendingConfirm === null || $this->pendingConfirm['action'] !== $action) {
            return false;
        }

        return $id === null || $this->pendingConfirm['id'] === $id;
    }

    /**
     * The record id awaiting confirmation for this action, if any.
     */
    public function confirmingId(string $action): ?int
    {
        return $this->isConfirming($action) ? $this->pendingConfirm['id'] : null;
    }
}
