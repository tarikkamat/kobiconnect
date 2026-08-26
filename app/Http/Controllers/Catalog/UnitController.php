<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\UnitRequest;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UnitController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Unit::class);

        return Inertia::render('catalog/units/index', [
            'units' => Unit::query()
                ->withCount('products')
                ->orderBy('name')
                ->get()
                ->map(fn (Unit $unit): array => [
                    'id' => $unit->getKey(),
                    'name' => $unit->name,
                    'shortName' => $unit->short_name,
                    'productCount' => (int) $unit->getAttribute('products_count'),
                ])
                ->all(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Unit::class);

        return Inertia::render('catalog/units/create');
    }

    public function store(UnitRequest $request): RedirectResponse
    {
        Gate::authorize('create', Unit::class);

        Unit::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ürün birimi eklendi.']);

        return to_route('units.index');
    }

    public function edit(Unit $unit): Response
    {
        Gate::authorize('update', $unit);

        return Inertia::render('catalog/units/edit', [
            'unit' => [
                'id' => $unit->getKey(),
                'name' => $unit->name,
                'shortName' => $unit->short_name,
            ],
        ]);
    }

    public function update(UnitRequest $request, Unit $unit): RedirectResponse
    {
        Gate::authorize('update', $unit);

        $unit->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ürün birimi güncellendi.']);

        return to_route('units.index');
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        Gate::authorize('delete', $unit);

        $unit->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ürün birimi silindi.']);

        return back();
    }
}
