<?php

namespace App\Filament\Resources\SatCfdiDocumentResource\Pages;

use App\Filament\Resources\SatCfdiDocumentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewSatCfdiDocument extends ViewRecord
{
    protected static string $resource = SatCfdiDocumentResource::class;

    protected static string $view = 'filament.resources.sat-cfdi-document-resource.pages.view-sat-cfdi-document';

    public function getTitle(): string
    {
        return 'Detalle CFDI';
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->record->loadMissing(['company', 'concepts', 'taxes', 'importedBy']);

        return $data;
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->record->loadMissing(['company', 'concepts', 'taxes', 'importedBy']);
    }
}
