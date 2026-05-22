<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class SalidasCluster extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static ?string $navigationLabel = 'Salidas';
    protected static ?string $navigationGroup = 'Salidas';
    protected static ?int $navigationSort = 10;
    protected static ?string $clusterBreadcrumb = 'Salidas';
}
