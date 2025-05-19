<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CaseFile;
use App\Models\ExtractedEntry;
use Illuminate\Support\Facades\DB;

class ReportImportSummary extends Command
{
    protected $signature = 'report:import-summary';
    protected $description = 'Zeigt eine Übersicht über importierte Dateien und extrahierte Fehler';

    public function handle()
    {
        $this->info('Import-Analyse gestartet…');

        $byExtension = CaseFile::selectRaw('extension, count(*) as anzahl')
            ->groupBy('extension')
            ->orderByDesc('anzahl')
            ->get();

        $byParsed = CaseFile::selectRaw('parsed, count(*) as anzahl')
            ->groupBy('parsed')
            ->get();

        $entryCount = ExtractedEntry::count();
        $topExtensions = CaseFile::selectRaw('extension, count(*) as dateien, SUM(size_kb) as gesamt_kb')
            ->groupBy('extension')
            ->orderByDesc('dateien')
            ->get();

        $this->table(
            ['Erweiterung', 'Dateien', 'Gesamtgröße (KB)'],
            $topExtensions->map(fn ($e) => [$e->extension, $e->dateien, $e->gesamt_kb])
        );

        $this->info("Gesamtdateien: " . CaseFile::count());
        $this->info("Geparste Dateien: " . ($byParsed->where('parsed', true)->first()?->anzahl ?? 0));
        $this->info("Fehler-Einträge insgesamt: $entryCount");

        return Command::SUCCESS;
    }
}
