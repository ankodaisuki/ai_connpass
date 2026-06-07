<?php

namespace App\Http\Requests\Event;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'integer', Rule::enum(EventCategory::class)],
            'prefecture' => ['required', 'string', 'max:10'],
            'location' => [
                Rule::when(
                    $this->prefecture !== 'オンライン',
                    ['required', 'string', 'max:255'],
                    ['nullable', 'string', 'max:255']
                ),
            ],
            'online_url' => [
                Rule::when(
                    in_array($this->prefecture, ['オンライン', 'ハイブリッド'], true),
                    ['required', 'url', 'max:2048'],
                    ['nullable', 'url', 'max:2048']
                ),
            ],
            'online_password' => ['nullable', 'string', 'max:255'],
            'event_date' => ['required', 'date', 'after:now'],
            'end_date' => ['required', 'date', 'after:event_date'],
            'capacity' => ['required', 'integer', 'min:1'],
            'status' => ['nullable', 'integer', Rule::enum(EventStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'online_url' => 'オンラインURL',
            'online_password' => 'パスワード',
        ];
    }
}
