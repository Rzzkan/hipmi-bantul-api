<?php

namespace App\Filament\Resources\BoardMembers\Pages;

use App\Filament\Resources\BoardMembers\BoardMemberResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBoardMember extends EditRecord
{
    protected static string $resource = BoardMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
