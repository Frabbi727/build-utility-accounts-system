<?php

namespace App\Livewire\Admin;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Models\Backup;
use App\Models\User;
use App\Services\Backup\BackupService;
use App\Services\Backup\RestoreService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Throwable;

class BackupList extends Component
{
    use WithFileUploads, WithPagination;

    public string $typeFilter = '';

    public string $statusFilter = '';

    public string $search = '';

    public bool $isCreating = false;

    /** @var TemporaryUploadedFile|UploadedFile|null */
    public $uploadedFile = null;

    public bool $showUploadModal = false;

    public bool $isUploading = false;

    public ?int $selectedBackupId = null;

    public bool $showDetailModal = false;

    public bool $showRestoreModal = false;

    public bool $restoreConfirmed = false;

    public string $restoreConfirmationPhrase = '';

    public bool $isRestoring = false;

    public ?int $backupToDeleteId = null;

    public bool $showDeleteModal = false;

    public string $statusMessage = '';

    public string $statusMessageType = 'success'; // success, error

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function createManualBackup(BackupService $backupService): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user || ! $user->isAdmin()) {
            abort(403, 'Unauthorized.');
        }

        $this->isCreating = true;
        $this->statusMessage = '';

        try {
            $backup = $backupService->create(
                type: BackupType::Manual,
                creator: $user,
                options: ['include_files' => true]
            );

            $this->statusMessageType = 'success';
            $this->statusMessage = "Manual backup [{$backup->backup_name}] created successfully ({$backup->formattedSize()}).";
        } catch (Throwable $e) {
            $this->statusMessageType = 'error';
            $this->statusMessage = "Backup creation failed: {$e->getMessage()}";
        } finally {
            $this->isCreating = false;
        }
    }

    public function openUploadModal(): void
    {
        $this->uploadedFile = null;
        $this->showUploadModal = true;
    }

    public function closeUploadModal(): void
    {
        $this->showUploadModal = false;
        $this->uploadedFile = null;
        $this->isUploading = false;
    }

    public function processUpload(BackupService $backupService): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user || ! $user->isAdmin()) {
            abort(403, 'Unauthorized.');
        }

        $this->validate([
            'uploadedFile' => ['required', 'file', 'mimes:zip,enc', 'max:512000'], // max 500MB
        ]);

        $this->isUploading = true;

        try {
            $originalName = $this->uploadedFile->getClientOriginalName();
            $tempPath = $this->uploadedFile->getRealPath();

            $backup = $backupService->upload($tempPath, $originalName, $user);

            $this->statusMessageType = 'success';
            $this->statusMessage = "Backup archive [{$backup->backup_name}] uploaded and verified successfully. It is now ready for download or restoration.";
            $this->closeUploadModal();
        } catch (Throwable $e) {
            $this->statusMessageType = 'error';
            $this->statusMessage = "Failed to process uploaded backup: {$e->getMessage()}";
        } finally {
            $this->isUploading = false;
        }
    }

    public function openDetailModal(int $id): void
    {
        $this->selectedBackupId = $id;
        $this->showDetailModal = true;
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->selectedBackupId = null;
    }

    public function openRestoreModal(int $id): void
    {
        $this->selectedBackupId = $id;
        $this->restoreConfirmed = false;
        $this->restoreConfirmationPhrase = '';
        $this->showRestoreModal = true;
    }

    public function closeRestoreModal(): void
    {
        $this->showRestoreModal = false;
        $this->restoreConfirmed = false;
        $this->restoreConfirmationPhrase = '';
        $this->selectedBackupId = null;
        $this->isRestoring = false;
    }

    public function confirmRestore(RestoreService $restoreService): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user || ! $user->isAdmin()) {
            abort(403, 'Unauthorized.');
        }

        if (! $this->restoreConfirmed || trim(strtoupper($this->restoreConfirmationPhrase)) !== 'RESTORE') {
            $this->statusMessageType = 'error';
            $this->statusMessage = 'You must check the confirmation checkbox and type "RESTORE" to proceed with restoration.';

            return;
        }

        $backup = Backup::find($this->selectedBackupId);
        if (! $backup) {
            $this->closeRestoreModal();

            return;
        }

        $this->isRestoring = true;

        try {
            $result = $restoreService->restore(
                backup: $backup,
                actor: $user,
                options: ['skip_safety_backup' => false]
            );

            $this->statusMessageType = 'success';
            $safetyName = $result['safety_backup']?->backup_name ? " (Safety backup: {$result['safety_backup']->backup_name})" : '';
            $this->statusMessage = "✓ System restored successfully to backup [{$backup->backup_name}]{$safetyName}.";
            $this->closeRestoreModal();
        } catch (Throwable $e) {
            $this->statusMessageType = 'error';
            $this->statusMessage = "Restoration error: {$e->getMessage()}";
            $this->closeRestoreModal();
        }
    }

    public function toggleProtection(int $id, BackupService $backupService): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user || ! $user->isAdmin()) {
            abort(403, 'Unauthorized.');
        }

        $backup = Backup::find($id);
        if ($backup) {
            $isProtected = $backupService->toggleProtection($backup, $user);
            $this->statusMessageType = 'success';
            $this->statusMessage = $isProtected
                ? "Backup [{$backup->backup_name}] is now protected from automatic deletion."
                : "Protection removed from backup [{$backup->backup_name}].";
        }
    }

    public function openDeleteModal(int $id): void
    {
        $this->backupToDeleteId = $id;
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->backupToDeleteId = null;
    }

    public function confirmDelete(BackupService $backupService): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user || ! $user->isAdmin()) {
            abort(403, 'Unauthorized.');
        }

        $backup = Backup::find($this->backupToDeleteId);
        if ($backup) {
            try {
                $name = $backup->backup_name;
                $backupService->delete($backup, $user);
                $this->statusMessageType = 'success';
                $this->statusMessage = "Backup [{$name}] deleted successfully.";
            } catch (Throwable $e) {
                $this->statusMessageType = 'error';
                $this->statusMessage = "Failed to delete backup: {$e->getMessage()}";
            }
        }

        $this->closeDeleteModal();
    }

    public function render(): View
    {
        /** @var User|null $user */
        $user = Auth::user();
        $this->authorize('viewAny', Backup::class);

        $query = Backup::query()
            ->with('user')
            ->when($this->typeFilter !== '', fn (Builder $q) => $q->where('backup_type', $this->typeFilter))
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->when($this->search !== '', function (Builder $q): void {
                $term = '%'.trim($this->search).'%';
                $q->where('backup_name', 'ilike', $term)
                    ->orWhere('checksum', 'ilike', $term);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $backups = $query->paginate(15);

        $totalCount = Backup::count();
        $completedCount = Backup::where('status', BackupStatus::Completed)->count();
        $totalStorageBytes = (int) Backup::where('status', BackupStatus::Completed)->sum('file_size');
        $latestBackup = Backup::where('status', BackupStatus::Completed)->orderByDesc('created_at')->first();

        $selectedBackup = $this->selectedBackupId !== null
            ? Backup::with('user')->find($this->selectedBackupId)
            : null;

        $backupToDelete = $this->backupToDeleteId !== null
            ? Backup::find($this->backupToDeleteId)
            : null;

        return view('livewire.admin.backup-list', [
            'backups' => $backups,
            'totalCount' => $totalCount,
            'completedCount' => $completedCount,
            'totalStorageBytes' => $totalStorageBytes,
            'latestBackup' => $latestBackup,
            'selectedBackup' => $selectedBackup,
            'backupToDelete' => $backupToDelete,
        ])->layout('components.layouts.app');
    }
}
