<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Nreljed\MarketingModule\Enums\ContactStatus;
use Nreljed\MarketingModule\Models\Contact;

class SyncMarketingCustomers extends Command
{
    protected $signature = 'marketing:sync-customers {--dry-run : Show what would be synced without writing}';

    protected $description = 'Sync shop customers (users with the customer role) into the marketing audience as contacts with purchase data.';

    public function handle(): int
    {
        $customers = User::whereHas('roles', fn ($q) => $q->where('slug', 'customer'))->get();

        if ($customers->isEmpty()) {
            $this->warn('No customer users found.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $created = 0;
        $updated = 0;

        // Aggregate purchase data per user/email for enrichment.
        $orderStats = DB::table('orders')
            ->selectRaw('user_id, customer_email, COUNT(*) as order_count, COALESCE(SUM(total),0) as total_spent, MAX(created_at) as last_order_at')
            ->whereNotNull('user_id')
            ->orWhereNotNull('customer_email')
            ->groupBy('user_id', 'customer_email')
            ->get()
            ->keyBy(fn ($row) => $row->user_id ?: strtolower((string) $row->customer_email));

        foreach ($customers as $user) {
            if (! $user->email) {
                continue;
            }

            $key = $user->id ?: strtolower($user->email);
            $stats = $orderStats->get($key) ?? $orderStats->get(strtolower($user->email));

            $payload = [
                'user_id' => $user->id,
                'first_name' => $user->first_name ?: $user->name,
                'last_name' => $user->last_name,
                'phone' => $user->phone,
                'status' => $user->email_verified_at
                    ? ContactStatus::Active
                    : ContactStatus::Lead,
                'source' => 'shop',
            ];

            if ($stats) {
                $payload['total_spent'] = (float) $stats->total_spent;
                $payload['order_count'] = (int) $stats->order_count;
                $payload['last_order_at'] = $stats->last_order_at;
            }

            if ($dryRun) {
                $this->line("[dry-run] {$user->email} -> ".json_encode($payload));

                continue;
            }

            $contact = Contact::where('email', $user->email)->first();

            if ($contact) {
                // Don't overwrite purchase data if already present and fresher? Keep accumulating logic simple.
                $contact->fill($payload)->save();
                $updated++;
            } else {
                Contact::create(array_merge(['email' => $user->email], $payload));
                $created++;
            }
        }

        $this->info("Synced {$customers->count()} customers: {$created} created, {$updated} updated.");

        return self::SUCCESS;
    }
}
