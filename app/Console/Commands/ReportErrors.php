<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ExtractedEntry;
use Illuminate\Support\Facades\DB;

class ReportErrors extends Command
{
    protected $signature = 'report:errors';
    protected $description = 'Zeigt häufigste Fehlercodes, Kategorien und Häufigkeiten';

    public function handle()
    {
        $this->info("Fehleranalyse…");

        $this->info("Top 10 Fehlercodes:");
        $topCodes = ExtractedEntry::select('code', DB::raw('COUNT(*) as anzahl'))
            ->whereNotNull('code')
            ->groupBy('code')
            ->orderByDesc('anzahl')
            ->limit(10)
            ->get();
        $this->table(['Fehlercode', 'Anzahl'], $topCodes);

        $this->info("Fehler nach Kategorie:");
        $byCategory = ExtractedEntry::select('category', DB::raw('COUNT(*) as anzahl'))
            ->groupBy('category')
            ->orderByDesc('anzahl')
            ->get();
        $this->table(['Kategorie', 'Anzahl'], $byCategory);

        return Command::SUCCESS;
    }
}
