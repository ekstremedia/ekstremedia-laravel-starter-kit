<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Seeds the default customer so the starter kit runs as a "single workspace"
 * app out of the box. The app always runs multi-tenant and needs at least one
 * customer to be reachable — this seeder guarantees that. The slug comes from
 * config so deployments can override it with the TENANCY_DEFAULT_CUSTOMER env
 * var.
 *
 * Creating the row fires stancl's TenantCreated pipeline, which in turn
 * provisions the `tenant<id>` Postgres schema and runs migrations in
 * `database/migrations/tenant/`.
 */
class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $slug = (string) config('tenancy.default_customer_slug', 'default');

        Tenant::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => config('app.name', 'Default customer'),
                'status' => 'active',
            ],
        );
    }
}
