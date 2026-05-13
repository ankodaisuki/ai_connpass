<?php

namespace App\Http\Requests\Api\V1\Event;

use App\Enums\EventCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * イベント一覧の検索・フィルタパラメータ検証
 */
class IndexEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'integer', Rule::enum(EventCategory::class)],
            'prefecture' => ['nullable', 'string', 'max:10'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }
}
