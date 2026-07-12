<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * カバー画像の保存・差し替え・削除を、ストレージとDBの整合を保ちながら安全に行うサービス。
 *
 * 統一パターン:
 * 1. 新ファイルは先にアップロードする（旧ファイルはまだ消さない）
 * 2. DB更新はトランザクションで行う
 * 3. 旧ファイルの削除はコミット成功後にのみ行う
 * 4. DB更新が失敗したら、アップ済みの新ファイルを補償削除する（旧ファイルは無傷）
 */
class CoverImageService
{
    private function disk(): string
    {
        return config('filesystems.cover_disk');
    }

    /**
     * イベント作成とカバー画像アップロードを原子的に行う。
     * 失敗時はイベント行・アップ済みファイルとも残さない。
     *
     * @param  array<string, mixed>  $data
     */
    public function createWithCover(array $data, ?UploadedFile $newImage): Event
    {
        $disk = $this->disk();
        $uploadedPath = null;

        try {
            return DB::transaction(function () use ($data, $newImage, $disk, &$uploadedPath): Event {
                $event = Event::create($data);

                if ($newImage !== null) {
                    $uploadedPath = $newImage->store("events/{$event->id}", $disk);
                    $event->update(['cover_image_path' => $uploadedPath]);
                }

                return $event;
            });
        } catch (\Throwable $e) {
            if ($uploadedPath !== null) {
                Storage::disk($disk)->delete($uploadedPath);
            }

            throw $e;
        }
    }

    /**
     * イベント更新（フィールド＋カバー画像の差し替え/削除）を安全に行う。
     *
     * @param  array<string, mixed>  $otherData  cover_image_path を含まないその他の更新項目
     */
    public function updateCover(Event $event, array $otherData, ?UploadedFile $newImage, bool $removeImage): void
    {
        $disk = $this->disk();
        $oldPath = $event->cover_image_path;
        $newPath = null;
        $data = $otherData;

        // ① 新ファイルを先にアップロード（旧はまだ消さない）
        if ($newImage !== null) {
            $newPath = $newImage->store("events/{$event->id}", $disk);
            $data['cover_image_path'] = $newPath;
        } elseif ($removeImage && $oldPath !== null) {
            $data['cover_image_path'] = null;
        }

        // ② DB更新はトランザクションで
        try {
            DB::transaction(fn () => $event->update($data));
        } catch (\Throwable $e) {
            if ($newPath !== null) {
                Storage::disk($disk)->delete($newPath); // 補償: 孤児化させない
            }

            throw $e;
        }

        // ③ コミット成功後にのみ旧ファイルを掃除
        $replaced = $newPath !== null && $oldPath !== null && $oldPath !== $newPath;
        $removed = $newImage === null && $removeImage && $oldPath !== null;

        if ($replaced || $removed) {
            Storage::disk($disk)->delete($oldPath);
        }
    }
}
