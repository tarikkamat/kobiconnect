<?php

use App\Marketplaces\Data\AttributeData;
use App\Marketplaces\Data\AttributeValueData;
use App\Marketplaces\Data\BrandData;
use App\Marketplaces\Data\MappingContext;
use App\Marketplaces\Trendyol\Mappers\AttributeMapper;
use App\Marketplaces\Trendyol\Mappers\BrandMapper;
use App\Marketplaces\Trendyol\Mappers\CategoryMapper;
use Tests\Fixtures\Trendyol\Fixture;

it('maps a brand row from either envelope', function () {
    $wrapped = Fixture::json('brands')['brands'][0];
    $bare = Fixture::json('brands-by-name')[0];

    expect((new BrandMapper)->toCanonical($wrapped))
        ->toEqual(new BrandData(remoteId: '10', name: 'TrendyolMilla'))
        ->and((new BrandMapper)->toCanonical($bare))
        ->toEqual(new BrandData(remoteId: '40', name: 'TRENDYOLMİLLA'));
});

it('marks a category node without subcategories as the only one that accepts products', function () {
    $node = (new CategoryMapper)->toCanonical(Fixture::json('category-tree')[0]);

    expect($node->remoteId)->toBe('1162')
        ->and($node->parentRemoteId)->toBe('368')
        ->and($node->isLeaf)->toBeFalse()
        ->and($node->children)->toHaveCount(1)
        ->and($node->children[0]->remoteId)->toBe('382')
        ->and($node->children[0]->parentRemoteId)->toBe('1162')
        ->and($node->children[0]->isLeaf)->toBeTrue();
});

it('round trips the category tree', function () {
    $mapper = new CategoryMapper;
    $context = new MappingContext(externalSellerId: '123456');
    $node = $mapper->toCanonical(Fixture::json('category-tree')[0]);

    expect($mapper->toCanonical($mapper->toRemote($node, $context)))->toEqual($node);
});

it('carries the attribute flags product validation is built on', function () {
    $entry = Fixture::json('category-attributes')['categoryAttributes'][0];

    $attribute = (new AttributeMapper)->toCanonical($entry);

    expect($attribute)->toEqual(new AttributeData(
        remoteId: '293',
        name: 'Beden',
        isRequired: true,
        allowsCustomValue: false,
        allowsMultipleValues: false,
        isVarianter: true,
        isSlicer: false,
    ));
});

it('reads either spelling of the attribute value name and trims it', function (string $field) {
    $entry = Fixture::json('category-attributes')['categoryAttributes'][0];
    $entry['attributeValues'] = [['attributeValueId' => 4872, $field => ' Tek Ebat']];

    expect((new AttributeMapper)->toCanonical($entry)->values)
        ->toEqual([new AttributeValueData(value: 'Tek Ebat', remoteId: '4872')]);
})->with(['attributeValue', 'attributeValueName']);

it('maps the documented attribute value page', function () {
    $entry = Fixture::json('category-attributes')['categoryAttributes'][0];
    $entry['attributeValues'] = Fixture::json('category-attribute-values')['content'];

    expect((new AttributeMapper)->toCanonical($entry)->values)
        ->toEqual([new AttributeValueData(value: 'Tek Ebat', remoteId: '4872')]);
});

it('round trips an attribute with its values', function () {
    $mapper = new AttributeMapper;
    $context = new MappingContext(externalSellerId: '123456');

    $entry = Fixture::json('category-attributes')['categoryAttributes'][0];
    $entry['attributeValues'] = Fixture::json('category-attribute-values')['content'];
    $attribute = $mapper->toCanonical($entry);

    expect($mapper->toCanonical($mapper->toRemote($attribute, $context)))->toEqual($attribute);
});

it('survives a response missing every optional field', function () {
    expect((new AttributeMapper)->toCanonical([]))->toEqual(new AttributeData(remoteId: '', name: ''))
        ->and((new CategoryMapper)->toCanonical([])->isLeaf)->toBeTrue()
        ->and((new BrandMapper)->toCanonical([]))->toEqual(new BrandData(remoteId: '', name: ''));
});
