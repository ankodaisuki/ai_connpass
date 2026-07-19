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
- `k6/longrun.js` — 試験2 24時間ロングラン（3〜5 RPS＋毎時10 RPS×5分）

## RPS とジャーニーの換算

1ジャーニー ≒ 7動的リクエスト。k6 の arrival rate（イテレーション開始数/秒）×7 ≒ RPS。
スペックの「最初の1分に1,500人」は think time を除いた等価RPS（約100 RPS）に圧縮してモデル化する
（サーバーに届く仕事量は同じ。ユーザー数のリアリティより RPS の正確さを優先）。

## 実行方法・実施手順（runbook）

Task 10 で追記する。
