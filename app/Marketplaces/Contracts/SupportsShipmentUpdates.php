<?php

namespace App\Marketplaces\Contracts;

use App\Marketplaces\Data\MappingContext;
use App\Marketplaces\Data\PushResult;
use App\Marketplaces\Data\ShipmentData;

interface SupportsShipmentUpdates
{
    /**
     * Move the package to the status carried by the shipment.
     */
    public function pushShipmentStatus(ShipmentData $shipment, MappingContext $context): PushResult;

    /**
     * Send the tracking number carried by the shipment.
     */
    public function pushTrackingNumber(ShipmentData $shipment, MappingContext $context): PushResult;
}
