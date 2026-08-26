<?php

declare(strict_types=1);

namespace App\Queries\Reports;

use App\Support\AppTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Bes rapor ekraninin ortak girdisi: tarih araligi + istege bagli kanal.
 *
 * Filtre daha once her rapor metodunda yeniden yaziliyordu (dokuz kez ayni
 * `whereBetween` + `when(connectionId)`); birini degistirmek digerlerini
 * sessizce ayristiriyordu.
 */
final readonly class ReportRange
{
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public ?int $connectionId = null,
    ) {}

    /**
     * Varsayilan pencere son 30 gun. Ters verilen aralik hata degil, duzeltilir.
     */
    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'connection' => ['nullable', 'integer'],
        ]);

        $to = isset($validated['to'])
            ? AppTime::parse($validated['to'])->endOfDay()
            : AppTime::now()->endOfDay();

        $from = isset($validated['from'])
            ? AppTime::parse($validated['from'])->startOfDay()
            : $to->subDays(30)->startOfDay();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        return new self($from, $to, isset($validated['connection']) ? (int) $validated['connection'] : null);
    }

    /**
     * @param  Builder  $query  `orders` tablosunu iceren her sorgu
     */
    public function apply(Builder $query): Builder
    {
        return $query
            ->whereBetween('orders.placed_at', [$this->from, $this->to])
            ->when(
                $this->connectionId !== null,
                fn (Builder $inner): Builder => $inner->where('orders.connection_id', $this->connectionId),
            );
    }

    /**
     * Araliga (ve varsa kanala) daraltilmis siparisler.
     */
    public function orders(): Builder
    {
        return $this->apply(DB::table('orders'));
    }

    /**
     * Aralıktaki her gün — trend grafiginin bos gunleri de gostermesi icin.
     *
     * @return list<CarbonImmutable>
     */
    public function days(): array
    {
        $days = [];

        for ($day = $this->from->startOfDay(); $day->lessThanOrEqualTo($this->to); $day = $day->addDay()) {
            $days[] = $day;
        }

        return $days;
    }

    /**
     * @return array{from: string, to: string}
     */
    public function toArray(): array
    {
        return ['from' => $this->from->toDateString(), 'to' => $this->to->toDateString()];
    }
}
