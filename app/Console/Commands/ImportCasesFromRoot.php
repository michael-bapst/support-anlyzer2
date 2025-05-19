<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CaseFile;
use App\Models\ExtractedEntry;
use App\Models\SupportCase as CaseModel;
use Illuminate\Support\Str;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use SimpleXMLElement;

class ImportCasesFromRoot extends Command
{
    protected $signature = 'import:cases-from-root {rootPath}';
    protected $description = 'Importiert sysinfo-Cases rekursiv mit Wiederaufnahme & Batchverarbeitung';

    private array $keywords = [
        'ERROR', 'FAIL', 'EXCEPTION', 'ACCESS DENIED', 'CANNOT LOAD',
        'UNABLE TO', 'MISSING', 'CRITICAL', 'FATAL', 'WARN', 'INFO'
    ];

    private int $batchSize = 500;

    public function handle()
    {
        $rootPath = $this->argument('rootPath');

        if (!is_dir($rootPath)) {
            $this->error("❌ Pfad nicht gefunden: $rootPath");
            return Command::FAILURE;
        }

        $dirs = array_filter(glob($rootPath . '/*'), 'is_dir');

        if (empty($dirs)) {
            $this->warn("Keine Unterordner gefunden unter: $rootPath");
            return Command::SUCCESS;
        }

        $this->info("🚀 Starte Import aus: $rootPath");
        foreach ($dirs as $dir) {
            $this->importCase($dir);
        }

        $this->info("✅ Import abgeschlossen.");
        return Command::SUCCESS;
    }

    private function importCase(string $dir)
    {
        $caseName = basename($dir);
        $existing = CaseModel::where('name', $caseName)->first();

        if ($existing) {
            $this->line("⏭️  Case \"$caseName\" existiert bereits – überspringe.");
            return;
        }

        $case = CaseModel::create([
            'name' => $caseName,
            'source_path' => $dir,
            'description' => null,
            'tags' => [],
        ]);

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

        foreach ($files as $file) {
            if ($file->isFile()) {
                $this->importFile($case, $file->getPathname());
            }
        }

        $this->info("📁 Case \"$caseName\" fertig importiert.");
    }

    private function importFile(CaseModel $case, string $path)
    {
        $filename = basename($path);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $relativePath = Str::after($path, $case->source_path . DIRECTORY_SEPARATOR);
        $sizeKb = round(filesize($path) / 1024);
        $hash = md5_file($path);

        $caseFile = CaseFile::where('case_id', $case->id)->where('filename', $filename)->first();
        if ($caseFile?->parsed) {
            $this->line("⏭️  Datei $filename bereits geparst – überspringe.");
            return;
        }

        $this->line("📄 Datei: $relativePath ($sizeKb KB)");

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
            $this->warn("⚠️  Datei konnte nicht geöffnet werden: $path");
            return;
        }

        while (($line = fgets($handle)) !== false) {
            $line = mb_convert_encoding($line, 'UTF-8', 'Windows-1252, ISO-8859-1, ASCII, UTF-8');

            foreach ($this->keywords as $keyword) {
                if (stripos($line, $keyword) !== false) {
                    $batch[] = [
                        'case_file_id' => $caseFile->id,
                        'entry_type' => $this->detectType($line),
                        'code' => $this->extractCode($line),
                        'category' => $this->guessCategory($caseFile),
                        'content' => trim($line),
                        'timestamp' => $this->extractTimestamp($line),
                        'metadata' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $count++;
                    if (count($batch) >= $this->batchSize) {
                        ExtractedEntry::insert($batch);
                        $batch = [];
                    }
                    break;
                }
            }
        }

        fclose($handle);

        if (!empty($batch)) {
            ExtractedEntry::insert($batch);
        }

        $caseFile->parsed = true;
        $caseFile->save();

        $this->line("    ✅ $count relevante Zeilen extrahiert.");
    }

    private function parseXml(CaseFile $caseFile, string $path)
    {
        $raw = @file_get_contents($path);
        if ($raw === false) {
            $this->warn("⚠️  XML konnte nicht geladen werden: $path");
            return;
        }

        $raw = @mb_convert_encoding($raw, 'UTF-8', 'Windows-1252, ISO-8859-1, ASCII, UTF-8');
        $xml = @simplexml_load_string($raw);
        if ($xml === false) {
            $this->warn("⚠️  XML konnte nicht geparst werden: $path");
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
                        $batch[] = [
                            'case_file_id' => $caseFile->id,
                            'entry_type' => $this->detectType($value),
                            'code' => $this->extractCode($value),
                            'category' => $this->guessCategory($caseFile),
                            'content' => trim($value),
                            'timestamp' => $this->extractTimestamp($value),
                            'metadata' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        $count++;
                        if (count($batch) >= $this->batchSize) {
                            ExtractedEntry::insert($batch);
                            $batch = [];
                        }
                        break;
                    }
                }
            }
        }

        if (!empty($batch)) {
            ExtractedEntry::insert($batch);
        }

        $caseFile->parsed = true;
        $caseFile->save();

        $this->line("    ✅ $count XML-Zeilen extrahiert.");
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
}
