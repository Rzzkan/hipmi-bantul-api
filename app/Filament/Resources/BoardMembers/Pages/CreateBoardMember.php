<?php

namespace App\Filament\Resources\BoardMembers\Pages;

use App\Filament\Resources\BoardMembers\BoardMemberResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBoardMember extends CreateRecord
{
    protected static string $resource = BoardMemberResource::class;
}
