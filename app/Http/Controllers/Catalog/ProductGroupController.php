<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ProductGroupRequest;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Support\AppTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProductGroupController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', ProductGroup::class);

        return Inertia::render('catalog/product-groups/index', [
            'groups' => ProductGroup::query()
                ->withCount('products')
                ->orderBy('name')
                ->get()
                ->map(fn (ProductGroup $group): array => [
                    'id' => $group->getKey(),
                    'name' => $group->name,
                    'slug' => $group->slug,
                    'description' => $group->description,
                    'productCount' => (int) $group->getAttribute('products_count'),
                    'createdAt' => AppTime::date($group->created_at),
                ])
                ->all(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', ProductGroup::class);

        return Inertia::render('catalog/product-groups/create', [
            'allProducts' => Product::query()
                ->orderBy('name')
                ->get(['id', 'name', 'status'])
                ->map(fn (Product $p): array => [
                    'id' => $p->getKey(),
                    'name' => $p->name,
                    'status' => $p->status->value,
                ])
                ->all(),
        ]);
    }

    public function store(ProductGroupRequest $request): RedirectResponse
    {
        Gate::authorize('create', ProductGroup::class);

        DB::transaction(function () use ($request): void {
            $group = ProductGroup::create([
                'name' => $request->string('name')->toString(),
                'slug' => $request->string('slug')->toString(),
                'description' => $request->input('description'),
            ]);

            if ($request->has('product_ids')) {
                $ids = array_values(array_map(intval(...), $request->input('product_ids', [])));
                $syncData = [];
                foreach ($ids as $pos => $id) {
                    $syncData[$id] = ['position' => $pos];
                }
                $group->products()->sync($syncData);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ürün grubu eklendi.']);

        return to_route('product-groups.index');
    }

    public function show(ProductGroup $productGroup): Response
    {
        Gate::authorize('view', $productGroup);

        $productGroup->load([
            'products' => fn ($query) => $query->with([
                'brand:id,name',
                'category:id,name',
                'variants:id,product_id',
            ]),
        ]);

        return Inertia::render('catalog/product-groups/show', [
            'group' => [
                'id' => $productGroup->getKey(),
                'name' => $productGroup->name,
                'slug' => $productGroup->slug,
                'description' => $productGroup->description,
                'createdAt' => AppTime::date($productGroup->created_at),
            ],
            'products' => $productGroup->products->map(fn (Product $p): array => [
                'id' => $p->getKey(),
                'name' => $p->name,
                'status' => $p->status->value,
                'statusLabel' => $p->status->label(),
                'brand' => $p->brand?->name,
                'category' => $p->category?->name,
                'variantCount' => $p->variants->count(),
                'position' => (int) ($p->pivot->position ?? 0),
            ])->all(),
            'allProducts' => Product::query()
                ->orderBy('name')
                ->get(['id', 'name', 'status'])
                ->map(fn (Product $p): array => [
                    'id' => $p->getKey(),
                    'name' => $p->name,
                    'status' => $p->status->value,
                ])
                ->all(),
        ]);
    }

    public function edit(ProductGroup $productGroup): Response
    {
        Gate::authorize('update', $productGroup);

        $productGroup->load(['products' => fn ($q) => $q->orderBy('product_group_product.position')]);

        return Inertia::render('catalog/product-groups/edit', [
            'group' => [
                'id' => $productGroup->getKey(),
                'name' => $productGroup->name,
                'slug' => $productGroup->slug,
                'description' => $productGroup->description,
                'productIds' => $productGroup->products->pluck('id')->all(),
            ],
            'allProducts' => Product::query()
                ->orderBy('name')
                ->get(['id', 'name', 'status'])
                ->map(fn (Product $p): array => [
                    'id' => $p->getKey(),
                    'name' => $p->name,
                    'status' => $p->status->value,
                ])
                ->all(),
        ]);
    }

    public function update(ProductGroupRequest $request, ProductGroup $productGroup): RedirectResponse
    {
        Gate::authorize('update', $productGroup);

        DB::transaction(function () use ($request, $productGroup): void {
            $productGroup->update([
                'name' => $request->string('name')->toString(),
                'slug' => $request->string('slug')->toString(),
                'description' => $request->input('description'),
            ]);

            if ($request->has('product_ids')) {
                $ids = array_values(array_map(intval(...), $request->input('product_ids', [])));
                $syncData = [];
                foreach ($ids as $pos => $id) {
                    $syncData[$id] = ['position' => $pos];
                }
                $productGroup->products()->sync($syncData);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ürün grubu güncellendi.']);

        return to_route('product-groups.index');
    }

    public function destroy(ProductGroup $productGroup): RedirectResponse
    {
        Gate::authorize('delete', $productGroup);

        $productGroup->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ürün grubu silindi.']);

        return to_route('product-groups.index');
    }
}
