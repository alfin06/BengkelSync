<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\Mechanic;
use App\Models\Part;
use App\Models\ServiceItem;
use App\Models\Supplier;
use App\Models\Vehicle;
use App\Services\Cash\CashService;
use App\Services\Cash\CashSetupService;
use App\Services\Customers\VehicleService;
use App\Services\Inventory\StockService;
use App\Services\Tenancy\TenantProvisioner;
use App\Services\Tenancy\TenantProvisioningData;
use App\Services\Users\UserService;
use App\Support\Quantity;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Demo data for local development only. All accounts use the password in PASSWORD
 * and fictional `.test` addresses (see README). Never runs in production.
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'password1';

    public function run(TenantProvisioner $provisioner, UserService $users, TenantContext $context): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The demo seeder must never run in production.');
        }

        $demo = $provisioner->provision(new TenantProvisioningData(
            shopName: 'Bengkel Demo',
            branchName: 'Pusat',
            branchCode: 'DMO',
            ownerName: 'Pemilik Demo',
            ownerEmail: 'owner@demo.test',
            ownerPassword: self::PASSWORD,
        ));

        $context->run($demo->tenant, null, function () use ($demo, $users): void {
            $second = Branch::query()->create(['name' => 'Cabang Timur', 'code' => 'DMT']);
            app(CashSetupService::class)->provisionDefaults($second);
            $main = $demo->branch->id;

            $accounts = [
                ['Kepala Cabang Demo', 'manager@demo.test', RoleName::BranchManager, [$main, $second->id]],
                ['Admin Depan Demo', 'frontdesk@demo.test', RoleName::FrontDesk, [$main]],
                ['Keuangan Demo', 'finance@demo.test', RoleName::Finance, [$main]],
                ['Gudang Demo', 'warehouse@demo.test', RoleName::Warehouse, [$main, $second->id]],
            ];

            foreach ($accounts as [$name, $email, $role, $branchIds]) {
                $users->create(
                    attributes: ['name' => $name, 'email' => $email, 'password' => self::PASSWORD],
                    role: $role,
                    branchIds: $branchIds,
                );
            }
        });

        $context->run($demo->tenant, $demo->branch, function (): void {
            $this->customers();
            $this->serviceCatalog();
            Mechanic::factory()->count(3)->create();
            Mechanic::factory()->inactive()->create();
            $this->inventory();
            $this->cash();
        });

        $east = $context->run($demo->tenant, null, fn () => Branch::query()->where('code', 'DMT')->firstOrFail());
        $context->run($demo->tenant, $east, fn () => Mechanic::factory()->count(2)->create());
    }

    /**
     * Accounts and payment methods like the pilot shop's, with opening balances.
     * (The default "Laci Kasir", "Kas Kecil" and "Tunai" exist already.)
     */
    private function cash(): void
    {
        $setup = app(CashSetupService::class);
        $cash = app(CashService::class);

        $anton = $setup->createAccount(['name' => 'BCA Anton', 'type' => 'bank', 'sort' => 3]);
        $martino = $setup->createAccount(['name' => 'BCA Martino', 'type' => 'bank', 'sort' => 4]);
        $mega = $setup->createAccount(['name' => 'Bank Mega', 'type' => 'bank', 'sort' => 5]);

        $methods = [
            // [name, type, account, surcharge %, needs reference]
            ['QRIS BCA', 'qris', $anton, '0', false],
            ['QRIS Mega', 'qris', $mega, '0', false],
            ['Transfer BCA Anton', 'transfer', $anton, '0', false],
            ['Transfer BCA Martino', 'transfer', $martino, '0', false],
            ['Debit BCA', 'debit', $anton, '0', true],
            ['Debit non-BCA', 'debit', $anton, '3', true],
            ['Kartu Kredit', 'credit_card', $anton, '3', true],
        ];

        foreach ($methods as $sort => [$name, $type, $account, $surcharge, $reference]) {
            $setup->createMethod([
                'name' => $name,
                'type' => $type,
                'cash_account_id' => $account->id,
                'surcharge_percent' => $surcharge,
                'needs_reference' => $reference,
                'sort' => $sort + 2,
            ]);
        }

        [$drawer, $petty] = CashAccount::query()->orderBy('sort')->limit(2)->get()->all();
        $cash->countTo($drawer, 1_500_000, __('stock.opening_balance'));
        $cash->countTo($petty, 300_000, __('stock.opening_balance'));
        $cash->countTo($anton, 25_000_000, __('stock.opening_balance'));
        $cash->transfer($drawer, $petty, 200_000, 'Isi kas kecil');
    }

    /**
     * Example suppliers and parts with stock in the main branch; a few are below their
     * threshold so the low-stock warnings show up.
     */
    private function inventory(): void
    {
        Supplier::factory()->count(3)->create();

        $stock = app(StockService::class);
        $parts = [
            // [sku, name, brand, category, unit, cost, price, stock, threshold]
            ['OLI-HLX-1040', 'Oli Shell Helix HX7 10W-40', 'Shell', 'Oli', 'liter', 62_000, 80_000, '24.5', '8'],
            ['OLI-TOP-0W20', 'Oli Toyota Motor Oil 0W-20', 'Toyota', 'Oli', 'liter', 75_000, 95_000, '6', '8'],
            ['FO-AVZ', 'Filter oli Avanza/Xenia', 'Aspira', 'Filter', 'pcs', 28_000, 40_000, '15', '5'],
            ['FU-AVZ', 'Filter udara Avanza/Xenia', 'Aspira', 'Filter', 'pcs', 45_000, 65_000, '3', '4'],
            ['KR-AVZ-D', 'Kampas rem depan Avanza', 'Bendix', 'Rem', 'set', 185_000, 250_000, '4', '2'],
            ['BUSI-K16', 'Busi Denso K16TT', 'Denso', 'Kelistrikan', 'pcs', 25_000, 35_000, '32', '8'],
            ['AKI-NS40', 'Aki GS Astra NS40ZL', 'GS Astra', 'Kelistrikan', 'pcs', 650_000, 780_000, '2', '2'],
            ['MINYAK-REM', 'Minyak rem Prestone DOT 3', 'Prestone', 'Cairan', 'bottle', 28_000, 40_000, '10', '3'],
            ['COOLANT-1L', 'Air radiator coolant', 'Prestone', 'Cairan', 'liter', 30_000, 45_000, '0', '3'],
            ['V-BELT-AVZ', 'V-belt Avanza', 'Bando', 'Mesin', 'pcs', 95_000, 135_000, '5', '2'],
        ];

        foreach ($parts as [$sku, $name, $brand, $category, $unit, $cost, $price, $qty, $threshold]) {
            $part = Part::query()->create([
                'sku' => $sku,
                'name' => $name,
                'brand' => $brand,
                'category' => $category,
                'unit' => $unit,
                'cost_price' => $cost,
                'sell_price' => $price,
            ]);

            $stock->configure($part, Quantity::parse($threshold), null);
            if ($qty !== '0') {
                $stock->adjustTo($part, Quantity::parse($qty), __('stock.opening_balance'));
            }
        }
    }

    /**
     * A typical small-shop price list (example prices).
     */
    private function serviceCatalog(): void
    {
        $services = [
            ['GO-01', 'Ganti oli mesin', 'Perawatan', 50_000, 30],
            ['GO-02', 'Ganti oli transmisi', 'Perawatan', 75_000, 45],
            ['TU-01', 'Tune up mesin bensin', 'Perawatan', 250_000, 90],
            ['FU-01', 'Ganti filter udara', 'Perawatan', 25_000, 15],
            ['SP-01', 'Spooring', 'Kaki-kaki', 150_000, 45],
            ['BL-01', 'Balancing (4 roda)', 'Kaki-kaki', 100_000, 30],
            ['RM-01', 'Servis rem depan', 'Rem', 120_000, 60],
            ['RM-02', 'Ganti kampas rem', 'Rem', 80_000, 45],
            ['AC-01', 'Isi freon AC', 'AC', 200_000, 45],
            ['AC-02', 'Servis AC lengkap', 'AC', 450_000, 120],
            ['KL-01', 'Cek kelistrikan', 'Kelistrikan', 75_000, 30],
            [null, 'Cuci mesin', null, 60_000, 30],
        ];

        foreach ($services as [$code, $name, $category, $price, $minutes]) {
            ServiceItem::query()->create([
                'code' => $code,
                'name' => $name,
                'category' => $category,
                'default_price' => $price,
                'estimated_minutes' => $minutes,
            ]);
        }
    }

    /**
     * Fictional customers with vehicles (Faker data, never real people).
     */
    private function customers(): void
    {
        $vehicles = app(VehicleService::class);

        Customer::factory()->count(18)->create()->each(function (Customer $customer, int $index) use ($vehicles): void {
            foreach (range(1, $index % 3 === 0 ? 2 : 1) as $ignored) {
                $vehicles->create($customer, Vehicle::factory()->raw());
            }
        });

        $company = Customer::factory()->company()->create(['npwp' => '01.234.567.8-901.000']);
        foreach (range(1, 3) as $ignored) {
            $vehicles->create($company, Vehicle::factory()->raw());
        }
    }
}
