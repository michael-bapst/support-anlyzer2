<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CaseFile;
use App\Models\ExtractedEntry;
use App\Models\ErrorPattern;
use App\Models\SupportCase as CaseModel;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Illuminate\Support\Facades\Log;

class ImportCasesFromRoot extends Command
{
    protected $signature = 'import:cases-from-root {rootPath} {--dry-run}';
    protected $description = 'Importiert Cases mit Log-Analyse, Fehler-Matching, Finder und optionalem DryRun';

    private array $keywords = [
        'ERROR', 'FAIL', 'EXCEPTION', 'ACCESS DENIED', 'CANNOT LOAD',
        'UNABLE TO', 'MISSING', 'CRITICAL', 'FATAL', 'WARN', 'INFO'
    ];

    private int $batchSize = 500;
    private $patterns;

    public function handle()
    {
        $startTime = microtime(true);
        $rootPath = $this->argument('rootPath');

        if (!is_dir($rootPath)) {
            $this->log("❌ Pfad nicht gefunden: $rootPath");
            return Command::FAILURE;
        }

        $this->patterns = ErrorPattern::all();
        $dirs = array_filter(glob($rootPath . '/*'), 'is_dir');

        if (empty($dirs)) {
            $this->log("Keine Unterordner gefunden unter: $rootPath");
            return Command::SUCCESS;
        }

        $this->log("🚀 Starte Import aus: $rootPath");
        foreach ($dirs as $dir) {
            $this->importCase($dir);
        }

        $duration = microtime(true) - $startTime;
        $this->log("⏱️ Dauer: " . round($duration, 2) . " Sekunden");

        $this->logDbStats();

        $this->log("✅ Import abgeschlossen.");
        return Command::SUCCESS;
    }

    private function importCase(string $dir)
    {
        $caseName = basename($dir);
        $this->log("📁 Starte Verarbeitung von Case: \"$caseName\"");

        $case = CaseModel::firstOrCreate([
            'name' => $caseName,
        ], [
            'source_path' => $dir,
            'description' => null,
            'tags' => [],
        ]);

        $finder = new Finder();
        $finder->files()->in($dir)->ignoreUnreadableDirs()->followLinks();

        foreach ($finder as $file) {
            $this->importFile($case, $file->getRealPath());
        }

        $this->log("📁 Case \"$caseName\" fertig verarbeitet.");
    }

    private function importFile(CaseModel $case, string $path)
    {
        $filename = basename($path);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $relativePath = Str::after($path, $case->source_path . DIRECTORY_SEPARATOR);
        $sizeKb = round(filesize($path) / 1024);
        $hash = md5_file($path);

        $caseFile = CaseFile::where('case_id', $case->id)->where('filename', $filename)->first();

        if ($caseFile && $caseFile->parsed && $caseFile->hash === $hash) {
            $this->log("⏭️  Datei $filename unverändert – überspringe.");
            return;
        }

        $this->log("📄 Datei: $relativePath ($sizeKb KB)");

        $caseFile = CaseFile::updateOrCreate([
            'case_id' => $case->id,
            'filename' => $filename,
        ], [
            'path' => $relativePath,
            'extension' => $extension,
            'size_kb' => $sizeKb,
            'hash' => $hash,
            'parsed' => false,
        ]);

        if (!$this->option('dry-run')) {
            ExtractedEntry::where('case_file_id', $caseFile->id)->delete();
        }

        if (in_array($extension, ['log', 'txt'])) {
            $this->parsePlainText($caseFile, $path);
        }

        if ($extension === 'xml') {
            $this->parseXml($caseFile, $path);
        }
    }

