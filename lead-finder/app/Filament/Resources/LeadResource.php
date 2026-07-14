<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LeadResource\Pages;
use App\Filament\Resources\LeadResource\RelationManagers;
use App\Models\Lead;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use App\Services\Leads\LeadScoringService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LeadResource extends Resource
{
    protected static ?string $model = Lead::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Leads';
    protected static ?string $navigationGroup = 'CRM';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Azienda')
                    ->schema([
                        TextInput::make('company_name')->required()->maxLength(255),
                        TextInput::make('slug')->maxLength(255),
                        TextInput::make('vat_number')->maxLength(50),
                        TextInput::make('fiscal_code')->maxLength(50),
                        TextInput::make('website')->url()->maxLength(255),
                    ]),

                Section::make('Contatti principali')
                    ->schema([
                        TextInput::make('email')->email()->maxLength(255),
                        TextInput::make('phone')->maxLength(50),
                        TextInput::make('mobile')->maxLength(50),
                        TextInput::make('whatsapp')->maxLength(50),
                        TextInput::make('pec')->maxLength(255),
                    ]),

                Section::make('Localizzazione')
                    ->schema([
                        TextInput::make('region')->maxLength(100),
                        TextInput::make('province')->maxLength(100),
                        TextInput::make('city')->maxLength(100),
                        TextInput::make('address')->maxLength(255),
                    ]),

                Section::make('Profilazione')
                    ->schema([
                        TextInput::make('sector')->maxLength(255),
                        TextInput::make('score')->numeric()->minValue(0)->maxValue(100),
                        Select::make('status')
                            ->options([
                                'new' => 'New',
                                'verified' => 'Verified',
                                'to_contact' => 'To Contact',
                                'contacted' => 'Contacted',
                                'follow_up' => 'Follow Up',
                                'interested' => 'Interested',
                                'not_interested' => 'Not Interested',
                                'customer' => 'Customer',
                                'blacklisted' => 'Blacklisted',
                                'opted_out' => 'Opted Out',
                            ])
                            ->default('new'),
                    ]),

                Section::make('Compliance / contattabilità')
                    ->schema([
                        Toggle::make('email_marketing_allowed')->label('Email Marketing Allowed'),
                        Toggle::make('whatsapp_marketing_allowed')->label('WhatsApp Marketing Allowed'),
                        Toggle::make('phone_contact_allowed')->label('Phone Contact Allowed'),
                        DateTimePicker::make('opted_out_at'),
                        DateTimePicker::make('blacklisted_at'),
                    ]),

                Section::make('Fonte')
                    ->schema([
                        TextInput::make('source_name')->maxLength(255),
                        TextInput::make('source_url')->url()->maxLength(2048),
                        DateTimePicker::make('last_seen_at'),
                        DateTimePicker::make('verified_at'),
                    ]),

                Section::make('Note')
                    ->schema([
                        Textarea::make('notes')->rows(4),
                    ]),
                Textarea::make('compliance_reminder')
                    ->label('Compliance reminder')
                    ->rows(4)
                    ->disabled()
                    ->default('Usa solo dati aziendali pubblici. Non inviare email massive senza consenso. Non usare WhatsApp a freddo salvo contatto pubblicato come commerciale o consenso. Rispetta opt-out e blacklist. Registra sempre fonte e attività.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company_name')->searchable()->sortable(),
                TextColumn::make('city')->sortable(),
                TextColumn::make('province')->sortable(),
                TextColumn::make('sector')->sortable()->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('phone')->searchable(),
                TextColumn::make('mobile')->searchable(),
                TextColumn::make('whatsapp')->searchable(),
                TextColumn::make('score')->sortable(),
                BadgeColumn::make('status')->sortable()->colors([
                    'primary' => 'new',
                    'success' => 'verified',
                    'warning' => 'to_contact',
                    'secondary' => 'contacted',
                    'info' => 'follow_up',
                    'success' => 'interested',
                    'danger' => 'not_interested',
                    'success' => 'customer',
                    'danger' => 'blacklisted',
                    'danger' => 'opted_out',
                ]),
                TextColumn::make('source_name')->sortable(),
                TextColumn::make('last_seen_at')->dateTime()->sortable(),
                TextColumn::make('verified_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('region')->options(fn () => Lead::query()->distinct()->pluck('region', 'region')->filter()->toArray()),
                SelectFilter::make('province')->options(fn () => Lead::query()->distinct()->pluck('province', 'province')->filter()->toArray()),
                SelectFilter::make('city')->options(fn () => Lead::query()->distinct()->pluck('city', 'city')->filter()->toArray()),
                SelectFilter::make('sector')->options(fn () => Lead::query()->distinct()->pluck('sector', 'sector')->filter()->toArray()),
                SelectFilter::make('status')->options([
                    'new' => 'New',
                    'verified' => 'Verified',
                    'to_contact' => 'To Contact',
                    'contacted' => 'Contacted',
                    'follow_up' => 'Follow Up',
                    'interested' => 'Interested',
                    'not_interested' => 'Not Interested',
                    'customer' => 'Customer',
                    'blacklisted' => 'Blacklisted',
                    'opted_out' => 'Opted Out',
                ]),
                Filter::make('score')->label('Score >= 60')->query(fn (Builder $query) => $query->where('score', '>=', 60)),
                Filter::make('has_email')->label('Has Email')->query(fn (Builder $query) => $query->whereNotNull('email')->where('email', '<>', '')),
                Filter::make('has_phone')->label('Has Phone')->query(fn (Builder $query) => $query->whereNotNull('phone')->where('phone', '<>', '')),
                Filter::make('has_mobile')->label('Has Mobile')->query(fn (Builder $query) => $query->whereNotNull('mobile')->where('mobile', '<>', '')),
                Filter::make('has_whatsapp')->label('Has WhatsApp')->query(fn (Builder $query) => $query->whereNotNull('whatsapp')->where('whatsapp', '<>', '')),
                Filter::make('opt_out')->label('Opt-out')->query(fn (Builder $query) => $query->whereNotNull('opted_out_at')),
                Filter::make('blacklist')->label('Blacklist')->query(fn (Builder $query) => $query->whereNotNull('blacklisted_at')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('markVerified')->label('Segna verificato')->action(fn (Lead $record) => $record->update(['status' => 'verified']))->requiresConfirmation(),
                Tables\Actions\Action::make('markToContact')->label('Segna da contattare')->action(fn (Lead $record) => $record->update(['status' => 'to_contact']))->requiresConfirmation(),
                Tables\Actions\Action::make('markContacted')->label('Segna contattato')->action(fn (Lead $record) => $record->update(['status' => 'contacted']))->requiresConfirmation(),
                Tables\Actions\Action::make('markInterested')->label('Segna interessato')->action(fn (Lead $record) => $record->update(['status' => 'interested']))->requiresConfirmation(),
                Tables\Actions\Action::make('markNotInterested')->label('Segna non interessato')->action(fn (Lead $record) => $record->update(['status' => 'not_interested']))->requiresConfirmation(),
                Tables\Actions\Action::make('markFollowUp')->label('Segna follow-up')->action(fn (Lead $record) => $record->update(['status' => 'follow_up']))->requiresConfirmation(),
                Tables\Actions\Action::make('markOptedOut')->label('Segna opt-out')->action(fn (Lead $record) => $record->update(['status' => 'opted_out', 'opted_out_at' => now()]))->requiresConfirmation(),
                Tables\Actions\Action::make('markBlacklisted')->label('Segna blacklist')->action(fn (Lead $record) => $record->update(['status' => 'blacklisted', 'blacklisted_at' => now()]))->requiresConfirmation(),
                Tables\Actions\Action::make('recalculateScore')->label('Ricalcola score')->action(fn (Lead $record) => app(LeadScoringService::class)->updateScore($record))->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('markToContact')->label('Segna da contattare')->action(fn (\Illuminate\Database\Eloquent\Collection $records) => $records->each(fn (Lead $record) => $record->update(['status' => 'to_contact']))),
                    Tables\Actions\BulkAction::make('markBlacklisted')->label('Segna blacklist')->action(fn (\Illuminate\Database\Eloquent\Collection $records) => $records->each(fn (Lead $record) => $record->update(['status' => 'blacklisted', 'blacklisted_at' => now()]))),
                    Tables\Actions\BulkAction::make('recalculateScore')->label('Ricalcola score')->action(fn (\Illuminate\Database\Eloquent\Collection $records) => $records->each(fn (Lead $record) => app(LeadScoringService::class)->updateScore($record))),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LeadContactsRelationManager::class,
            RelationManagers\LeadActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeads::route('/'),
            'create' => Pages\CreateLead::route('/create'),
            'edit' => Pages\EditLead::route('/{record}/edit'),
        ];
    }
}
