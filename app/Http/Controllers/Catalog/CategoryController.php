<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Category::class);

        return Inertia::render('catalog/categories/index', [
            // `path` ata id'lerinin materyalize yolu ("1/4/9"); ona gore siralamak
            // agac sirasi verir. Metinsel siralama 1/10'u 1/2'den once koyar —
            // derinlik girintisi dogru kaldigi surece sorun degil.
            'categories' => Category::query()
                ->withCount('products')
                ->orderBy('path')
                ->get()
                ->map(fn (Category $category): array => [
                    'id' => $category->getKey(),
                    'name' => $category->name,
                    'parentId' => $category->parent_id,
                    'depth' => substr_count($category->path, '/'),
                    'path' => $category->path,
                    'productCount' => (int) $category->products_count,
                ])
                ->all(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Category::class);

        return Inertia::render('catalog/categories/create', [
            'categories' => Category::query()
                ->orderBy('path')
                ->get()
                ->map(fn (Category $category): array => [
                    'id' => $category->getKey(),
                    'name' => $category->name,
                    'depth' => substr_count($category->path, '/'),
                ])
                ->all(),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        Gate::authorize('create', Category::class);

        $parentId = $request->integer('parent_id') ?: null;

        $category = Category::create([
            'parent_id' => $parentId,
            'name' => $request->string('name')->toString(),
            'path' => '',
        ]);

        $parent = $parentId === null ? null : Category::find($parentId);

        $category->update([
            'path' => $parent === null
                ? (string) $category->getKey()
                : $parent->path.'/'.$category->getKey(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kategori eklendi.']);

        return to_route('categories.index');
    }

    /**
     * ponytail: ust kategori degistirilemez — torunlarin `path` degerlerini
     * yeniden yazmak gerekir. Tasima ihtiyaci dogdugunda ayri bir aksiyon olur.
     */
    public function edit(Category $category): Response
    {
        Gate::authorize('update', $category);

        return Inertia::render('catalog/categories/edit', [
            'category' => [
                'id' => $category->getKey(),
                'name' => $category->name,
                'parentId' => $category->parent_id,
            ],
        ]);
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        Gate::authorize('update', $category);

        $category->update(['name' => $request->string('name')->toString()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kategori güncellendi.']);

        return to_route('categories.index');
    }

    public function destroy(Category $category): RedirectResponse
    {
        Gate::authorize('delete', $category);

        $category->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kategori silindi.']);

        return back();
    }
}
