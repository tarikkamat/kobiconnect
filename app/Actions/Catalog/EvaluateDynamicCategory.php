<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Enums\DynamicCategoryField;
use App\Enums\DynamicCategoryMatchType;
use App\Enums\DynamicCategoryOperator;
use App\Models\DynamicCategory;
use App\Models\DynamicCategoryCondition;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final class EvaluateDynamicCategory
{
    /**
     * Koşulları değerlendirir ve dinamik kategori ürün tablosunu günceller.
     *
     * @return int Eklenen/eşleşen ürün sayısı
     */
    public function execute(DynamicCategory $category): int
    {
        $category->load('conditions');

        if ($category->conditions->isEmpty()) {
            $category->products()->sync([]);

            return 0;
        }

        $query = Product::query();
        $isAny = $category->match_type === DynamicCategoryMatchType::Any;

        if ($isAny) {
            $query->where(function (Builder $sub) use ($category): void {
                foreach ($category->conditions as $idx => $condition) {
                    if ($idx === 0) {
                        $sub->where(fn (Builder $q) => $this->applyCondition($q, $condition));
                    } else {
                        $sub->orWhere(fn (Builder $q) => $this->applyCondition($q, $condition));
                    }
                }
            });
        } else {
            foreach ($category->conditions as $condition) {
                $this->applyCondition($query, $condition);
            }
        }

        $productIds = $query->pluck('id')->all();
        $category->products()->sync($productIds);

        return count($productIds);
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applyCondition(Builder $query, DynamicCategoryCondition $condition): void
    {
        $field = $condition->field;
        $op = $condition->operator;
        $rawVal = $condition->value;
        $val = is_string($rawVal) ? trim($rawVal) : $rawVal;

        match ($field) {
            DynamicCategoryField::Name => $this->applyTextCondition($query, 'name', $op, (string) $val),
            DynamicCategoryField::Brand => $this->applyBrandCondition($query, $op, (string) $val),
            DynamicCategoryField::Category => $this->applyCategoryCondition($query, $op, (string) $val),
            DynamicCategoryField::Tag => $this->applyTagCondition($query, $op, (string) $val),
            DynamicCategoryField::Price => $this->applyPriceCondition($query, $op, $val),
            DynamicCategoryField::VariantValue => $this->applyVariantValueCondition($query, $op, (string) $val),
            DynamicCategoryField::OnSale => $this->applyOnSaleCondition($query, $op, $val),
            DynamicCategoryField::CreatedAt => $this->applyCreatedAtCondition($query, $op, $val),
            DynamicCategoryField::Campaign => $this->applyTextCondition($query, 'description', $op, (string) $val),
        };
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applyTextCondition(Builder $query, string $column, DynamicCategoryOperator $op, string $val): void
    {
        match ($op) {
            DynamicCategoryOperator::Contains => $query->where($column, 'ILIKE', '%'.$val.'%'),
            DynamicCategoryOperator::NotContains => $query->where($column, 'NOT ILIKE', '%'.$val.'%'),
            DynamicCategoryOperator::Equals => $query->where($column, '=', $val),
            DynamicCategoryOperator::NotEquals => $query->where($column, '!=', $val),
            default => $query->where($column, 'ILIKE', '%'.$val.'%'),
        };
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applyBrandCondition(Builder $query, DynamicCategoryOperator $op, string $val): void
    {
        match ($op) {
            DynamicCategoryOperator::Contains => $query->whereHas('brand', fn (Builder $q) => $q->where('name', 'ILIKE', '%'.$val.'%')),
            DynamicCategoryOperator::NotContains => $query->whereDoesntHave('brand', fn (Builder $q) => $q->where('name', 'ILIKE', '%'.$val.'%')),
            DynamicCategoryOperator::Equals => $query->whereHas('brand', fn (Builder $q) => is_numeric($val) ? $q->where('id', (int) $val)->orWhere('name', '=', $val) : $q->where('name', '=', $val)),
            DynamicCategoryOperator::NotEquals => $query->whereDoesntHave('brand', fn (Builder $q) => is_numeric($val) ? $q->where('id', (int) $val)->orWhere('name', '=', $val) : $q->where('name', '=', $val)),
            default => $query->whereHas('brand', fn (Builder $q) => $q->where('name', 'ILIKE', '%'.$val.'%')),
        };
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applyCategoryCondition(Builder $query, DynamicCategoryOperator $op, string $val): void
    {
        match ($op) {
            DynamicCategoryOperator::Contains => $query->whereHas('category', fn (Builder $q) => $q->where('name', 'ILIKE', '%'.$val.'%')),
            DynamicCategoryOperator::NotContains => $query->whereDoesntHave('category', fn (Builder $q) => $q->where('name', 'ILIKE', '%'.$val.'%')),
            DynamicCategoryOperator::Equals => $query->whereHas('category', fn (Builder $q) => is_numeric($val) ? $q->where('id', (int) $val)->orWhere('name', '=', $val) : $q->where('name', '=', $val)),
            DynamicCategoryOperator::NotEquals => $query->whereDoesntHave('category', fn (Builder $q) => is_numeric($val) ? $q->where('id', (int) $val)->orWhere('name', '=', $val) : $q->where('name', '=', $val)),
            default => $query->whereHas('category', fn (Builder $q) => $q->where('name', 'ILIKE', '%'.$val.'%')),
        };
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applyTagCondition(Builder $query, DynamicCategoryOperator $op, string $val): void
    {
        match ($op) {
            DynamicCategoryOperator::Contains, DynamicCategoryOperator::Equals => $query->whereHas('tags', fn (Builder $q) => $q->where('name', 'ILIKE', '%'.$val.'%')->orWhere('slug', '=', $val)),
            DynamicCategoryOperator::NotContains, DynamicCategoryOperator::NotEquals => $query->whereDoesntHave('tags', fn (Builder $q) => $q->where('name', 'ILIKE', '%'.$val.'%')->orWhere('slug', '=', $val)),
            default => $query->whereHas('tags', fn (Builder $q) => $q->where('name', 'ILIKE', '%'.$val.'%')),
        };
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applyPriceCondition(Builder $query, DynamicCategoryOperator $op, mixed $val): void
    {
        match ($op) {
            DynamicCategoryOperator::Equals => $query->whereHas('variants.prices', fn (Builder $q) => $q->where('list_price', '=', (float) $val)),
            DynamicCategoryOperator::NotEquals => $query->whereHas('variants.prices', fn (Builder $q) => $q->where('list_price', '!=', (float) $val)),
            DynamicCategoryOperator::GreaterThan => $query->whereHas('variants.prices', fn (Builder $q) => $q->where('list_price', '>', (float) $val)),
            DynamicCategoryOperator::LessThan => $query->whereHas('variants.prices', fn (Builder $q) => $q->where('list_price', '<', (float) $val)),
            DynamicCategoryOperator::Between => is_array($val) && count($val) >= 2
                ? $query->whereHas('variants.prices', fn (Builder $q) => $q->whereBetween('list_price', [(float) $val[0], (float) $val[1]]))
                : null,
            default => null,
        };
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applyVariantValueCondition(Builder $query, DynamicCategoryOperator $op, string $val): void
    {
        match ($op) {
            DynamicCategoryOperator::Contains, DynamicCategoryOperator::Equals => $query->whereHas('variants', fn (Builder $q) => $q->whereRaw('attributes::text ILIKE ?', ['%'.$val.'%'])),
            DynamicCategoryOperator::NotContains, DynamicCategoryOperator::NotEquals => $query->whereDoesntHave('variants', fn (Builder $q) => $q->whereRaw('attributes::text ILIKE ?', ['%'.$val.'%'])),
            default => $query->whereHas('variants', fn (Builder $q) => $q->whereRaw('attributes::text ILIKE ?', ['%'.$val.'%'])),
        };
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applyOnSaleCondition(Builder $query, DynamicCategoryOperator $op, mixed $val): void
    {
        $isTrue = $val === true || $val === '1' || $val === 1 || $val === 'true';

        if ($isTrue) {
            $query->whereHas('variants.prices', fn (Builder $q) => $q->whereNotNull('sale_price')->whereRaw('sale_price < list_price'));
        } else {
            $query->whereDoesntHave('variants.prices', fn (Builder $q) => $q->whereNotNull('sale_price')->whereRaw('sale_price < list_price'));
        }
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applyCreatedAtCondition(Builder $query, DynamicCategoryOperator $op, mixed $val): void
    {
        match ($op) {
            DynamicCategoryOperator::Before => $query->where('created_at', '<', Carbon::parse((string) $val)),
            DynamicCategoryOperator::After => $query->where('created_at', '>', Carbon::parse((string) $val)),
            DynamicCategoryOperator::Between => is_array($val) && count($val) >= 2
                ? $query->whereBetween('created_at', [Carbon::parse((string) $val[0]), Carbon::parse((string) $val[1])])
                : null,
            default => null,
        };
    }
}
