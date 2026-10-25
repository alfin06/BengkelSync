<?php

use App\Enums\RoleName;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Services\Invoicing\InvoiceService;
use App\Services\Invoicing\InvoiceShareService;
use App\Services\Payments\PaymentService;
use App\Services\Tenancy\ProvisionedTenant;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A Rp 100.000 counter sale to a customer with a phone number, Rp 40.000 paid in cash.
 */
function sharedSale(ProvisionedTenant $shop): Invoice
{
    $customer = customerIn($shop, ['name' => 'Budi Santoso', 'phone' => '+6281234567890']);
    $part = stockedPart($shop, 1000);
    $invoice = invoices($shop, fn (InvoiceService $service) => $service->createWalkIn($customer, invoiceInput(), [
        ['type' => 'part', 'part_id' => $part->id, 'qty' => 200, 'unit_price' => 50_000],
    ]));

    payments($shop, fn (PaymentService $service) => $service->checkout(
        $invoice,
        now('Asia/Jakarta')->toDateString(),
        [['method' => inTenant($shop->tenant, $shop->branch, fn () => PaymentMethod::query()->with('account')->where('type', 'cash')->firstOrFail()), 'amount' => 40_000]],
        now('Asia/Jakarta')->addWeek()->toDateString(),
    ));

    return $invoice->fresh() ?? $invoice;
}

function linkFor(ProvisionedTenant $shop, Invoice $invoice): string
{
    return inTenant($shop->tenant, $shop->branch, fn () => app(InvoiceShareService::class)->link($invoice));
}

it('prepares a WhatsApp message in Indonesian with the balance and a link, for the customer\'s number', function () {
    $shop = provisionTenant();
    $invoice = sharedSale($shop);

    $this->actingAs(userWithRole($shop, RoleName::FrontDesk))->get(route('invoices.show', $invoice))
        ->assertInertia(fn (Assert $page) => $page
            ->where('share.phone', '6281234567890')
            ->where('share.invoice_message', fn (string $message) => str_contains($message, 'Halo Budi Santoso')
                && str_contains($message, $invoice->number)
                && str_contains($message, 'Total: Rp 100.000')
                && str_contains($message, 'Sudah dibayar: Rp 40.000')
                && str_contains($message, 'Sisa tagihan: Rp 60.000')
                && str_ends_with($message, "Terima kasih,\n".$shop->tenant->name.' ('.$shop->branch->name.')')
                && str_contains($message, '/i/'.$shop->tenant->id.'/'.$shop->branch->id.'/'.$invoice->id.'?expires='))
            ->where('share.receipt_messages', fn ($messages) => count($messages) === 1 && str_contains((string) collect($messages)->first(), 'pembayaran Rp 40.000')));
});

it('opens the invoice from the link without logging in, read-only and not indexed', function () {
    $shop = provisionTenant();
    $invoice = sharedSale($shop);

    $this->get(linkFor($shop, $invoice))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Public')
            ->where('invoice.number', $invoice->number)
            ->where('invoice.outstanding_label', 'Rp 60.000')
            ->where('branch.shop_name', $shop->tenant->name)
            ->has('payments', 1));
});

it('refuses a link that was changed or has expired', function () {
    $shop = provisionTenant();
    $invoice = sharedSale($shop);
    $other = sharedSale($shop);
    $link = linkFor($shop, $invoice);

    // Another invoice id with the same signature.
    $this->get(str_replace('/'.$invoice->id.'?', '/'.$other->id.'?', $link))->assertForbidden();
    // Without the signature.
    $this->get(strtok($link, '?'))->assertForbidden();

    $this->travel(InvoiceShareService::LINK_DAYS + 1)->days();
    $this->get($link)->assertForbidden();
});

it('only shows an invoice of the shop and branch in the signed link', function () {
    $shop = provisionTenant();
    $otherShop = provisionTenant('Bengkel Lain');
    $foreign = sharedSale($otherShop);

    // A validly signed link whose tenant does not own that invoice finds nothing.
    $forged = inTenant($shop->tenant, $shop->branch, fn () => URL::temporarySignedRoute('invoices.public', now()->addDay(), [
        'tenant' => $shop->tenant->id, 'branch' => $shop->branch->id, 'invoice' => $foreign->id,
    ]));

    $this->get($forged)->assertNotFound();
});

it('offers no WhatsApp message for a cancelled invoice', function () {
    $shop = provisionTenant();
    $part = stockedPart($shop, 1000);
    $invoice = invoices($shop, fn (InvoiceService $service) => $service->createWalkIn(null, invoiceInput(), [
        ['type' => 'part', 'part_id' => $part->id, 'qty' => 100, 'unit_price' => 50_000],
    ]));

    $this->actingAs($shop->owner)->get(route('invoices.show', $invoice))->assertInertia(fn (Assert $page) => $page->where('share.phone', null));

    invoices($shop, fn (InvoiceService $service) => $service->void($invoice, 'Salah input'));

    $this->actingAs($shop->owner)->get(route('invoices.show', $invoice))->assertInertia(fn (Assert $page) => $page->where('share', null));
});
