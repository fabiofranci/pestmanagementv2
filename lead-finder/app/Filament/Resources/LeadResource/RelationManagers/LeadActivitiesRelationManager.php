<?php

namespace App\Filament\Resources\LeadResource\RelationManagers;

use App\Models\LeadActivity;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LeadActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $recordTitleAttribute = 'type';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('type')
                    ->options([
                        'call' => 'Call',
                        'email' => 'Email',
                        'whatsapp' => 'WhatsApp',
                        'note' => 'Note',
                        'meeting' => 'Meeting',
                        'follow_up' => 'Follow Up',
                        'status_change' => 'Status Change',
                    ])
                    ->required(),
                Select::make('direction')
                    ->options([
                        'inbound' => 'Inbound',
                        'outbound' => 'Outbound',
                    ]),
                Select::make('outcome')
                    ->options([
                        'no_answer' => 'No Answer',
                        'interested' => 'Interested',
                        'callback' => 'Callback',
                        'not_interested' => 'Not Interested',
                        'wrong_number' => 'Wrong Number',
                        'sent' => 'Sent',
                        'replied' => 'Replied',
                        'meeting_booked' => 'Meeting Booked',
                    ]),
                Textarea::make('content')->rows(4),
                DateTimePicker::make('scheduled_at'),
                DateTimePicker::make('completed_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')->sortable(),
                TextColumn::make('direction')->sortable(),
                TextColumn::make('outcome')->sortable(),
                TextColumn::make('content')->limit(50),
                TextColumn::make('scheduled_at')->dateTime()->sortable(),
                TextColumn::make('completed_at')->dateTime()->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
