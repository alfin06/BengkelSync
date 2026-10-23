<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Tenancy\TenantProvisioner;
use App\Services\Tenancy\TenantProvisioningData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Creates a shop for the pilot (SPEC section 9: no public sign-up yet).
 *
 * Interactive by default; every value can also be passed as an option for scripts.
 */
final class CreateTenantCommand extends Command
{
    protected $signature = 'tenant:create
        {--shop= : Shop (tenant) name}
        {--branch= : First branch name}
        {--code= : Branch code used in document numbers, e.g. KLM}
        {--timezone=Asia/Jakarta : Branch timezone}
        {--owner= : Owner name}
        {--email= : Owner email (login)}
        {--password= : Owner password; leave empty to generate one}';

    protected $description = 'Create a tenant with its first branch, default roles and owner account';

    public function handle(TenantProvisioner $provisioner): int
    {
        $shop = $this->option('shop') ?: text('Shop name', required: true);
        $branch = $this->option('branch') ?: text('First branch name', default: 'Pusat', required: true);
        $code = $this->option('code') ?: text('Branch code (2-6 letters/digits)', default: Str::upper(Str::substr(Str::slug($shop, ''), 0, 3)), required: true);
        $owner = $this->option('owner') ?: text('Owner name', required: true);
        $email = $this->option('email') ?: text('Owner email', required: true);
        $plainPassword = $this->option('password') ?? ($this->input->isInteractive() ? password('Owner password (leave empty to generate)') : '');
        $generated = $plainPassword === '';
        $plainPassword = $generated ? Str::password(16, symbols: false) : $plainPassword;

        $data = [
            'shop' => $shop,
            'branch' => $branch,
            'code' => Str::upper((string) $code),
            'timezone' => $this->option('timezone'),
            'owner' => $owner,
            'email' => Str::lower((string) $email),
            'password' => $plainPassword,
        ];

        $validator = Validator::make($data, [
            'shop' => ['required', 'string', 'max:255'],
            'branch' => ['required', 'string', 'max:255'],
            'code' => ['required', 'regex:/^[A-Z0-9]{2,6}$/'],
            'timezone' => ['required', 'timezone:all'],
            'owner' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:'.User::class.',email'],
            'password' => ['required', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $result = $provisioner->provision(new TenantProvisioningData(
            shopName: $data['shop'],
            branchName: $data['branch'],
            branchCode: $data['code'],
            ownerName: $data['owner'],
            ownerEmail: $data['email'],
            ownerPassword: $data['password'],
            timezone: (string) $data['timezone'],
        ));

        $this->info("Tenant \"{$result->tenant->name}\" created with branch {$result->branch->code}.");
        $this->line("Owner login: {$result->owner->email}");

        if ($generated) {
            $this->warn("Generated password (shown only once): {$plainPassword}");
        }

        return self::SUCCESS;
    }
}
