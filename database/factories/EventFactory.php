<?php

namespace Database\Factories;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $titles = [
            'Laravel 入門ハンズオン',
            'React モダン開発勉強会',
            'TypeScript 実践ワークショップ',
            'AWS クラウド設計入門',
            'Docker & Kubernetes 勉強会',
            'Python データ分析入門',
            'Vue.js 実践勉強会',
            'GitHub Actions CI/CD 入門',
            'GraphQL API 設計勉強会',
            'Next.js フルスタック開発',
            'TailwindCSS デザイン実践',
            'Flutter モバイル開発入門',
            'セキュリティ勉強会：OWASP Top 10',
            'データベース設計のベストプラクティス',
            'AIエンジニアリング入門',
            'Rust プログラミング勉強会',
            'Go 言語バックエンド開発',
        ];

        $venues = [
            '渋谷ヒカリエ 8F',
            '六本木ヒルズ コワーキングスペース',
            '新宿 WeWork',
            '品川インターシティ カンファレンスルーム',
            'コワーキングスペース茅場町 Co-Edo',
            'AWS 目黒オフィス',
            'Google 渋谷オフィス',
            'DMM.com 本社セミナールーム',
            'DeNA 本社大会議室',
            'Recruit 本社 ホール',
        ];

        return [
            'user_id' => User::factory(),
            'title' => fake()->randomElement($titles),
            'description' => fake()->text(200),
            'category' => fake()->randomElement(EventCategory::cases()),
            'prefecture' => fake()->randomElement(['東京都', '大阪府', '京都府', '福岡県', '神奈川県', '埼玉県']),
            'location' => fake()->randomElement($venues),
            'event_date' => fake()->dateTimeBetween('+1 day', '+6 months'),
            'end_date' => fn (array $attributes) => Carbon::parse($attributes['event_date'])->addHours(2),
            'capacity' => fake()->randomElement([20, 30, 50, 80, 100]),
            'status' => EventStatus::Published,
        ];
    }

    /**
     * 下書き状態のイベントを生成
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventStatus::Draft,
        ]);
    }

    /**
     * 非公開状態のイベントを生成
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventStatus::Private,
        ]);
    }

    /**
     * オンラインイベントを生成
     */
    public function online(): static
    {
        return $this->state(fn (array $attributes) => [
            'prefecture' => 'オンライン',
            'location' => null,
            'online_url' => 'https://zoom.us/j/123456789',
            'online_password' => null,
        ]);
    }

    /**
     * ハイブリッドイベントを生成
     */
    public function hybrid(): static
    {
        return $this->state(fn (array $attributes) => [
            'prefecture' => 'ハイブリッド',
            'online_url' => 'https://zoom.us/j/987654321',
            'online_password' => null,
        ]);
    }
}
