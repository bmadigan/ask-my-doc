<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Document\IngestDocumentAction;
use Bmadigan\Overpass\Services\PythonAiBridge;
use Illuminate\Database\Seeder;

class SampleDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $docsPath = base_path('.docs');

        if (! is_dir($docsPath)) {
            $this->command->error("Directory not found: {$docsPath}");
            $this->command->info('Create the .docs/ folder with markdown files first.');

            return;
        }

        $files = glob("{$docsPath}/*.md");

        if (empty($files)) {
            $this->command->warn('No markdown files found in .docs/ folder.');

            return;
        }

        $overpass = app(PythonAiBridge::class);

        foreach ($files as $file) {
            $filename = pathinfo($file, PATHINFO_FILENAME);
            $title = str_replace('-', ' ', $filename);
            $title = ucwords($title);
            $content = file_get_contents($file);

            $this->command->info("Ingesting: {$title}");

            try {
                $document = IngestDocumentAction::run([
                    'title' => $title,
                    'content' => $content,
                    'original_filename' => basename($file),
                    'chunk_size' => 1000,
                    'overlap_size' => 200,
                ], $overpass);

                $chunkCount = $document->chunks()->count();
                $this->command->info("  → Created {$chunkCount} chunks");
            } catch (\Exception $e) {
                $this->command->error("  → Failed: {$e->getMessage()}");
            }
        }

        $this->command->newLine();
        $this->command->info('Sample documents seeded successfully!');
    }
}
