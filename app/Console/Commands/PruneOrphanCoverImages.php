<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneOrphanCoverImages extends Command
{
    protected $signature = 'covers:prune-orphans {--dry-run : 削除せず対象のみ表示} {--hours=24 : この時間以上経過した未参照ファイルのみ対象}';

    protected $description = 'どのイベントからも参照されない古いカバー画像ファイルを削除する';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $hours = (int) $this->option('hours');
        $disk = Storage::disk(config('filesystems.cover_disk'));

        $referenced = array_flip(
            Event::withTrashed()->whereNotNull('cover_image_path')->pluck('cover_image_path')->all()
        );

        $threshold = now()->subHours($hours)->timestamp;
        $deleted = 0;

        foreach ($disk->allFiles('events') as $file) {
            if (isset($referenced[$file])) {
                continue; // どこかのイベントが参照している
            }

            if ($disk->lastModified($file) > $threshold) {
                continue; // 猶予時間内は対象外（アップ直後の取りこぼし防止）
            }

            if ($dryRun) {
                $this->line("[dry-run] 削除対象: {$file}");
            } else {
                $disk->delete($file);
                $this->line("削除: {$file}");
            }

            $deleted++;
        }

        $this->info("対象: {$deleted} 件".($dryRun ? '（dry-run）' : ''));

        return self::SUCCESS;
    }
}
