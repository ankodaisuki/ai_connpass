# v7 性能試験（perf）

スペック: `docs/superpowers/specs/2026-07-11-v7-performance-test-design.md`

## ツール選定

| 候補 | 判断 | 理由 |
|---|---|---|
| **k6（採用）** | ✅ | シナリオをJSで記述でき、SLOを`thresholds`で自動合否判定できる。arrival-rate executorでRPSを正確に制御できる |
| Locust | ❌ | 分散実行前提で単機・単発の本試験にはオーバーキル。SLO判定は自作 |
| Gatling | ❌ | Scala/JVMで学習・セットアップコストが高い |

## 構成

- `k6/lib/journey.js` — 共通ジャーニー（CSRFログイン・閲覧・申込）
- `k6/ramp.js` — 試験1(a) 限界探索ランプ（5→10→25→50→100 RPS）
- `k6/spike.js` — 試験1(b) スパイク再現（殺到1分 約100 RPS）
- `k6/longrun.js` — 試験2 12時間ロングラン（当初計画24時間から短縮。3〜5 RPS＋毎時10 RPS×5分）

## RPS とジャーニーの換算

1ジャーニー ≒ 7動的リクエスト。k6 の arrival rate（イテレーション開始数/秒）×7 ≒ RPS。
スペックの「最初の1分に1,500人」は think time を除いた等価RPS（約100 RPS）に圧縮してモデル化する
（サーバーに届く仕事量は同じ。ユーザー数のリアリティより RPS の正確さを優先）。

