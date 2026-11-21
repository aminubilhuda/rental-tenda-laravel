<?php

namespace App\Exports;

use App\Models\Rental;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class RentalsExport implements FromCollection, WithHeadings
{
    public function __construct(private readonly ?string $query = null, private readonly ?string $status = null, private readonly ?string $from = null, private readonly ?string $to = null) {}

    public function collection(): Collection
    {
        return Rental::query()->with(['customer', 'cashier'])
            ->when($this->query, fn ($builder) => $builder->where(fn ($queryBuilder) => $queryBuilder->where('trx_code', 'like', "%{$this->query}%")->orWhereHas('customer', fn ($query) => $query->where('name', 'like', "%{$this->query}%"))))
            ->when($this->status && $this->status !== 'late', fn ($builder) => $builder->where('status', $this->status))
            ->when($this->status === 'late', fn ($builder) => $builder->where('status', 'rented')->whereDate('due_date', '<', today()))
            ->when($this->from, fn ($builder) => $builder->whereDate('rent_date', '>=', $this->from))
            ->when($this->to, fn ($builder) => $builder->whereDate('rent_date', '<=', $this->to))
            ->orderByDesc('rent_date')->get()->map(fn (Rental $rental): array => [
                $rental->trx_code, $rental->customer?->name ?? 'Pelanggan diarsipkan', $rental->customer?->phone ?? '—', $rental->rent_date->format('d/m/Y'), $rental->due_date->format('d/m/Y'), $rental->display_status, $rental->total, $rental->late_fee, $rental->cashier?->name ?? 'Kasir diarsipkan',
            ]);
    }

    public function headings(): array
    {
        return ['ID Transaksi', 'Pelanggan', 'Telepon', 'Tanggal Sewa', 'Tanggal Kembali', 'Status', 'Total Sewa', 'Denda', 'Kasir'];
    }
}
