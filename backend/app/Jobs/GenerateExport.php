<?php

namespace App\Jobs;

use App\Mail\ExportReadyMail;
use App\Models\ExportRequest;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $exportId) {}

    public function handle(): void
    {
        $export = ExportRequest::withoutGlobalScopes()->findOrFail($this->exportId);
        $tenant = Tenant::findOrFail($export->tenant_id);
        app(TenantContext::class)->set($tenant);
        try {
            [$contents, $extension, $mime] = $export->type === 'tenant_data' ? [$this->tenantData($tenant), 'json', 'application/json'] : [$this->csv($export), 'csv', 'text/csv'];
            $path = "exports/{$tenant->public_id}/{$export->public_id}.{$extension}";
            Storage::disk(config('filesystems.default'))->put($path, $contents);
            $export->update(['status' => 'completed', 'storage_path' => $path, 'mime_type' => $mime, 'completed_at' => now(), 'failure_message' => null]);
            if ($export->email) {
                Mail::to($export->email)->send(new ExportReadyMail($contents, $extension, $mime));
            }
        } catch (Throwable $exception) {
            $export->update(['status' => 'failed', 'failure_message' => str($exception->getMessage())->limit(500)]);
            throw $exception;
        } finally {
            app(TenantContext::class)->clear();
        }
    }

    private function csv(ExportRequest $export): string
    {
        $stream = fopen('php://temp', 'r+');
        if ($export->type === 'customer_statement') {
            fputcsv($stream, ['date', 'memo', 'debit_minor', 'credit_minor']);
            $customerId = DB::table('customers')->where('tenant_id', $export->tenant_id)->where('public_id', $export->filters['customer_id'])->value('id');
            $accountId = DB::table('ledger_accounts')->where('tenant_id', $export->tenant_id)->where('customer_id', $customerId)->value('id');
            DB::table('journal_lines')->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')->where('journal_lines.tenant_id', $export->tenant_id)->where('ledger_account_id', $accountId)->orderBy('occurred_at')->select('occurred_at', 'memo', 'debit_minor', 'credit_minor')->get()->each(fn ($row) => fputcsv($stream, (array) $row));
        } else {
            fputcsv($stream, ['metric', 'value_minor']);
            fputcsv($stream, ['orders', DB::table('orders')->where('tenant_id', $export->tenant_id)->sum('total_minor')]);
            fputcsv($stream, ['sales', DB::table('sales')->where('tenant_id', $export->tenant_id)->sum('total_minor')]);
            fputcsv($stream, ['approved_expenses', DB::table('expenses')->where('tenant_id', $export->tenant_id)->where('status', 'approved')->sum('amount_minor')]);
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        return $contents;
    }

    private function tenantData(Tenant $tenant): string
    {
        $tables = ['customers', 'employees', 'garments', 'garment_parts', 'orders', 'order_measurements', 'order_status_events', 'inventory_items', 'inventory_movements', 'suppliers', 'purchases', 'purchase_items', 'sales', 'sale_items', 'expenses', 'rentals', 'rental_items', 'attendance_records', 'work_entries', 'payout_batches', 'notification_deliveries', 'journal_entries', 'journal_lines'];
        $data = ['exported_at' => now()->toIso8601String(), 'tenant' => ['public_id' => $tenant->public_id, 'name' => $tenant->name, 'slug' => $tenant->slug]];
        foreach ($tables as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                $data[$table] = DB::table($table)->where('tenant_id', $tenant->id)->get();
            }
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
