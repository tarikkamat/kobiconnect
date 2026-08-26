<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\GeneratePriceListItems;
use App\Enums\AdjustmentType;
use App\Enums\PriceListType;
use App\Enums\PriceRuleField;
use App\Enums\RoundingMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\PriceListItemRequest;
use App\Http\Requests\Catalog\PriceListRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\PriceListRule;
use App\Models\Product;
use App\Models\Tag;
use App\Support\AppTime;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PriceListController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', PriceList::class);

        return Inertia::render('catalog/price-lists/index', [
            'priceLists' => PriceList::query()
                ->withCount('items')
                ->orderBy('name')
                ->get()
                ->map(fn (PriceList $pl): array => [
                    'id' => $pl->getKey(),
                    'name' => $pl->name,
                    'type' => $pl->type->value,
                    'typeLabel' => $pl->type->label(),
                    'sourceCurrency' => $pl->source_currency,
                    'targetCurrency' => $pl->target_currency,
                    'exchangeRate' => $pl->exchange_rate !== null ? (float) $pl->exchange_rate : null,
                    'roundingMethod' => $pl->rounding_method->value,
                    'roundingMethodLabel' => $pl->rounding_method->label(),
                    'isActive' => $pl->is_active,
                    'itemCount' => (int) $pl->getAttribute('items_count'),
                    'description' => $pl->description,
                    'createdAt' => AppTime::date($pl->created_at),
                ])
                ->all(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', PriceList::class);

        return Inertia::render('catalog/price-lists/create', [
            'types' => array_map(fn (PriceListType $t): array => [
                'value' => $t->value,
                'label' => $t->label(),
            ], PriceListType::cases()),
            'roundingMethods' => array_map(fn (RoundingMethod $r): array => [
                'value' => $r->value,
                'label' => $r->label(),
            ], RoundingMethod::cases()),
            'ruleFields' => array_map(fn (PriceRuleField $f): array => [
                'value' => $f->value,
                'label' => $f->label(),
            ], PriceRuleField::cases()),
            'adjustmentTypes' => array_map(fn (AdjustmentType $a): array => [
                'value' => $a->value,
                'label' => $a->label(),
            ], AdjustmentType::cases()),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(PriceListRequest $request, GeneratePriceListItems $generator): RedirectResponse
    {
        Gate::authorize('create', PriceList::class);

        $priceList = DB::transaction(function () use ($request): PriceList {
            $priceList = PriceList::create([
                'name' => $request->string('name')->toString(),
                'type' => $request->enum('type', PriceListType::class),
                'source_currency' => $request->input('source_currency', 'TRY') ?: 'TRY',
                'target_currency' => $request->input('target_currency', 'TRY') ?: 'TRY',
                'exchange_rate' => $request->input('exchange_rate'),
                'rounding_method' => $request->enum('rounding_method', RoundingMethod::class) ?? RoundingMethod::None,
                'is_active' => $request->boolean('is_active', true),
                'description' => $request->input('description'),
            ]);

            if ($request->has('rules')) {
                foreach ($request->input('rules', []) as $pos => $rule) {
                    PriceListRule::create([
                        'price_list_id' => $priceList->getKey(),
                        'field' => $rule['field'],
                        'condition_value' => $rule['condition_value'] ?? null,
                        'adjustment_type' => $rule['adjustment_type'],
                        'adjustment_value' => (float) $rule['adjustment_value'],
                        'position' => (int) ($rule['position'] ?? $pos),
                    ]);
                }
            }

            return $priceList;
        });

        $count = $generator->execute($priceList);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Fiyat listesi oluşturuldu ({$count} kalem fiyatlandı).",
        ]);

        return to_route('price-lists.show', ['priceList' => $priceList->getKey()]);
    }

    public function show(Request $request, PriceList $priceList): Response
    {
        Gate::authorize('view', $priceList);

        $priceList->load('rules');

        $search = trim((string) $request->input('search', ''));

        $items = PriceListItem::query()
            ->where('price_list_id', $priceList->getKey())
            ->with(['variant.product', 'variant.images'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('variant', function ($vQuery) use ($search): void {
                    $vQuery->where('sku', 'ILIKE', '%'.$search.'%')
                        ->orWhere('barcode', 'ILIKE', '%'.$search.'%')
                        ->orWhereHas('product', fn ($pQuery) => $pQuery->where('name', 'ILIKE', '%'.$search.'%'));
                });
            })
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (PriceListItem $item): array => [
                'id' => $item->getKey(),
                'variantId' => $item->variant_id,
                'sku' => $item->variant->sku,
                'barcode' => $item->variant->barcode,
                'productName' => $item->variant->product->name ?? '',
                'listPrice' => (float) $item->list_price,
                'listPriceFormatted' => Money::format((float) $item->list_price, $item->currency),
                'salePrice' => $item->sale_price !== null ? (float) $item->sale_price : null,
                'salePriceFormatted' => $item->sale_price !== null ? Money::format((float) $item->sale_price, $item->currency) : null,
                'currency' => $item->currency,
            ]);

        return Inertia::render('catalog/price-lists/show', [
            'priceList' => [
                'id' => $priceList->getKey(),
                'name' => $priceList->name,
                'type' => $priceList->type->value,
                'typeLabel' => $priceList->type->label(),
                'sourceCurrency' => $priceList->source_currency,
                'targetCurrency' => $priceList->target_currency,
                'exchangeRate' => $priceList->exchange_rate !== null ? (float) $priceList->exchange_rate : null,
                'roundingMethod' => $priceList->rounding_method->value,
                'roundingMethodLabel' => $priceList->rounding_method->label(),
                'isActive' => $priceList->is_active,
                'description' => $priceList->description,
                'createdAt' => AppTime::date($priceList->created_at),
                'rules' => $priceList->rules->map(fn (PriceListRule $r): array => [
                    'id' => $r->getKey(),
                    'field' => $r->field->value,
                    'fieldLabel' => $r->field->label(),
                    'conditionValue' => $r->condition_value,
                    'adjustmentType' => $r->adjustment_type->value,
                    'adjustmentTypeLabel' => $r->adjustment_type->label(),
                    'adjustmentValue' => (float) $r->adjustment_value,
                    'position' => $r->position,
                ])->all(),
            ],
            'items' => $items,
            'filters' => ['search' => $search],
            'types' => array_map(fn (PriceListType $t): array => [
                'value' => $t->value,
                'label' => $t->label(),
            ], PriceListType::cases()),
            'roundingMethods' => array_map(fn (RoundingMethod $r): array => [
                'value' => $r->value,
                'label' => $r->label(),
            ], RoundingMethod::cases()),
            'ruleFields' => array_map(fn (PriceRuleField $f): array => [
                'value' => $f->value,
                'label' => $f->label(),
            ], PriceRuleField::cases()),
            'adjustmentTypes' => array_map(fn (AdjustmentType $a): array => [
                'value' => $a->value,
                'label' => $a->label(),
            ], AdjustmentType::cases()),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(
        PriceListRequest $request,
        PriceList $priceList,
        GeneratePriceListItems $generator
    ): RedirectResponse {
        Gate::authorize('update', $priceList);

        DB::transaction(function () use ($request, $priceList): void {
            $priceList->update([
                'name' => $request->string('name')->toString(),
                'type' => $request->enum('type', PriceListType::class),
                'source_currency' => $request->input('source_currency', 'TRY') ?: 'TRY',
                'target_currency' => $request->input('target_currency', 'TRY') ?: 'TRY',
                'exchange_rate' => $request->input('exchange_rate'),
                'rounding_method' => $request->enum('rounding_method', RoundingMethod::class) ?? RoundingMethod::None,
                'is_active' => $request->boolean('is_active', true),
                'description' => $request->input('description'),
            ]);

            $priceList->rules()->delete();

            if ($request->has('rules')) {
                foreach ($request->input('rules', []) as $pos => $rule) {
                    PriceListRule::create([
                        'price_list_id' => $priceList->getKey(),
                        'field' => $rule['field'],
                        'condition_value' => $rule['condition_value'] ?? null,
                        'adjustment_type' => $rule['adjustment_type'],
                        'adjustment_value' => (float) $rule['adjustment_value'],
                        'position' => (int) ($rule['position'] ?? $pos),
                    ]);
                }
            }
        });

        if ($priceList->type !== PriceListType::Manual) {
            $generator->execute($priceList);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fiyat listesi güncellendi.']);

        return back();
    }

    public function updateItem(
        PriceListItemRequest $request,
        PriceList $priceList,
        PriceListItem $priceListItem
    ): JsonResponse {
        Gate::authorize('update', $priceList);

        $priceListItem->update([
            'list_price' => (float) $request->input('list_price'),
            'sale_price' => $request->input('sale_price') !== null ? (float) $request->input('sale_price') : null,
        ]);

        return response()->json([
            'success' => true,
            'item' => [
                'id' => $priceListItem->getKey(),
                'list_price' => (float) $priceListItem->list_price,
                'list_price_formatted' => Money::format((float) $priceListItem->list_price, $priceListItem->currency),
                'sale_price' => $priceListItem->sale_price !== null ? (float) $priceListItem->sale_price : null,
                'sale_price_formatted' => $priceListItem->sale_price !== null ? Money::format((float) $priceListItem->sale_price, $priceListItem->currency) : null,
            ],
        ]);
    }

    public function regenerate(PriceList $priceList, GeneratePriceListItems $generator): RedirectResponse
    {
        Gate::authorize('update', $priceList);

        $count = $generator->execute($priceList);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Fiyatlar yeniden hesaplandı ({$count} kalem).",
        ]);

        return back();
    }

    public function destroy(PriceList $priceList): RedirectResponse
    {
        Gate::authorize('delete', $priceList);

        $priceList->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fiyat listesi silindi.']);

        return to_route('price-lists.index');
    }
}