## 実行方法（ローカル検証）

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan perf:seed --test-accounts=10 --users=0 --events=0 --published-events=5 --attendances=0 --force
# TARGET_EVENT_ID=<id> が出力される
k6 run --env BASE_URL=http://localhost --env TARGET_EVENT_ID=<id> --env TEST_ACCOUNTS=10 perf/k6/smoke.js
```

## 本番（Railway）実施手順

前提: メンター承認済みスペックに基づき、Railway 無料プランの本番へ「あえて壊れる負荷」をかける。
即死しても終了せず、負荷を下げて計測可能な帯域を見つけレポーティングまでやり切る。

### 注意事項

- **k6の終了コードとSLO未達の扱い**: レイテンシSLOが未達だと `k6 run` 自体の終了コードが非ゼロになる。
  これは「試験が失敗した」のではなく「限界を超えた負荷帯を観測した」という**正常な結果**であることが多い
  （特に ramp.js は意図的に限界を超えるまで負荷を上げる試験）。終了コードが非ゼロでもそれ自体は異常ではない。
  SLO判定表への転記が本来の目的なので、`--out json=` の結果は必ず保存し、レポートに反映すること
- **既知バグ（remember_token）・スコープ外**: `users` テーブルに `remember_token` カラムが存在せず、
  ログイン画面の「ログイン状態を保持する」にチェックを入れると本番でも500エラーになる既存バグがある
  （k6の `login()` は `remember` パラメータを送らないよう既に回避済み）。本試験のスコープ外だが、
  性能試験とは別に修正を検討すべき

### 0. 事前準備（必須）

1. **DBバックアップ**（CLAUDE.md の規約。シードとテストデータを丸ごと巻き戻せるようにする）

   **Railwayネイティブの Backups 機能は Pro プラン限定**（Hobbyでは使えないことを2026-07-19に確認）。
   また `railway ssh` でリモートコンテナ内の `bash -c` を実行する方式は、**標準出力の先頭行が
   欠落する信頼性問題**を実際に確認した（単純な `echo` のテストで再現）ため、大きなダンプ出力を
   ストリーミングする用途には使わないこと。

   代わりに `railway run` でMySQLの接続変数（`MYSQL_PUBLIC_URL` 等、外部接続用TCPプロキシ経由）を
   **ローカルプロセスにのみ注入**し、ローカルの `mysqldump`（`brew install mysql-client` 等で導入）を
   実行する。認証情報はコマンド実行者の目に触れず、出力はローカルファイルへの通常のリダイレクトなので
   信頼性の問題もない:

   ```bash
   mkdir -p perf/backups   # .gitignore 済み。実データ（メールアドレス等）を含むため絶対にコミットしない
   BACKUP_FILE="perf/backups/backup-before-perf-$(date +%Y%m%d-%H%M%S).sql"
   railway run --service <MySQLサービスID> -- bash -c \
     'mysqldump -h "$(echo $MYSQL_PUBLIC_URL | sed -E "s#mysql://[^@]+@([^:/]+).*#\1#")" \
                -P "$(echo $MYSQL_PUBLIC_URL | sed -E "s#.*:([0-9]+)/.*#\1#")" \
                -u "$MYSQLUSER" -p"$MYSQL_ROOT_PASSWORD" \
                --single-transaction --routines --triggers "$MYSQLDATABASE"' > "$BACKUP_FILE"
   ```

   **取得後は必ず整合性を検証すること**（サイレントな破損を防ぐため）:
   - ファイル末尾に `-- Dump completed on ...` の完了マーカーがあるか
   - `grep -c "^CREATE TABLE" "$BACKUP_FILE"` が期待テーブル数（16）と一致するか
   - 主要テーブルのINSERTタプル数（`grep "^INSERT INTO ..." | grep -o "),(" | wc -l` の結果+1）が、
     本番の実際の行数（読み取り専用の `SELECT COUNT(*)`）と一致するか
2. **メール実送信の停止**: Railway の環境変数を `MAIL_MAILER=log` に変更（キャンセル待ち登録でメール送信が走るため。SMTPプロバイダの制限・ブラックリスト入りを防ぐ）
3. **観測の有効化**: `PERF_MONITORING=true` を設定（perf:snapshot が毎時実行される。スケジューラが動いていることを `railway run php artisan schedule:list` で確認）
4. **シード投入**（数十分〜1時間程度の想定。進捗ログが10万件ごとに出る）
   ```bash
   railway run php artisan perf:seed --force
   # 出力の TARGET_EVENT_ID=<id> を控える
   ```
   既定値（`--users=150000 --events=30000 --attendances=450000`）は下記「容量計算」の通り
   MySQLボリューム容量に収まるよう調整済み。**独自の値を指定する場合は必ず容量を再計算すること**

### 容量計算（MySQLボリューム制約）

2026-07-19 に本番Railway環境を確認したところ、**MySQLボリュームの上限は500MB**（現在の使用量は
アプリのテーブルがほぼ空の状態で182MB）だった。当初のスペックが想定していた「connpass級」の規模
（ユーザー100万〜300万行等）でシードすると確実に容量超過し、ディスクフルでMySQLがクラッシュする
リスクがあったため、**実測ベースで容量に収まる規模に縮小した**（2026-07-19、`perf:seed`の既定値を変更）。

**実測方法**: ローカルSailに`users`10,000行・`events`2,000行・`event_attendances`30,000行を投入し、
`ANALYZE TABLE`後に`information_schema.tables`の`DATA_LENGTH+INDEX_LENGTH`を実測件数で割って算出。

| テーブル | 実測 bytes/行 | 既定値 | 容量 |
|---|---:|---:|---:|
| users | 315.1 | 150,000 | 45.1MB |
| events | 216.4 | 30,000 | 6.2MB |
| event_attendances | 193.8 | 450,000 | 83.2MB |
| **合計** | | | **約134MB** |

現在の使用量182MB＋シード後134MB＝約316MB。**残り約184MB**を、シード投入中のREDO/UNDOログ、
試験実行中に発生する新規行（申込・キャンセル・セッション・キュー等の churn）のバッファとして残す。

ユーザー:過去イベント:申込履歴の比率（5:1:15）は当初のスペックのまま維持しているため、
DBのクエリ性能・インデックス効果を検証する目的は損なわれない（絶対数のみ縮小）。
独自の規模で実施したい場合は、上記 bytes/行 を使って `(users×315 + events×216 + attendances×194) / 1024²`
が安全な範囲（目安150MB以下）に収まることを確認してから `--users`/`--events`/`--attendances` を指定すること。

### 1. スモーク（1VU で疎通確認）

```bash
k6 run --env BASE_URL=https://aiconnpass-production.up.railway.app \
       --env TARGET_EVENT_ID=<id> perf/k6/smoke.js
