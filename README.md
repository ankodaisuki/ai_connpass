<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## 🛠️ Claude Code Skills セットアップ

このプロジェクトには、Laravel開発向けのカスタムスキルが含まれています。

### セットアップ手順

初回クローン後、以下を実行してください：

```bash
bash setup-skills.sh
```

このスクリプトは `skills/` ディレクトリを `.claude/skills/` にコピーします。
コピー後、Claude Code はこれらのスキルを自動的に読み込みます。

## 🐳 ローカル環境メモ（Sail / Docker）

### 画像などのストレージ公開リンク（`storage:link`）

アップロードした画像（イベントのカバー画像など）を表示するには、`public/storage` → `storage/app/public` のシンボリックリンクが必要です。**このリンクは必ず Sail コンテナ内で作成してください。**

```bash
./vendor/bin/sail artisan storage:link
```

> **⚠️ ホスト側で `php artisan storage:link` を実行しないこと。**
> ホスト（macOS 等）で実行すると、ホストの絶対パスを指す symlink が作られ、アプリが動く Docker コンテナ内ではそのパスが解決できず、画像配信が 403 になります（症状: ファイルも DB も正常なのに詳細・一覧で画像が表示されない）。
> 既に誤って作成した場合は、コンテナ内で削除して張り直します:
> ```bash
> ./vendor/bin/sail exec laravel.test sh -c 'rm -f public/storage && php artisan storage:link'
> ```

本番（Railway 等のコンテナ環境）でも同様に、**コンテナ起動時に `storage:link` を実行**する必要があります（起動スクリプトに含める）。

### 含まれるスキル

- **laravel-best-practices** — Laravel コードのベストプラクティス・リファクタリング指針
- **laravel-plugin-discovery** — LaraPlugins.io MCPでパッケージ検索・評価
- **laravel-security** — 認証、検証、CSRF、セキュリティベストプラクティス
- **laravel-tdd** — PHPUnit/Pestによる TDDワークフロー
- **laravel-verification** — デプロイ前の検証ループ
- **pest-testing** — Pest によるテスト記述・ブラウザテスト
- **socialite-development** — Laravel Socialite による OAuth ソーシャルログイン
- **tailwindcss-development** — Tailwind CSS のレイアウト・UIスタイリング
- **api-design** — REST API設計パターン
- **backend-patterns** — バックエンド開発パターン
- **deployment-patterns** — デプロイ戦略・CI/CDパイプライン
- **docker-patterns** — Dockerベストプラクティス
- **e2e-testing** — Playwright E2Eテスト
- **use-railway** — Railway へのデプロイ・環境変数・DB運用（※下記の追加セットアップが必要）

### Railway スキルを使う場合の追加セットアップ

`use-railway` スキルは「手順書」のみをリポジトリで配布しています。
実際に Railway を操作するには、各自のマシンで **Railway MCP と CLI** を別途セットアップしてください
（認証トークンを伴うため、リポジトリには同梱できません）。

```bash
# Railway CLI のインストール（未導入の場合）
brew install railway          # macOS（Homebrew）
# もしくは: bash <(curl -fsSL https://railway.com/install.sh)

# MCP・スキル・認証をまとめてセットアップ
railway setup agent
```

セットアップ後に未ログインの場合は `railway login` を実行してください。
これで Claude Code から Railway の操作（デプロイ、ログ確認、環境変数設定など）が可能になります。

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
