<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantDeletionRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ProcessTenantDeletions extends Command
{
    protected $signature = 'tenants:process-deletions';

    protected $description = 'Permanently delete tenants whose guarded offboarding period has elapsed';

    public function handle(): int
    {
        TenantDeletionRequest::withoutGlobalScopes()->where('status', 'scheduled')->where('scheduled_for', '<=', now())->each(function ($request) {
            $tenant = Tenant::withTrashed()->find($request->tenant_id);
            if (! $tenant) {
                return;
            }
            $disk = Storage::disk(config('filesystems.default'));
            $disk->deleteDirectory("exports/{$tenant->public_id}");
            $disk->deleteDirectory("tenants/{$tenant->public_id}");
            $tenant->forceDelete();
            $this->info("Deleted tenant {$tenant->public_id}");
        });

        return self::SUCCESS;
    }
}
