<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TanganyikaTerritoryCoordinatesSeeder extends Seeder
{
    private const FILE_NAMES = [
        'geodata_tanganyika.csv',
        'geodata_tanganyika',
    ];

    public function run(): void
    {
        $path = $this->resolveCsvPath();
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Impossible d'ouvrir le fichier CSV: {$path}");
        }

        $updated = 0;
        $skipped = 0;
        $missingTerritories = [];
        $invalidRows = [];
        $line = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $line++;

            if ($this->isBlankRow($row)) {
                $skipped++;
                continue;
            }

            $codeTerritoire = $this->cleanCell($row[0] ?? '');
            $latitude = $this->cleanCell($row[1] ?? '');
            $longitude = $this->cleanCell($row[2] ?? '');

            if ($line === 1 && strtolower($codeTerritoire) === 'code_territoire') {
                $skipped++;
                continue;
            }

            if ($codeTerritoire === '' || ! is_numeric($latitude) || ! is_numeric($longitude)) {
                $invalidRows[] = $line;
                continue;
            }

            $latitude = (float) $latitude;
            $longitude = (float) $longitude;

            if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
                $invalidRows[] = $line;
                continue;
            }

            $affected = DB::table('territoires')
                ->where('code_territoire', $codeTerritoire)
                ->update([
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ]);

            if ($affected === 0) {
                $missingTerritories[] = $codeTerritoire;
                continue;
            }

            $updated++;
        }

        fclose($handle);

        $this->command?->info("Coordonnees GPS mises a jour pour {$updated} territoire(s).");

        if ($skipped > 0) {
            $this->command?->line("Lignes ignorees: {$skipped}.");
        }

        if ($invalidRows !== []) {
            $this->command?->warn('Lignes invalides ignorees: '.implode(', ', $invalidRows).'.');
        }

        if ($missingTerritories !== []) {
            $this->command?->warn('Territoires introuvables: '.implode(', ', array_unique($missingTerritories)).'.');
        }
    }

    private function resolveCsvPath(): string
    {
        foreach (self::FILE_NAMES as $fileName) {
            $candidates = [
                base_path($fileName),
                dirname(base_path()).DIRECTORY_SEPARATOR.$fileName,
            ];

            foreach ($candidates as $candidate) {
                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }

        throw new RuntimeException(
            'Fichier geodata_tanganyika introuvable. Placez geodata_tanganyika.csv a la racine du projet.'
        );
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function isBlankRow(array $row): bool
    {
        return collect($row)
            ->map(fn ($cell) => $this->cleanCell($cell ?? ''))
            ->filter()
            ->isEmpty();
    }

    private function cleanCell(string $value): string
    {
        return trim(ltrim($value, "\xEF\xBB\xBF"));
    }
}
