<?php

use App\Marketplaces\Data\MappingContext;
use App\Marketplaces\Support\Exceptions\MappingException;

function mappingContext(): MappingContext
{
    return new MappingContext(
        externalSellerId: '123456',
        categoryIds: ['cat-tshirt' => '411'],
        brandIds: ['brand-acme' => '7891'],
        attributeIds: ['color' => '338'],
        attributeValueIds: ['color' => ['Kırmızı' => '4290']],
        fieldOverrides: ['sku' => ['prefix' => 'KC-']],
        settings: ['shipmentAddressId' => 90210],
    );
}

it('resolves the mappings a mapper needs', function () {
    $context = mappingContext();

    expect($context->remoteCategoryId('cat-tshirt'))->toBe('411')
        ->and($context->remoteBrandId('brand-acme'))->toBe('7891')
        ->and($context->remoteAttributeId('color'))->toBe('338')
        ->and($context->remoteAttributeValueId('color', 'Kırmızı'))->toBe('4290');
});

it('fails loudly on an unresolved category', function () {
    mappingContext()->remoteCategoryId('cat-unknown');
})->throws(MappingException::class, 'No remote category mapping resolved for category [cat-unknown].');

it('fails loudly on an unresolved brand', function () {
    mappingContext()->remoteBrandId('brand-unknown');
})->throws(MappingException::class, 'No remote brand mapping resolved for brand [brand-unknown].');

it('fails loudly on an unresolved attribute', function () {
    mappingContext()->remoteAttributeId('size');
})->throws(MappingException::class, 'No remote attribute mapping resolved for attribute [size].');

it('fails loudly on an unresolved attribute value', function () {
    mappingContext()->remoteAttributeValueId('color', 'Mavi');
})->throws(MappingException::class, 'No remote value mapping resolved for attribute [color] value [Mavi].');

it('exposes tenant field overrides and connection settings', function () {
    $context = mappingContext();

    expect($context->override('sku.prefix'))->toBe('KC-')
        ->and($context->override('sku.suffix', '-TR'))->toBe('-TR')
        ->and($context->setting('shipmentAddressId'))->toBe(90210)
        ->and($context->setting('missing'))->toBeNull();
});
