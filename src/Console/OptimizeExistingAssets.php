<?php

namespace Sennik\AssetOptimizer\Console;

use Illuminate\Console\Command;
use Sennik\AssetOptimizer\Optimizer;
use Statamic\Facades\AssetContainer;

class OptimizeExistingAssets extends Command
{
    protected $signature = 'asset-optimizer:run
        {--container=* : Beperk tot specifieke container-handle(s)}
        {--dry-run : Toon resultaat zonder bestanden te wijzigen}
        {--force : Negeer ook de min_size_bytes drempel}';

    protected $description = 'Pas Asset Optimizer toe op alle bestaande assets (resize + recompress).';

    public function handle(Optimizer $optimizer): int
    {
        $containerHandles = (array) $this->option('container');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $containers = empty($containerHandles)
            ? AssetContainer::all()
            : AssetContainer::all()->filter(fn ($c) => in_array($c->handle(), $containerHandles, true));

        if ($containers->isEmpty()) {
            $this->error('Geen containers gevonden.');
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('[DRY RUN] Geen bestanden worden gewijzigd.');
        }

        $totals = [
            'scanned' => 0,
            'changed' => 0,
            'skipped' => 0,
            'errors' => 0,
            'bytes_before' => 0,
            'bytes_after' => 0,
        ];

        foreach ($containers as $container) {
            $assets = $container->assets();
            $count = $assets->count();

            if ($count === 0) {
                $this->line("Container <comment>{$container->handle()}</comment>: geen assets.");
                continue;
            }

            $this->line("Container <comment>{$container->handle()}</comment>: {$count} assets.");
            $bar = $this->output->createProgressBar($count);
            $bar->start();

            foreach ($assets as $asset) {
                $totals['scanned']++;
                $result = $optimizer->optimize($asset, force: $force, dryRun: $dryRun);

                if ($result->error) {
                    $totals['errors']++;
                    $this->newLine();
                    $this->error("  ✗ {$asset->path()} — {$result->error}");
                } elseif ($result->changed) {
                    $totals['changed']++;
                    $totals['bytes_before'] += $result->originalBytes;
                    $totals['bytes_after'] += $result->newBytes;

                    if ($this->getOutput()->isVerbose()) {
                        $this->newLine();
                        $this->line(sprintf(
                            '  → %s: %dx%d (%s) → %dx%d (%s)',
                            $asset->path(),
                            $result->originalWidth, $result->originalHeight, $this->formatBytes($result->originalBytes),
                            $result->newWidth, $result->newHeight, $this->formatBytes($result->newBytes)
                        ));
                    }
                } else {
                    $totals['skipped']++;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        }

        $this->newLine();
        $this->info('Klaar.');
        $this->table(
            ['Stat', 'Waarde'],
            [
                ['Bekeken', $totals['scanned']],
                ['Aangepast', $totals['changed']],
                ['Overgeslagen', $totals['skipped']],
                ['Fouten', $totals['errors']],
                ['Totaal voor', $this->formatBytes($totals['bytes_before'])],
                ['Totaal na', $this->formatBytes($totals['bytes_after'])],
                ['Besparing', $this->formatBytes(max(0, $totals['bytes_before'] - $totals['bytes_after']))],
            ]
        );

        return self::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        return round($bytes / (1024 ** $power), 1) . ' ' . $units[$power];
    }
}
