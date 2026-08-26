<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\TagRequest;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Tag::class);

        return Inertia::render('catalog/tags/index', [
            'tags' => Tag::query()
                ->withCount('products')
                ->orderBy('name')
                ->get()
                ->map(fn (Tag $tag): array => [
                    'id' => $tag->getKey(),
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                    'productCount' => (int) $tag->getAttribute('products_count'),
                ])
                ->all(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Tag::class);

        return Inertia::render('catalog/tags/create');
    }

    public function store(TagRequest $request): RedirectResponse
    {
        Gate::authorize('create', Tag::class);

        Tag::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Etiket eklendi.']);

        return to_route('tags.index');
    }

    public function edit(Tag $tag): Response
    {
        Gate::authorize('update', $tag);

        return Inertia::render('catalog/tags/edit', [
            'tag' => [
                'id' => $tag->getKey(),
                'name' => $tag->name,
                'slug' => $tag->slug,
            ],
        ]);
    }

    public function update(TagRequest $request, Tag $tag): RedirectResponse
    {
        Gate::authorize('update', $tag);

        $tag->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Etiket güncellendi.']);

        return to_route('tags.index');
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        Gate::authorize('delete', $tag);

        $tag->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Etiket silindi.']);

        return back();
    }
}
