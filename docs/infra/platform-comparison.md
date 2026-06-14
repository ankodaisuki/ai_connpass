# インフラ移行先 比較表

作成日: 2026-06-14

## Railway（現状）vs Fly.io vs Render + Neon vs AWS

> AWS の構成は ECS Fargate（コンテナ）+ RDS MySQL を想定。

| 項目 | Railway（現状） | Fly.io | Render + Neon | AWS（ECS + RDS） |
|------|----------------|--------|---------------|-----------------|
| **料金** | 無料期間終了後 $5〜/月 | $0〜（無料枠あり、従量課金） | $0〜（両サービスとも無料枠あり） | **$40〜70/月〜**（ALB + Fargate + RDS の最低構成）⚠️ |
| **PHP サーバー** | Railway（コンテナ） | Fly.io（コンテナ） | Render（コンテナ） | ECS Fargate（コンテナ） |
| **DB** | MySQL 8.4（マネージド） | MySQL（自前 or 外部） | Neon（PostgreSQL） | RDS MySQL（マネージド） |
| **DB エンジン変更** | — | 不要 ✅ | **MySQL → PostgreSQL** ⚠️ | 不要 ✅ |
| **データ移行** | — | `mysqldump` → restore のみ | MySQL → PostgreSQL 変換ツールが必要 ⚠️ | `mysqldump` → RDS restore のみ |
| **Dockerfile** | 既存を利用 | 既存をそのまま利用 ✅ | 既存をそのまま利用 ✅ | 既存をそのまま利用 ✅ |
| **コード変更** | — | `start.sh` 1行削除 | `start.sh` 1行削除 + マイグレーション1ファイル修正 | `start.sh` 1行削除 |
| **マイグレーション修正** | — | 不要 ✅ | 1箇所（生 SQL を PostgreSQL 用に書き直し）⚠️ | 不要 ✅ |
| **インフラ設定** | Railway ダッシュボードのみ | `fly.toml` 1ファイル | `render.yaml` 1ファイル | **VPC / ECR / ECS / ALB / RDS / IAM / セキュリティグループ** ⚠️ |
| **GitHub 自動デプロイ** | `main` マージで自動（Railway 連携） | GitHub Actions に1ステップ追加 | GitHub Actions に1ステップ追加 | GitHub Actions に複数ステップ追加（ECR push + ECS deploy）⚠️ |
| **CI（テスト）** | GitHub Actions ✅ | そのまま流用 ✅ | そのまま流用 ✅ | そのまま流用 ✅ |
| **CD（デプロイ自動化）** | Railway が自動 | GitHub Actions に1ステップ追加 | GitHub Actions に1ステップ追加 | GitHub Actions に ECR push + ECS タスク更新の複数ステップ追加 |
| **マイグレーション実行** | `start.sh` 内で自動 | 同じ `start.sh` をそのまま利用 ✅ | 同じ `start.sh` をそのまま利用 ✅ | ECS タスク定義に `migrate` 用の release コマンドを追加設定が必要 |
| **ゼロダウンタイム** | なし | なし | なし | ALB + ECS Rolling Update で**対応可** ✅ |
| **スケーラビリティ** | 限定的 | 中程度 | 中程度 | **高い**（Auto Scaling 対応）✅ |
| **運用負荷** | 低い ✅ | 低い ✅ | 低い ✅ | **高い**（AWS の知識が必要）⚠️ |
| **リージョン** | `iad`（バージニア） | 東京（`nrt`）選択可 | 複数リージョン選択可 | 東京（`ap-northeast-1`）✅ |
| **移行コスト** | — | **半日〜1日** ✅ | **1〜2日** ⚠️ | **1週間〜** ⚠️ |

---

## 移行作業の内訳

### Fly.io

```
1. flyctl launch --no-deploy          # fly.toml 生成（5分）
2. flyctl secrets set ...             # 環境変数移行（30分）
3. start.sh の RAILWAY_PUBLIC_DOMAIN 参照を削除（1行・5分）
4. mysqldump → Fly MySQL へ restore   # データ移行（30分〜）
5. flyctl deploy                      # 初回デプロイ（10分）
6. GitHub Actions に flyctl deploy 追加（30分）
```

### Render + Neon

```
1. render.yaml 作成                        # Render 設定（1〜2時間）
2. Neon プロジェクト作成・接続設定            # （30分）
3. add_end_date_to_events.php の生SQL修正   # PostgreSQL 対応（30分）
4. ローカルで PostgreSQL 動作確認            # （2〜3時間）
5. mysqldump → pgloader で変換・移行         # データ移行（半日〜）
6. 環境変数設定（DB_CONNECTION=pgsql 等）   # （30分）
7. GitHub Actions に deploy hook 追加       # （30分）
```

### AWS（ECS Fargate + RDS MySQL）

```
1. VPC / サブネット / セキュリティグループ作成  # （2〜4時間）
2. ECR リポジトリ作成                         # （30分）
3. RDS MySQL 作成・接続設定                   # （1〜2時間）
4. ECS クラスター / タスク定義 / サービス作成  # （2〜4時間）
5. ALB 作成・ECS サービスと紐付け             # （1〜2時間）
6. IAM ロール設定                             # （1〜2時間）
7. start.sh の RAILWAY_PUBLIC_DOMAIN 参照を削除（1行）
8. mysqldump → RDS restore                    # データ移行（30分〜）
9. GitHub Actions に ECR push + ECS deploy 追加（2〜4時間）
10. 動作確認・DNS 切り替え                    # （1〜2時間）
```

---

## 総評

| | コスト | 移行工数 | 運用負荷 | スケーラビリティ |
|--|--------|---------|---------|--------------|
| **Fly.io** | ◎ | ◎ | ◎ | ○ |
| **Render + Neon** | ◎ | ○ | ◎ | ○ |
| **AWS** | △ | △ | △ | ◎ |

**現時点の推奨: Fly.io**
DB エンジン変更不要・Dockerfile 流用・コード変更最小限で半日程度で移行完了。東京リージョンも選択可。将来的にスケールが必要になった段階で AWS への移行を検討するのが合理的。
