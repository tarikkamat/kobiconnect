<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\BrandRequest;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Brand::class);

        return Inertia::render('catalog/brands/index', [
            'brands' => Brand::query()
                ->withCount('products')
                ->orderBy('name')
                ->get()
                ->map(fn (Brand $brand): array => [
                    'id' => $brand->getKey(),
                    'name' => $brand->name,
                    'slug' => $brand->slug,
                    'productCount' => (int) $brand->getAttribute('products_count'),
                ])
                ->all(),
        ]);
    }

    public function store(BrandRequest $request): RedirectResponse
    {
        Gate::authorize('create', Brand::class);

        Brand::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Marka eklendi.']);

        return back();
    }

    public function update(BrandRequest $request, Brand $brand): RedirectResponse
    {
        Gate::authorize('update', $brand);

        $brand->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Marka güncellendi.']);

        return back();
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        Gate::authorize('delete', $brand);

        $brand->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Marka silindi.']);

        return back();
    }
}
