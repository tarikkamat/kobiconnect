<?php

use App\Marketplaces\Data\ClaimData;
use App\Marketplaces\Data\ClaimItemData;
use App\Marketplaces\Data\Enums\CanonicalClaimStatus;
use App\Marketplaces\Data\Enums\CanonicalListingStatus;
use App\Marketplaces\Data\Enums\CanonicalOrderStatus;
use App\Marketplaces\Data\Enums\CanonicalQuestionStatus;
use App\Marketplaces\Data\Enums\SyncState;

it('keeps a payment approval order out of fulfilment', function () {
    $status = CanonicalOrderStatus::PendingPayment;

    expect($status->allowsFulfilment())->toBeFalse()
        ->and($status->isTerminal())->toBeFalse()
        ->and($status->reservesStock())->toBeTrue();
});

it('allows fulfilment only while the package is being prepared', function () {
    $fulfillable = array_filter(
        CanonicalOrderStatus::cases(),
        fn (CanonicalOrderStatus $status): bool => $status->allowsFulfilment(),
    );

    expect(array_values($fulfillable))->toBe([
        CanonicalOrderStatus::Created,
        CanonicalOrderStatus::Picking,
        CanonicalOrderStatus::Invoiced,
        CanonicalOrderStatus::Unpacked,
    ]);
});

it('frees reserved stock on terminal statuses', function () {
    expect(CanonicalOrderStatus::Delivered->isTerminal())->toBeTrue()
        ->and(CanonicalOrderStatus::Cancelled->reservesStock())->toBeFalse()
        ->and(CanonicalOrderStatus::Shipped->reservesStock())->toBeTrue();
});

it('lets the seller act on a claim item only while it waits for action', function () {
    $actionable = array_filter(
        CanonicalClaimStatus::cases(),
        fn (CanonicalClaimStatus $status): bool => $status->isActionable(),
    );

    expect(array_values($actionable))->toBe([CanonicalClaimStatus::WaitingAction]);
});

it('derives the claim status from its items', function () {
    $claim = new ClaimData(
        remoteId: 'claim-1',
        orderRemoteId: 'order-1',
        openedAt: new DateTimeImmutable('2026-08-19 10:00:00'),
        items: [
            new ClaimItemData('item-1', CanonicalClaimStatus::Accepted, 'Accepted'),
            new ClaimItemData('item-2', CanonicalClaimStatus::WaitingAction, 'WaitingInAction'),
        ],
    );

    expect($claim->status())->toBe(CanonicalClaimStatus::WaitingAction)
        ->and((new ClaimData('claim-2', 'order-2', new DateTimeImmutable))->status())->toBeNull();
});

it('answers a question only while it awaits an answer', function () {
    $answerable = array_filter(
        CanonicalQuestionStatus::cases(),
        fn (CanonicalQuestionStatus $status): bool => $status->isAnswerable(),
    );

    expect(array_values($answerable))->toBe([CanonicalQuestionStatus::AwaitingAnswer]);
});

it('keeps an operation open until its result is read back', function () {
    expect(SyncState::Pending->isOpen())->toBeTrue()
        ->and(SyncState::InFlight->isOpen())->toBeTrue()
        ->and(SyncState::Completed->isOpen())->toBeFalse()
        ->and(SyncState::Failed->isOpen())->toBeFalse();
});

it('knows which listings are approved and editable', function () {
    expect(CanonicalListingStatus::PendingApproval->isApproved())->toBeFalse()
        ->and(CanonicalListingStatus::OnSale->isApproved())->toBeTrue()
        ->and(CanonicalListingStatus::Locked->isEditable())->toBeFalse()
        ->and(CanonicalListingStatus::Archived->isEditable())->toBeTrue();
});
