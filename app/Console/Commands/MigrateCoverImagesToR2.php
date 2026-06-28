<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateCoverImagesToR2 extends Command
{
    protected $signature = 'covers:migrate-to-r2 {--dry-run : コピーせず対象件数のみ表示}';

    protected $description = 'カバー画像を public ディスクから R2(s3) へ冪等にコピーする';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $source = Storage::disk('public');
        $target = Storage::disk('s3');

        $copied = 0;
        $skipped = 0;
        $missing = 0;

        Event::whereNotNull('cover_image_path')->each(function (Event $event) use ($source, $target, $dryRun, &$copied, &$skipped, &$missing): void {
            $path = $event->cover_image_path;

            if ($target->exists($path)) {
                $skipped++;

                return;
            }

            if (! $source->exists($path)) {
                $this->warn("元ファイルが見つかりません (event {$event->id}): {$path}");
                $missing++;

                return;
            }

            if ($dryRun) {
                $this->line("[dry-run] コピー対象: {$path}");
                $copied++;

                return;
            }

            $target->writeStream($path, $source->readStream($path));
            $this->line("コピー完了: {$path}");
            $copied++;
        });

        $this->info("コピー: {$copied} / スキップ: {$skipped} / 欠損: {$missing}".($dryRun ? '（dry-run）' : ''));

        return self::SUCCESS;
    }
}
