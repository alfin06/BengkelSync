<?php

use App\Enums\RoleName;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Permissions\RoleProvisioner;
use Illuminate\Support\Facades\Hash;

function createTenantOptions(array $overrides = []): array
{
    return [
        '--shop' => 'Bengkel Kalimas Lumas',
        '--branch' => 'Pusat',
        '--code' => 'klm',
        '--owner' => 'Pak Pemilik',
        '--email' => 'Owner@Kalimas.test',
        '--password' => 'rahasia123',
        '--no-interaction' => true,
        ...$overrides,
    ];
}

it('creates a tenant with its branch, roles and owner', function () {
    $this->artisan('tenant:create', createTenantOptions())
        ->expectsOutputToContain('Bengkel Kalimas Lumas')
        ->assertSuccessful();

    $tenant = Tenant::query()->where('name', 'Bengkel Kalimas Lumas')->firstOrFail();
    $owner = User::query()->where('email', 'owner@kalimas.test')->firstOrFail();

    expect($tenant->slug)->toBe('bengkel-kalimas-lumas')
        ->and($owner->tenant_id)->toBe($tenant->id)
        ->and($owner->all_branches)->toBeTrue()
        ->and(Hash::check('rahasia123', $owner->password))->toBeTrue();

    inTenant($tenant, null, function () use ($owner) {
        expect($owner->hasRole(RoleName::Owner->value))->toBeTrue()
            ->and($owner->branches()->pluck('code')->all())->toBe(['KLM']);
    });
});

it('generates a password when none is given and shows it once', function () {
    $this->artisan('tenant:create', createTenantOptions(['--password' => '']))
        ->expectsOutputToContain('Generated password')
        ->assertSuccessful();
});

it('validates the input', function (array $overrides) {
    $this->artisan('tenant:create', createTenantOptions($overrides))->assertFailed();

    expect(Tenant::query()->count())->toBe(0);
})->with([
    'bad branch code' => [['--code' => 'k-l']],
    'bad email' => [['--email' => 'not-an-email']],
    'weak password' => [['--password' => 'short']],
    'bad timezone' => [['--timezone' => 'Mars/Base']],
]);

it('rejects an email that already has an account', function () {
    $this->artisan('tenant:create', createTenantOptions())->assertSuccessful();

    $this->artisan('tenant:create', createTenantOptions(['--shop' => 'Bengkel Lain']))->assertFailed();

    expect(Tenant::query()->count())->toBe(1);
});

it('rolls everything back when a step fails', function () {
    $this->mock(RoleProvisioner::class)
        ->shouldReceive('provision')
        ->andThrow(new RuntimeException('boom'));

    expect(fn () => $this->artisan('tenant:create', createTenantOptions())->run())
        ->toThrow(RuntimeException::class);

    expect(Tenant::query()->count())->toBe(0)
        ->and(User::query()->count())->toBe(0);
});
