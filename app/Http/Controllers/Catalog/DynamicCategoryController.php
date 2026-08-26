<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\EvaluateDynamicCategory;
use App\Enums\DynamicCategoryField;
use App\Enums\DynamicCategoryMatchType;
use App\Enums\DynamicCategoryOperator;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\DynamicCategoryRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\DynamicCategory;
use App\Models\DynamicCategoryCondition;
use App\Models\Product;
use App\Models\Tag;
use App\Support\AppTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DynamicCategoryController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', DynamicCategory::class);

        return Inertia::render('catalog/dynamic-categories/index', [
            'categories' => DynamicCategory::query()
                ->with(['conditions'])
                ->withCount('products')
                ->orderBy('name')
                ->get()
                ->map(fn (DynamicCategory $c): array => [
                    'id' => $c->getKey(),
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'matchType' => $c->match_type->value,
                    'matchTypeLabel' => $c->match_type->label(),
                    'description' => $c->description,
                    'conditionCount' => $c->conditions->count(),
                    'productCount' => (int) $c->getAttribute('products_count'),
                    'createdAt' => AppTime::date($c->created_at),
                ])
                ->all(),
            'fields' => array_map(fn (DynamicCategoryField $f): array => [
                'value' => $f->value,
                'label' => $f->label(),
            ], DynamicCategoryField::cases()),
            'operators' => array_map(fn (DynamicCategoryOperator $o): array => [
                'value' => $o->value,
                'label' => $o->label(),
            ], DynamicCategoryOperator::cases()),
            'matchTypes' => array_map(fn (DynamicCategoryMatchType $m): array => [
                'value' => $m->value,
                'label' => $m->label(),
            ], DynamicCategoryMatchType::cases()),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'productCategories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(DynamicCategoryRequest $request, EvaluateDynamicCategory $evaluator): RedirectResponse
    {
        Gate::authorize('create', DynamicCategory::class);

        $category = DB::transaction(function () use ($request): DynamicCategory {
            $category = DynamicCategory::create([
                'name' => $request->string('name')->toString(),
                'slug' => $request->string('slug')->toString(),
                'match_type' => $request->enum('match_type', DynamicCategoryMatchType::class),
                'description' => $request->input('description'),
            ]);

            if ($request->has('conditions')) {
                foreach ($request->input('conditions', []) as $cond) {
                    DynamicCategoryCondition::create([
                        'dynamic_category_id' => $category->getKey(),
                        'field' => $cond['field'],
                        'operator' => $cond['operator'],
                        'value' => $cond['value'] ?? null,
                    ]);
                }
            }

            return $category;
        });

        $matched = $evaluator->execute($category);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Dinamik kategori oluşturuldu ({$matched} ürün eşleşti).",
        ]);

        return back();
    }

    public function show(DynamicCategory $dynamicCategory): Response
    {
        Gate::authorize('view', $dynamicCategory);

        $dynamicCategory->load(['conditions', 'products.brand', 'products.category', 'products.variants']);

        return Inertia::render('catalog/dynamic-categories/show', [
            'category' => [
                'id' => $dynamicCategory->getKey(),
                'name' => $dynamicCategory->name,
                'slug' => $dynamicCategory->slug,
                'matchType' => $dynamicCategory->match_type->value,
                'matchTypeLabel' => $dynamicCategory->match_type->label(),
                'description' => $dynamicCategory->description,
                'createdAt' => AppTime::date($dynamicCategory->created_at),
                'conditions' => $dynamicCategory->conditions->map(fn (DynamicCategoryCondition $c): array => [
                    'id' => $c->getKey(),
                    'field' => $c->field->value,
                    'fieldLabel' => $c->field->label(),
                    'operator' => $c->operator->value,
                    'operatorLabel' => $c->operator->label(),
                    'value' => $c->value,
                ])->all(),
            ],
            'products' => $dynamicCategory->products->map(fn (Product $p): array => [
                'id' => $p->getKey(),
                'name' => $p->name,
                'status' => $p->status->value,
                'statusLabel' => $p->status->label(),
                'brand' => $p->brand?->name,
                'category' => $p->category?->name,
                'variantCount' => $p->variants->count(),
            ])->all(),
            'fields' => array_map(fn (DynamicCategoryField $f): array => [
                'value' => $f->value,
                'label' => $f->label(),
            ], DynamicCategoryField::cases()),
            'operators' => array_map(fn (DynamicCategoryOperator $o): array => [
                'value' => $o->value,
                'label' => $o->label(),
            ], DynamicCategoryOperator::cases()),
            'matchTypes' => array_map(fn (DynamicCategoryMatchType $m): array => [
                'value' => $m->value,
                'label' => $m->label(),
            ], DynamicCategoryMatchType::cases()),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'productCategories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(
        DynamicCategoryRequest $request,
        DynamicCategory $dynamicCategory,
        EvaluateDynamicCategory $evaluator
    ): RedirectResponse {
        Gate::authorize('update', $dynamicCategory);

        DB::transaction(function () use ($request, $dynamicCategory): void {
            $dynamicCategory->update([
                'name' => $request->string('name')->toString(),
                'slug' => $request->string('slug')->toString(),
                'match_type' => $request->enum('match_type', DynamicCategoryMatchType::class),
                'description' => $request->input('description'),
            ]);

            $dynamicCategory->conditions()->delete();

            if ($request->has('conditions')) {
                foreach ($request->input('conditions', []) as $cond) {
                    DynamicCategoryCondition::create([
                        'dynamic_category_id' => $dynamicCategory->getKey(),
                        'field' => $cond['field'],
                        'operator' => $cond['operator'],
                        'value' => $cond['value'] ?? null,
                    ]);
                }
            }
        });

        $matched = $evaluator->execute($dynamicCategory);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Dinamik kategori güncellendi ({$matched} ürün eşleşti).",
        ]);

        return back();
    }

    public function evaluate(DynamicCategory $dynamicCategory, EvaluateDynamicCategory $evaluator): RedirectResponse
    {
        Gate::authorize('update', $dynamicCategory);

        $matched = $evaluator->execute($dynamicCategory);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Koşullar yeniden çalıştırıldı ({$matched} ürün eşleşti).",
        ]);

        return back();
    }

    public function destroy(DynamicCategory $dynamicCategory): RedirectResponse
    {
        Gate::authorize('delete', $dynamicCategory);

        $dynamicCategory->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dinamik kategori silindi.']);

        return to_route('dynamic-categories.index');
    }
}
