<?php

namespace App\Console\Commands;

use App\Models\LegacyImportMapping;
use App\Models\LegacyStagingRecord;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ValidateLegacyImport extends Command
{
    protected $signature = 'legacy:validate {tenant} {--company-id=}';

    protected $description = 'Reconcile every staged source row and normalized core transform.';

    public function handle(TenantContext $context): int
    {
        $tenant = Tenant::where('slug', $this->argument('tenant'))->firstOrFail();
        $context->set($tenant);
        $failed = false;
        $rows = [];
        $companyId = $this->option('company-id') !== null
            ? (int) $this->option('company-id')
            : LegacyStagingRecord::whereNotNull('source_company_id')->distinct()->value('source_company_id');
        $tables = LegacyStagingRecord::query()->distinct()->orderBy('source_table')->pluck('source_table');
        if ($tables->isEmpty()) {
            $this->error('No staged legacy records exist for this tenant. Run legacy:import first.');
            $context->clear();

            return self::FAILURE;
        }
        foreach ($tables as $table) {
            $query = DB::connection('legacy')->table($table);
            $columns = Schema::connection('legacy')->getColumnListing($table);
            if ($companyId !== null && in_array('com_id', $columns, true)) {
                $query->where('com_id', $companyId);
            } elseif ($companyId !== null && $table === 'company' && in_array('id', $columns, true)) {
                $query->where('id', $companyId);
            }
            $source = $query->count();
            $staged = LegacyStagingRecord::where('source_table', $table)->count();
            $corrupt = LegacyStagingRecord::where('source_table', $table)->get()->filter(function ($record) {
                $payload = $record->payload;
                ksort($payload);

                return hash('sha256', json_encode($payload)) !== $record->checksum;
            })->count();
            $mapped = LegacyImportMapping::where('source_table', $table)->count();
            $requiresTransform = in_array($table, ['customer', 'product', 'dsms'], true);
            $ok = $source === $staged && $corrupt === 0 && (! $requiresTransform || $mapped === $source);
            $failed |= ! $ok;
            $rows[] = [$table, $source, $staged, $mapped, $corrupt, $ok ? 'OK' : 'MISMATCH'];
        }$this->table(['Table', 'Source', 'Staged', 'Mapped', 'Corrupt', 'Result'], $rows);
        $context->clear();

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
