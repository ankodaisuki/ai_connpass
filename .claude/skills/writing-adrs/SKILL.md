---
name: writing-adrs
description: Use when a design choice in this project changes user-visible behavior (what users can do/see, which screens or buttons appear), when starting a new version's feature decisions, or when asked to create/update an ADR or record an architecture decision. Project-specific to ai_connpass (docs/adr).
---

# ADR を書く（ai_connpass）

## 概要

ユーザーから見える動作（**表側仕様**）が変わる設計判断を、バージョン単位で記録する。意思決定者（上司・企画）と後続の保守メンバーが、判断の経緯とトレードオフを追跡・再判断できるようにすることが目的。

## ADR を書くか書かないか

| 判断 | 例 |
|---|---|
| **書く**（表側仕様が変わる） | 合同主催者を公開ページに出すか／オーナー退会時にイベントが残るか／コメント欄を誰に見せるか |
| **書かない**（裏側の実装選択） | テーブル設計・クラス構成・移行手順・既存処理の流用方法 |

迷ったら問う：**「開発者でないレビュアーが、案の違いを画面上の挙動として見分けられるか？」** 見分けられる → 表側 → ADR。見分けられない → 裏側 → ADR には書かず `docs/superpowers/plans/` の実装計画へ。

## 書き方（このプロジェクト固有の作法）

1. **配置・命名:** `docs/adr/v{N}/{4桁連番}-{slug}.md`。`docs/adr/README.md` の索引表に1行追加する。
2. **読み手は非開発者。** 各案は**画面の挙動で説明する**（どのボタンが出る/出ない、参加者画面でどう見えるか）。**本文に裏側用語（例 `EventPolicy`）を出さない。** 案の違いは ASCII モックアップで図示し、git 差分で追えるようにする。
3. **複数案（2案以上）をトレードオフ表で比較**し、各案に 採用/見送り を付ける。**推奨は Proposed 時のたたき台**として書き、**決定はレビュアーに委ねる**（自分で Accepted にしない）。
4. **実装方法は ADR に書かない。** 「採用決定後に実装計画（`docs/superpowers/plans/`）で定める」と分離する。
5. **ステータス:** `Proposed`（提案・レビュー待ち）→ `Accepted`（採用）→ 必要なら `Superseded by ADR-XXXX`。採用が決まったら「決定」欄に理由を記入しステータスを更新する。

## 構成（MADR ベース・日本語）

```
このADRで決めること / 背景と課題 / 判断の観点 /
検討した選択肢 / 各案のメリット・デメリット（表） /
推奨と理由（たたき台） / 決定 / 影響・結果
```

正規のフォーマットは `docs/adr/README.md`、章立て・表・ASCIIモック・実装分離の手本は `docs/adr/v6/0002-abuse-handling.md` を参照。

## よくある失敗

- テーブル/クラス設計を ADR 本文に書く → 裏側なので plans へ移す。
- 案比較で裏側用語を使う → レビュアーが判断できない。画面挙動＋ASCIIで書く。
- 自分で `Accepted` にする → 推奨はたたき台。決定はレビュアー。
- README の索引表への行追加を忘れる。
