<?php

declare(strict_types=1);

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\User;
use Database\Seeders\TenantRoleSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);

    $this->manager = User::factory()->create()->assignRole('Yönetici');
});

it('lists attributes with their values count and values', function (): void {
    $attribute = Attribute::factory()->create([
        'name' => 'Beden',
        'code' => 'beden',
        'type' => AttributeType::Select,
        'is_variant_defining' => true,
    ]);

    AttributeValue::factory()->create([
        'attribute_id' => $attribute->id,
        'value' => 'S',
        'position' => 0,
    ]);
    AttributeValue::factory()->create([
        'attribute_id' => $attribute->id,
        'value' => 'M',
        'position' => 1,
    ]);

    $this->actingAs($this->manager)
        ->get(route('attributes.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('catalog/attributes/index')
            ->has('attributes', 1)
            ->where('attributes.0.name', 'Beden')
            ->where('attributes.0.code', 'beden')
            ->where('attributes.0.valuesCount', 2)
            ->has('attributes.0.values', 2)
            ->has('types')
        );
});

it('creates an attribute with values and auto-derived code', function (): void {
    $this->actingAs($this->manager)
        ->post(route('attributes.store'), [
            'name' => 'Ayakkabı Numarası',
            'type' => 'select',
            'is_variant_defining' => true,
            'values' => ['40', '41', '42', '43'],
        ])
        ->assertRedirect();

    $attribute = Attribute::query()->where('code', 'ayakkabi-numarasi')->firstOrFail();

    expect($attribute->name)->toBe('Ayakkabı Numarası')
        ->and($attribute->type)->toBe(AttributeType::Select)
        ->and($attribute->is_variant_defining)->toBeTrue()
        ->and($attribute->values()->count())->toBe(4)
        ->and($attribute->values()->orderBy('position')->pluck('value')->all())->toBe(['40', '41', '42', '43']);
});

it('refuses a second attribute that collides on code', function (): void {
    Attribute::factory()->create(['name' => 'Renk', 'code' => 'renk']);

    $this->actingAs($this->manager)
        ->post(route('attributes.store'), [
            'name' => 'Renk',
            'code' => 'renk',
            'type' => 'select',
        ])
        ->assertSessionHasErrors('code');

    expect(Attribute::query()->count())->toBe(1);
});

it('updates an attribute and synchronizes values', function (): void {
    $attribute = Attribute::factory()->create([
        'name' => 'Renk',
        'code' => 'renk',
        'type' => AttributeType::Select,
    ]);

    AttributeValue::factory()->create(['attribute_id' => $attribute->id, 'value' => 'Siyah', 'position' => 0]);
    AttributeValue::factory()->create(['attribute_id' => $attribute->id, 'value' => 'Beyaz', 'position' => 1]);

    $this->actingAs($this->manager)
        ->patch(route('attributes.update', $attribute), [
            'name' => 'Ana Renk',
            'code' => 'ana-renk',
            'type' => 'select',
            'is_variant_defining' => true,
            'values' => ['Beyaz', 'Kırmızı', 'Mavi'],
        ])
        ->assertRedirect();

    $attribute->refresh();
    expect($attribute->name)->toBe('Ana Renk')
        ->and($attribute->code)->toBe('ana-renk')
        ->and($attribute->is_variant_defining)->toBeTrue()
        ->and($attribute->values()->count())->toBe(3)
        ->and($attribute->values()->orderBy('position')->pluck('value')->all())->toBe(['Beyaz', 'Kırmızı', 'Mavi']);
});

it('deletes an attribute and cascades its values', function (): void {
    $attribute = Attribute::factory()->create();
    AttributeValue::factory()->count(3)->create(['attribute_id' => $attribute->id]);

    $this->actingAs($this->manager)
        ->delete(route('attributes.destroy', $attribute))
        ->assertRedirect();

    expect(Attribute::query()->count())->toBe(0)
        ->and(AttributeValue::query()->count())->toBe(0);
});

it('blocks attribute writes for a role that may only read', function (): void {
    $viewer = User::factory()->create()->assignRole('Muhasebe');

    $this->actingAs($viewer)
        ->post(route('attributes.store'), ['name' => 'İzinsiz', 'type' => 'text'])
        ->assertForbidden();
});

it('renders attribute create and edit pages', function (): void {
    $attribute = Attribute::factory()->create([
        'name' => 'Beden',
        'code' => 'beden',
        'type' => AttributeType::Select,
    ]);

    $this->actingAs($this->manager)
        ->get(route('attributes.create'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('catalog/attributes/create')->has('types'));

    $this->actingAs($this->manager)
        ->get(route('attributes.edit', $attribute))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('catalog/attributes/edit')
            ->where('attribute.name', 'Beden')
            ->has('types')
        );
});
