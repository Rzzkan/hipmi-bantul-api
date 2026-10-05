<?php

namespace App\Filament\Resources\Registrations\Tables;

use App\Models\Registration;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class RegistrationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Masuk')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('type')->label('Jenis')->badge()->formatStateUsing(fn ($state) => Registration::TYPES[$state] ?? $state)
                    ->color(fn ($state) => ['membership' => 'warning', 'event' => 'info', 'program' => 'primary'][$state] ?? 'gray'),
                TextColumn::make('name')->label('Nama')->searchable()
                    ->description(fn (Registration $r) => $r->company_name),
                TextColumn::make('phone')->label('WA')->searchable()
                    ->url(fn (Registration $r) => 'https://wa.me/'.preg_replace('/^0/', '62', preg_replace('/\D/', '', $r->phone)), true)
                    ->color('success'),
                TextColumn::make('target')->label('Agenda / Program')
                    ->state(fn (Registration $r) => $r->event?->title ?? $r->program?->title ?? '—')->limit(30),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state) => Registration::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'][$state] ?? 'gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')->label('Jenis')->options(Registration::TYPES),
                SelectFilter::make('status')->options(Registration::STATUSES),
                SelectFilter::make('event_id')->label('Agenda')->relationship('event', 'title'),
                SelectFilter::make('program_id')->label('Program')->relationship('program', 'title'),
            ])
            ->recordActions([
                Action::make('approve')->label('Terima')->icon('heroicon-o-check')->color('success')
                    ->visible(fn (Registration $r) => $r->status !== Registration::STATUS_APPROVED)
                    ->action(fn (Registration $r) => $r->update(['status' => Registration::STATUS_APPROVED])),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('exportCsv')
                        ->label('Export CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(fn (Collection $records) => static::exportCsv($records)),
                    BulkAction::make('approveAll')->label('Terima terpilih')->icon('heroicon-o-check')
                        ->action(fn (Collection $records) => $records->each->update(['status' => Registration::STATUS_APPROVED])),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected static function exportCsv(Collection $records)
    {
        $records->loadMissing('event', 'program');

        return response()->streamDownload(function () use ($records) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Tanggal', 'Jenis', 'Agenda/Program', 'Nama', 'Email', 'HP', 'Usaha', 'Bidang', 'Jabatan', 'Usia', 'Alamat', 'Status']);
            foreach ($records as $r) {
                fputcsv($out, [
                    $r->created_at->format('Y-m-d H:i'), Registration::TYPES[$r->type] ?? $r->type,
                    $r->event?->title ?? $r->program?->title, $r->name, $r->email, $r->phone, $r->company_name,
                    $r->business_field, $r->position, $r->age, $r->address, Registration::STATUSES[$r->status] ?? $r->status,
                ]);
            }
            fclose($out);
        }, 'pendaftar-hipmi-bantul-'.now()->format('Ymd-His').'.csv');
    }
}
