<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalog;

use App\Enums\AttributeType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\AttributeRequest;
use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AttributeController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Attribute::class);

        return Inertia::render('catalog/attributes/index', [
            'attributes' => Attribute::query()
                ->with(['values' => fn ($q) => $q->orderBy('position')->orderBy('id')])
                ->withCount('values')
                ->orderBy('name')
                ->get()
                ->map(fn (Attribute $attribute): array => [
                    'id' => $attribute->getKey(),
                    'name' => $attribute->name,
                    'code' => $attribute->code,
                    'type' => $attribute->type->value,
                    'isVariantDefining' => $attribute->is_variant_defining,
                    'valuesCount' => (int) $attribute->getAttribute('values_count'),
                    'values' => $attribute->values->map(fn (AttributeValue $v): array => [
                        'id' => $v->getKey(),
                        'value' => $v->value,
                        'position' => $v->position,
                    ])->all(),
                ])
                ->all(),
            'types' => array_map(fn (AttributeType $t): array => [
                'value' => $t->value,
                'label' => match ($t) {
                    AttributeType::Text => 'Metin (Serbest Yazı)',
                    AttributeType::Number => 'Sayısal Değer',
                    AttributeType::Boolean => 'Mantıksal (Evet / Hayır)',
                    AttributeType::Select => 'Seçim Kutusu (Tekli)',
                    AttributeType::MultiSelect => 'Çoklu Seçim',
                },
            ], AttributeType::cases()),
        ]);
    }

    public function store(AttributeRequest $request): RedirectResponse
    {
        Gate::authorize('create', Attribute::class);

        DB::transaction(function () use ($request): void {
            /** @var Attribute $attribute */
            $attribute = Attribute::create([
                'name' => $request->string('name')->toString(),
                'code' => $request->string('code')->toString(),
                'type' => $request->input('type'),
                'is_variant_defining' => $request->boolean('is_variant_defining'),
            ]);

            $values = $this->extractCleanValues($request->input('values'));
            foreach ($values as $position => $val) {
                AttributeValue::create([
                    'attribute_id' => $attribute->getKey(),
                    'value' => $val,
                    'position' => $position,
                ]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Nitelik eklendi.']);

        return back();
    }

    public function update(AttributeRequest $request, Attribute $attribute): RedirectResponse
    {
        Gate::authorize('update', $attribute);

        DB::transaction(function () use ($request, $attribute): void {
            $attribute->update([
                'name' => $request->string('name')->toString(),
                'code' => $request->string('code')->toString(),
                'type' => $request->input('type'),
                'is_variant_defining' => $request->boolean('is_variant_defining'),
            ]);

            if ($request->has('values')) {
                $cleanValues = $this->extractCleanValues($request->input('values'));
                $existingValues = $attribute->values()->get()->keyBy('value');

                // Silinecek olanlar
                $attribute->values()
                    ->whereNotIn('value', $cleanValues)
                    ->delete();

                // Eklenecek veya güncellenecek olanlar
                foreach ($cleanValues as $position => $val) {
                    if ($existing = $existingValues->get($val)) {
                        if ($existing->position !== $position) {
                            $existing->update(['position' => $position]);
                        }
                    } else {
                        AttributeValue::create([
                            'attribute_id' => $attribute->getKey(),
                            'value' => $val,
                            'position' => $position,
                        ]);
                    }
                }
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Nitelik güncellendi.']);

        return back();
    }

    public function destroy(Attribute $attribute): RedirectResponse
    {
        Gate::authorize('delete', $attribute);

        $attribute->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Nitelik silindi.']);

        return back();
    }

    /**
     * @return list<string>
     */
    private function extractCleanValues(mixed $rawValues): array
    {
        if (! is_array($rawValues)) {
            return [];
        }

        $cleaned = [];
        foreach ($rawValues as $val) {
            $str = trim((string) $val);
            if ($str !== '' && ! in_array($str, $cleaned, true)) {
                $cleaned[] = $str;
            }
        }

        return $cleaned;
    }
}