```

### 2. 試験1(a) 限界探索ランプ（約25分50秒）

```bash
k6 run --env BASE_URL=https://... --env TARGET_EVENT_ID=<id> \
       --out json=results/ramp.json perf/k6/ramp.js
```

- SLO違反が最初に出た段＝限界RPS。Railway のメトリクス（CPU/メモリ/DB）と突き合わせ、
  最初に音を上げたコンポーネントを記録する
- **即死した場合**: rates を下げた縮小版（`--env SMOKE=1` か rates 編集）で再実行し、
  計測可能な帯域で限界値を確定させる
- 各段は「立ち上がり10秒＋維持5分」のペア構成（5段）のため、所要時間は約25分50秒（SMOKE時は各段40秒×2段）

### 3. 試験1(b) スパイク再現（約17分）＋整合性検証

```bash
k6 run --env BASE_URL=https://... --env TARGET_EVENT_ID=<id> \
       --out json=results/spike.json perf/k6/spike.js
railway run php artisan perf:verify <id>   # 定員超過0・繰り上げ漏れ0を確認
```

- **`dropped_iterations` が出た場合の対処**: SMOKE実行では rush フェーズ（殺到）開始直後に
  VUプールの立ち上がりが目標レートに追いつかず `dropped_iterations` が発生することがあった。
  フル実行時にも継続的に発生する場合は、`perf/k6/spike.js` の該当シナリオ（主に `rush`）の
  `preAllocatedVUs` を引き上げて再実行する

### 4. 試験2 ロングラン（12時間・当初計画24時間から短縮）

**短縮の理由**: 毎時スナップショット12点は傾き検出（メモリ・テーブル行数・応答時間が「線形に増加し続けていないか」）に十分機能する。セッション寿命120分に対し6周期分あり、期限切れセッション掃除の検証にも支障ない。トレードオフとして、緩やかなリークの検出力と「何日で限界に達するか」の外挿精度は24時間実施より下がるため、レポートには観測窓短縮の注記を残すこと。

```bash
nohup k6 run --env BASE_URL=https://... --env TARGET_EVENT_ID=<id> --env HOURS=12 \
       --out json=results/longrun.json perf/k6/longrun.js > results/longrun.log 2>&1 &
```

- Mac がスリープしないよう `caffeinate` を併用する
- 毎時スナップショットは本番側の `storage/logs/perf-snapshots.jsonl` に蓄積される。
  終了後に `railway ssh` などで回収する
- **アプリコンテナ・キューワーカーのメモリ推移は Railway のメトリクス画面で観測する**
  （perf:snapshot はテーブル行数とDB状態のみ。メモリの傾きはRailway側のグラフを毎時記録 or スクリーンショット）

### 5. レポート作成と後始末

1. `docs/test/v7-performance-report.md` に結果を記入
2. 環境変数を元に戻す（`MAIL_MAILER`・`PERF_MONITORING`）
3. テストデータを消す: バックアップから復元
   ```bash
   railway run mysql -u <user> -p<pass> ai_connpass < backup-before-perf.sql
   ```

## フェーズ別 p95 の集計（レポート用）

`--out json=` の結果から jq でフェーズ別に p95 を出す例:

```bash
jq -r 'select(.type=="Point" and .metric=="http_req_duration") | [.data.time, .data.value] | @csv' \
  results/spike.json > results/spike-duration.csv
# 時刻でフェーズに切り分けて表計算やスクリプトでp95を算出する
```
