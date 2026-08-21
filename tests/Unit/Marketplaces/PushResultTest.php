<?php

use App\Marketplaces\Data\PushResult;

it('treats an accepted push as pending until item results arrive', function () {
    $result = PushResult::accepted('batch-9f3c');

    expect($result->accepted)->toBeTrue()
        ->and($result->remoteBatchId)->toBe('batch-9f3c')
        ->and($result->isPending())->toBeTrue()
        ->and($result->failedReferences())->toBe([]);
});

it('settles once item results are merged in', function () {
    $result = PushResult::accepted('batch-9f3c')->withItemResults([
        'variant-1' => ['accepted' => true, 'code' => null, 'message' => null],
        'variant-2' => ['accepted' => false, 'code' => 'PIM-1001', 'message' => 'Barcode already exists'],
    ]);

    expect($result->isPending())->toBeFalse()
        ->and($result->remoteBatchId)->toBe('batch-9f3c')
        ->and($result->failedReferences())->toBe(['variant-2']);
});

it('records a rejected push', function () {
    $result = PushResult::rejected('HTTP 400: invalid category');

    expect($result->accepted)->toBeFalse()
        ->and($result->isPending())->toBeFalse()
        ->and($result->failureReason)->toBe('HTTP 400: invalid category');
});
