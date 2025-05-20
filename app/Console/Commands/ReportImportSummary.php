<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CaseFile;
use App\Models\ExtractedEntry;
use Illuminate\Support\Facades\DB;

class ReportImportSummary extends Command
{
    protected $signature = 'report:import-summary';
    protected $description = 'Zeigt eine vollständige Übersicht über importierte Dateien und extrahierte Fehler';

    public function handle()
    {
        $this->info('📊 Import-Analyse gestartet…');

        // Dateien nach Erweiterung
        $topExtensions = CaseFile::selectRaw('extension, count(*) as dateien, SUM(size_kb) as gesamt_kb')
            ->groupBy('extension')
            ->orderByDesc('dateien')
            ->get();

        $this->table(
            ['Erweiterung', 'Dateien', 'Gesamtgröße (KB)'],
            $topExtensions->map(fn ($e) => [$e->extension ?: '(leer)', $e->dateien, $e->gesamt_kb])
        );

        // Basiswerte
        $gesamtDateien = CaseFile::count();
        $geparst = CaseFile::where('parsed', true)->count();
        $entryCount = ExtractedEntry::count();
        $gesamtMb = round(CaseFile::sum('size_kb') / 1024, 2);

        $this->info("📦 Gesamtdateien: $gesamtDateien");
        $this->info("✅ Geparste Dateien: $geparst");
        $this->info("🧠 Fehler-Einträge insgesamt: $entryCount");
        $this->info("💾 Gesamtspeicher: {$gesamtMb} MB");

        // Fehler nach Typ
        $types = ExtractedEntry::select('entry_type', DB::raw('COUNT(*) as anzahl'))
            ->groupBy('entry_type')
            ->orderByDesc('anzahl')
            ->get();

        $this->info("\n📂 Fehler nach Typ:");
        $this->table(['Typ', 'Anzahl'], $types);

        // Fehler nach Kategorie
        $categories = ExtractedEntry::select('category', DB::raw('COUNT(*) as anzahl'))
            ->groupBy('category')
            ->orderByDesc('anzahl')
            ->get();

        $this->info("📂 Fehler nach Kategorie:");
        $this->table(['Kategorie', 'Anzahl'], $categories);

        // Top 10 Fehlercodes
        $codes = ExtractedEntry::select('code', DB::raw('COUNT(*) as anzahl'))
            ->whereNotNull('code')
            ->groupBy('code')
            ->orderByDesc('anzahl')
            ->limit(10)
            ->get();

        $this->info("📈 Top 10 Fehlercodes:");
        $this->table(['Code', 'Anzahl'], $codes);

        return Command::SUCCESS;
    }
}
