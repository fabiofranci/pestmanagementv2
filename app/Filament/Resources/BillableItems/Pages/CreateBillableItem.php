<?php

namespace App\Filament\Resources\BillableItems\Pages;

use App\Filament\Resources\BillableItems\BillableItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBillableItem extends CreateRecord
{
    protected static string $resource = BillableItemResource::class;
}
