<?php

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/**
 * The shared create/edit/delete shape behind every master-data screen.
 *
 * A component using this trait supplies:
 *   - formRules():           validation rules for the open form
 *   - fillForm(?Model):      load the form properties from a record, or reset them
 *   - persist():             write the validated form and return the record
 *   - findRecord(int):       resolve a record, scoped however the screen needs
 *   - authorizeAction(...):  the screen's policy check, run before every mutation
 *
 * Authorization stays with the component because each master has its own policy,
 * but the trait guarantees it is called — no action here mutates without asking.
 *
 * Deletes are two-step: the list calls confirmDelete(), the dialog calls delete().
 * A screen describes its own record by overriding deleteConfirmation().
 */
trait WithCrudModal
{
    use WithNotices;

    /** The record being edited, or null when the open form is a create form. */
    public ?int $editingId = null;

    public bool $showForm = false;

    /** The record a delete has been asked for but not yet confirmed. */
    public ?int $deletingId = null;

    /**
     * @return array<string, mixed>
     */
    abstract protected function formRules(): array;

    abstract protected function fillForm(?Model $record): void;

    abstract protected function persist(): Model;

    abstract protected function findRecord(int $id): Model;

    /**
     * Called before create, update and delete. Implementations call authorize().
     */
    abstract protected function authorizeAction(string $ability, ?Model $record = null): void;

    public function create(): void
    {
        $this->authorizeAction('create');

        $this->clearNotice();
        $this->resetValidation();
        $this->editingId = null;
        $this->fillForm(null);
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $record = $this->findRecord($id);

        $this->authorizeAction('update', $record);

        $this->clearNotice();
        $this->resetValidation();
        $this->editingId = $record->getKey();
        $this->fillForm($record);
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->resetValidation();
        $this->closeForm();
    }

    public function save(): void
    {
        $record = $this->editingId === null ? null : $this->findRecord($this->editingId);

        $this->authorizeAction($record === null ? 'create' : 'update', $record);

        $this->validate($this->formRules());

        $this->persist();

        $this->closeForm();
        $this->notify(__('masters.saved'));
    }

    /**
     * Step one of a delete: ask. Nothing is touched until delete() runs.
     */
    public function confirmDelete(int $id): void
    {
        $record = $this->findRecord($id);

        $this->authorizeAction('delete', $record);

        $this->clearNotice();
        $this->deletingId = $record->getKey();
    }

    public function cancelDelete(): void
    {
        $this->deletingId = null;
    }

    /**
     * What the confirmation dialog says about this record.
     *
     * Screens override this to describe what is really at stake — how many flats an owner
     * holds, how many bills a vendor has — because "are you sure?" alone tells the operator
     * nothing they did not already know.
     *
     * @return array{title: string, message: string, details: string|null}
     */
    protected function deleteConfirmation(Model $record): array
    {
        return [
            'title' => __('confirmations.delete_title'),
            'message' => __('confirmations.delete_message'),
            'details' => $this->describeRecord($record),
        ];
    }

    /**
     * The dialog's contents for the record awaiting confirmation, or null when none is.
     *
     * @return array{title: string, message: string, details: string|null}|null
     */
    public function pendingDelete(): ?array
    {
        if ($this->deletingId === null) {
            return null;
        }

        return $this->deleteConfirmation($this->findRecord($this->deletingId));
    }

    /**
     * A short label for a record, used by the default confirmation.
     */
    protected function describeRecord(Model $record): ?string
    {
        foreach (['name', 'number', 'code', 'title', 'description'] as $attribute) {
            $value = $record->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    public function delete(int $id): void
    {
        $record = $this->findRecord($id);

        $this->authorizeAction('delete', $record);

        $this->deletingId = null;

        try {
            $deleted = $this->deleteRecord($record);
        } catch (QueryException) {
            // A restricted foreign key: the record is in use, so it stays.
            $this->notifyError(__('masters.delete_blocked'));

            return;
        }

        if ($deleted) {
            $this->notify(__('masters.deleted'));
        }
    }

    /**
     * Overridable so a screen can refuse a delete on its own terms.
     *
     * @return bool whether the record was actually deleted
     */
    protected function deleteRecord(Model $record): bool
    {
        $record->delete();

        return true;
    }

    protected function closeForm(): void
    {
        $this->editingId = null;
        $this->showForm = false;
        $this->fillForm(null);
    }

    public function isEditing(): bool
    {
        return $this->editingId !== null;
    }
}
