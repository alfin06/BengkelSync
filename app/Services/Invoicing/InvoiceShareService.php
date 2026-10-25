<?php

namespace App\Services\Invoicing;

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\PaymentReceipt;
use App\Support\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Sending an invoice or kwitansi to the customer over WhatsApp: a ready message for a
 * wa.me link (staff press send in their own WhatsApp) with a link to the invoice.
 *
 * The link is a signed URL that expires after LINK_DAYS: it cannot be guessed or
 * changed, needs no login, and shows only that one invoice (read-only). Messages are
 * always in Indonesian because they go to the customer.
 */
class InvoiceShareService
{
    public const LINK_DAYS = 30;

    private const LOCALE = 'id';

    public function __construct(private readonly TenantContext $context) {}

    public function link(Invoice $invoice): string
    {
        return URL::temporarySignedRoute('invoices.public', now()->addDays(self::LINK_DAYS), [
            'tenant' => $invoice->tenant_id,
            'branch' => $invoice->branch_id,
            'invoice' => $invoice->id,
        ]);
    }

    /**
     * The customer's number as wa.me wants it (62812…), or null for "Pelanggan umum".
     */
    public function phone(Invoice $invoice): ?string
    {
        $phone = $invoice->customer?->phone;

        return $phone === null ? null : ltrim($phone, '+');
    }

    /**
     * Needs customer, branch and workOrder.vehicle loaded.
     */
    public function invoiceMessage(Invoice $invoice): string
    {
        $lines = [
            $this->greeting($invoice),
            '',
            $this->t('invoice_intro', ['shop' => $this->shopName($invoice)]),
            $this->t('invoice_number', ['number' => $invoice->number]),
            $this->t('date', ['date' => $this->date($invoice->issue_date)]),
        ];

        if ($invoice->workOrder !== null) {
            $lines[] = $this->t('vehicle', ['vehicle' => $invoice->workOrder->vehicle->plate_number]);
        }

        $lines[] = $this->t('total', ['amount' => Money::format($invoice->total)]);
        $lines = [...$lines, ...$this->balance($invoice), '', $this->t('link', ['url' => $this->link($invoice)])];

        return $this->finish($lines, $invoice);
    }

    /**
     * Thanks for a payment on the invoice (same loaded relations as invoiceMessage()),
     * with the receipt's amount that is not cancelled. The receipt needs payments loaded.
     */
    public function receiptMessage(Invoice $invoice, PaymentReceipt $receipt): string
    {
        $lines = [
            $this->greeting($invoice),
            '',
            $this->t('receipt_intro', [
                'amount' => Money::format($receipt->activeAmount()),
                'number' => $invoice->number,
                'receipt' => $receipt->number,
                'date' => $this->date($receipt->paid_on),
            ]),
            ...$this->balance($invoice),
            '',
            $this->t('link', ['url' => $this->link($invoice)]),
        ];

        return $this->finish($lines, $invoice);
    }

    /**
     * The shop and branch details printed on the invoice. The logo is embedded as a data
     * URI so the customer's page can show it without access to the private disk route.
     *
     * @return array{shop_name: string, name: string, address: string|null, phone: string|null, npwp: string|null, footer: string|null, logo_url: string|null}
     */
    public function branchDetails(Branch $branch, bool $embedLogo = false): array
    {
        return [
            'shop_name' => $this->context->requireTenant()->name,
            'name' => $branch->name,
            'address' => $branch->address,
            'phone' => $branch->phone,
            'npwp' => $branch->npwp,
            'footer' => $branch->invoice_footer,
            'logo_url' => $embedLogo ? $this->embeddedLogo($branch) : $branch->logoUrl(),
        ];
    }

    private function embeddedLogo(Branch $branch): ?string
    {
        $disk = Storage::disk(config('filesystems.private_disk'));

        if ($branch->logo_path === null || ! $disk->exists($branch->logo_path)) {
            return null;
        }

        return 'data:'.$disk->mimeType($branch->logo_path).';base64,'.base64_encode((string) $disk->get($branch->logo_path));
    }

    /**
     * @return list<string>
     */
    private function balance(Invoice $invoice): array
    {
        if ($invoice->outstanding() <= 0) {
            return [$this->t('paid_in_full')];
        }

        $lines = [];
        if ($invoice->amount_paid > 0) {
            $lines[] = $this->t('paid', ['amount' => Money::format($invoice->amount_paid)]);
        }
        $lines[] = $this->t('outstanding', ['amount' => Money::format($invoice->outstanding())]);
        if ($invoice->due_date !== null) {
            $lines[] = $this->t('due', ['date' => $this->date($invoice->due_date)]);
        }

        return $lines;
    }

    /**
     * @param  list<string>  $lines
     */
    private function finish(array $lines, Invoice $invoice): string
    {
        $footer = trim((string) $invoice->branch->invoice_footer);

        return implode("\n", [...$lines, ...($footer === '' ? [] : ['', $footer]), '', $this->t('thanks'), $this->shopName($invoice)]);
    }

    private function greeting(Invoice $invoice): string
    {
        return $invoice->customer === null ? $this->t('greeting_general') : $this->t('greeting', ['name' => $invoice->customer->name]);
    }

    private function shopName(Invoice $invoice): string
    {
        return $this->context->requireTenant()->name.' ('.$invoice->branch->name.')';
    }

    private function date(Carbon $date): string
    {
        return $date->copy()->locale(self::LOCALE)->translatedFormat('j M Y');
    }

    /**
     * @param  array<string, string>  $replace
     */
    private function t(string $key, array $replace = []): string
    {
        return __('whatsapp.'.$key, $replace, self::LOCALE);
    }
}