    private function parsePlainText(CaseFile $caseFile, string $path)
    {
        $handle = fopen($path, 'r');
        $batch = [];
        $count = 0;

        if (!$handle) {
            $this->log("⚠️  Datei konnte nicht geöffnet werden: $path");
            return;
        }

        while (($line = fgets($handle)) !== false) {
            $line = mb_convert_encoding($line, 'UTF-8', 'Windows-1252, ISO-8859-1, ASCII, UTF-8');

            foreach ($this->keywords as $keyword) {
                if (stripos($line, $keyword) !== false) {
                    $pattern = $this->matchErrorPattern($line);

                    $batch[] = [
                        'case_file_id' => $caseFile->id,
                        'entry_type' => $this->detectType($line),
                        'code' => $pattern?->code ?? $this->extractCode($line),
                        'category' => $pattern?->category ?? $this->guessCategory($caseFile),
                        'content' => trim($line),
                        'timestamp' => $this->extractTimestamp($line),
                        'metadata' => null,
                        'severity' => $pattern?->severity,
                        'pattern_id' => $pattern?->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $count++;
                    if (count($batch) >= $this->batchSize) {
                        if (!$this->option('dry-run')) {
                            ExtractedEntry::insert($batch);
                        }
                        $batch = [];
                    }
                    break;
                }
            }
        }

        fclose($handle);
        if (!empty($batch) && !$this->option('dry-run')) {
            ExtractedEntry::insert($batch);
        }

        if (!$this->option('dry-run')) {
            $caseFile->parsed = true;
            $caseFile->save();
        }

        $this->log("    ✅ $count relevante Zeilen extrahiert.");
    }

    private function parseXml(CaseFile $caseFile, string $path)
    {
        $raw = @file_get_contents($path);
        if ($raw === false) {
            $this->log("⚠️  XML konnte nicht geladen werden: $path");
            return;
        }

        $raw = @mb_convert_encoding($raw, 'UTF-8', 'Windows-1252, ISO-8859-1, ASCII, UTF-8');
        $xml = @simplexml_load_string($raw);
        if ($xml === false) {
            $this->log("⚠️  XML konnte nicht geparst werden: $path");
            return;
        }

        $json = json_decode(json_encode($xml), true);
        $flat = collect($json)->flatten();
        $batch = [];
        $count = 0;

        foreach ($flat as $value) {
            if (is_string($value)) {
                $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252, ISO-8859-1, ASCII, UTF-8');
                foreach ($this->keywords as $keyword) {
                    if (stripos($value, $keyword) !== false) {
                        $pattern = $this->matchErrorPattern($value);

                        $batch[] = [
                            'case_file_id' => $caseFile->id,
                            'entry_type' => $this->detectType($value),
                            'code' => $pattern?->code ?? $this->extractCode($value),
                            'category' => $pattern?->category ?? $this->guessCategory($caseFile),
                            'content' => trim($value),
                            'timestamp' => $this->extractTimestamp($value),
                            'metadata' => null,
                            'severity' => $pattern?->severity,
                            'pattern_id' => $pattern?->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        $count++;
                        if (count($batch) >= $this->batchSize) {
                            if (!$this->option('dry-run')) {
                                ExtractedEntry::insert($batch);
                            }
                            $batch = [];
                        }
                        break;
                    }
                }
            }
        }

        if (!empty($batch) && !$this->option('dry-run')) {
            ExtractedEntry::insert($batch);
        }

        if (!$this->option('dry-run')) {
            $caseFile->parsed = true;
            $caseFile->save();
        }

        $this->log("    ✅ $count XML-Zeilen extrahiert.");
    }

    private function matchErrorPattern(string $line): ?ErrorPattern
    {
        foreach ($this->patterns as $pattern) {
            if ($pattern->keyword && stripos($line, $pattern->keyword) !== false) {
                return $pattern;
            }
        }
        return null;
    }

    private function extractCode(string $line): ?string
    {
        return preg_match('/0x[0-9A-Fa-f]{8}/', $line, $match) ? $match[0] : null;
    }

    private function detectType(string $line): string
    {
        $line = strtolower($line);
        return str_contains($line, 'error') || str_contains($line, 'fail') || str_contains($line, 'exception') ? 'error'
            : (str_contains($line, 'warn') ? 'warning' : 'info');
    }

    private function extractTimestamp(string $line): ?string
    {
        return preg_match('/\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}/', $line, $match) ? $match[0] : null;
    }

    private function guessCategory(CaseFile $file): string
    {
        $map = [
            'CBS.log' => 'System',
            'setupapi.dev.log' => 'Treiber',
            'eventlog.xml' => 'WindowsEvent',
            'file_protection.xml' => 'VSS',
            'rules.xml' => 'Defender',
            'netlog.txt' => 'Netzwerk',
        ];

        foreach ($map as $needle => $category) {
            if (Str::contains($file->filename, $needle)) {
                return $category;
            }
        }

        if (Str::contains($file->path, 'VssInfo')) return 'VSS';
        if (Str::contains($file->path, 'drivers')) return 'Treiber';
        if (Str::contains($file->path, 'network')) return 'Netzwerk';

        return 'Unbekannt';
    }

    private function logDbStats(): void
    {
        $caseCount = CaseModel::count();
        $fileCount = CaseFile::count();
        $entryCount = ExtractedEntry::count();

        $this->log("📊 DB-Status:");
        $this->log("  🔹 Cases: $caseCount");
        $this->log("  🔹 Dateien: $fileCount");
        $this->log("  🔹 Fehlerzeilen: $entryCount");
    }

    private function log(string $message): void
    {
        $this->line($message);
        Log::channel('daily')->info('[Import] ' . $message);
    }
}
