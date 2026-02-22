<?php

// php artisan make:model Invoice -m

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Invoice extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'invoice_number',
        'company_id', 'client_id',
        'company_name', 'company_gst_number', 'company_state', 'company_state_code',
        'client_name', 'client_email', 'client_address', 'client_gst_number', 'client_state', 'client_state_code',
        'invoice_date', 'due_date',
        'currency',
        'subtotal', 'discount_amount', 'taxable_amount',
        'cgst_amount', 'sgst_amount', 'igst_amount', 'gst_amount',
        'total_amount', 'amount_paid', 'amount_due',
        'invoice_type', 'status', 'supply_type',
        'payment_terms',
        'bank_account_name', 'bank_account_number', 'bank_ifsc_code',
        'notes', 'terms_and_conditions',
        'reference_invoice_id',
    ];

    protected $casts = [
        'invoice_date'    => 'date',
        'due_date'        => 'date',
        'subtotal'        => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'taxable_amount'  => 'decimal:2',
        'cgst_amount'     => 'decimal:2',
        'sgst_amount'     => 'decimal:2',
        'igst_amount'     => 'decimal:2',
        'gst_amount'      => 'decimal:2',
        'total_amount'    => 'decimal:2',
        'amount_paid'     => 'decimal:2',
        'amount_due'      => 'decimal:2',
    ];

    // ── Activity Log Configuration ─────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Invoice was {$eventName}");
    }

    // ── Relationships ──────────────────────────────────────────────────────

    /** Invoice belongs to a Company (the seller) */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Invoice belongs to a Client (the buyer) */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class)->orderBy('sort_order');
    }

    public function referenceInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'reference_invoice_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForClient(Builder $query, int $clientId): Builder
    {
        return $query->where('client_id', $clientId);
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereIn('status', ['sent', 'partial'])
            ->where('due_date', '<', now()->toDateString());
    }

    // ── Business Logic ─────────────────────────────────────────────────────

    public static function generateNumber(): string
    {
        return 'INV-' . now()->timestamp . random_int(10, 99);
    }

    /** Determine supply type from company vs client state codes */
    public function determineSupplyType(): string
    {
        return $this->company_state_code === $this->client_state_code
            ? 'intra_state'
            : 'inter_state';
    }

    /** Recalculate all totals from line items */
    public function recalculateTotals(): void
    {
        $this->load('lineItems');

        $subtotal  = $this->lineItems->sum('amount');
        $cgst      = $this->lineItems->sum('cgst_amount');
        $sgst      = $this->lineItems->sum('sgst_amount');
        $igst      = $this->lineItems->sum('igst_amount');
        $gstTotal  = $cgst + $sgst + $igst;

        $this->update([
            'subtotal'       => $subtotal,
            'taxable_amount' => $subtotal,
            'cgst_amount'    => $cgst,
            'sgst_amount'    => $sgst,
            'igst_amount'    => $igst,
            'gst_amount'     => $gstTotal,
            'total_amount'   => $subtotal + $gstTotal,
            'amount_due'     => ($subtotal + $gstTotal) - $this->amount_paid,
        ]);
    }

    /** Record a payment and update invoice status */
    public function recordPayment(float $amount): void
    {
        $paid   = $this->amount_paid + $amount;
        $due    = $this->total_amount - $paid;
        $status = match (true) {
            $due <= 0 => 'paid',
            $paid > 0 => 'partial',
            default   => $this->status,
        };

        $this->update([
            'amount_paid' => $paid,
            'amount_due'  => max(0, $due),
            'status'      => $status,
        ]);
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, ['sent', 'partial']) && $this->due_date?->isPast();
    }
}
