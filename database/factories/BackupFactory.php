<?php

namespace Database\Factories;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Models\Backup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Backup>
 */
class BackupFactory extends Factory
{
    protected $model = Backup::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $timestamp = fake()->dateTimeBetween('-1 month', 'now')->format('Ymd_His');
        $name = 'uas_testing_'.$timestamp.'_'.Str::random(6);

        return [
            'backup_name' => $name,
            'backup_type' => fake()->randomElement([BackupType::Automatic, BackupType::Manual]),
            'environment' => 'testing',
            'file_path' => 'backups/'.$name.'.zip',
            'disk' => 'local',
            'file_size' => fake()->numberBetween(100000, 5000000),
            'database_name' => 'utility_accounts_test',
            'checksum' => hash('sha256', Str::random(32)),
            'status' => BackupStatus::Completed,
            'restore_status' => null,
            'error_message' => null,
            'is_protected' => false,
            'metadata' => [
                'tables_count' => 45,
                'files_count' => 12,
                'archive_format' => 'zip',
                'duration_ms' => 1250,
            ],
            'retention_date' => now()->addDays(30),
            'created_by' => User::factory(),
            'completed_at' => now(),
            'restored_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => BackupStatus::Failed,
            'error_message' => 'Simulated database backup failure.',
            'completed_at' => null,
        ]);
    }

    public function automatic(): static
    {
        return $this->state(fn () => [
            'backup_type' => BackupType::Automatic,
            'created_by' => null,
        ]);
    }

    public function manual(): static
    {
        return $this->state(fn () => [
            'backup_type' => BackupType::Manual,
        ]);
    }

    public function safety(): static
    {
        return $this->state(fn () => [
            'backup_type' => BackupType::Safety,
            'is_protected' => true,
        ]);
    }

    public function protected(): static
    {
        return $this->state(fn () => [
            'is_protected' => true,
        ]);
    }
}
