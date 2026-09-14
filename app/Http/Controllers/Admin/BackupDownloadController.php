<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Services\Audit\AuditService;
use App\Services\Backup\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupDownloadController extends Controller
{
    public function __construct(
        private readonly BackupService $backupService,
        private readonly AuditService $auditService,
    ) {}

    /**
     * Authenticated and authorized download for a specific backup archive.
     */
    public function download(Request $request, Backup $backup): StreamedResponse
    {
        Gate::authorize('download', $backup);

        // Verify backup integrity prior to allowing download
        $verification = $this->backupService->verify($backup);
        if (! $verification['valid']) {
            abort(404, 'Backup archive is corrupted or missing from storage.');
        }

        $disk = Storage::disk($backup->disk);
        $filename = "{$backup->backup_name}.zip";

        // Audit log download action
        $this->auditService->record(
            action: 'BACKUP_DOWNLOADED',
            module: 'backup',
            entity: $backup,
            description: "Administrator downloaded backup archive [{$backup->backup_name}].",
            payload: [
                'backup_id' => $backup->id,
                'backup_name' => $backup->backup_name,
                'file_size' => $backup->file_size,
                'checksum' => $backup->checksum,
            ],
            userId: $request->user()?->id,
        );

        return $disk->download($backup->file_path, $filename, [
            'Content-Type' => 'application/zip',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
