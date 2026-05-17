# Blade フィーチャーテスト設計

## 背景

メンターレビュー対応として、Blade（Web UI）と重複していた API 層を削除した（コミット `c7314b3`）。
削除に伴い、API テスト（`tests/Feature/Api/`）も削除されたため、同等のカバレッジを持つ Blade 向けフィーチャーテストを新規作成する。

## 方針

- **アプローチ**: 削除した API テストを Blade 向けに直接変換（アプローチ A）
- **分割**: ドメイン別に 4 ファイルに分割
- **Blade 特有の検証**: JSON ではなく HTML レスポンス・リダイレクト・DB 変化・セッションエラーを検証
- **コメント**: 各テストに日本語コメントで検証内容を明記

## ファイル構成

```
tests/Feature/
├── AuthTest.php              # 認証（登録・ログイン・ログアウト）
├── EventTest.php             # イベント CRUD + 検索・フィルタ
├── EventAttendanceTest.php   # 参加申し込み・キャンセル
└── MyAttendanceTest.php      # 自分の申し込み一覧
```

## 検証パターン

| API テスト（削除済み） | Blade テスト（新規） |
|---|---|
| `assertOk()` | `assertStatus(200)` / `assertSee()` |
| `assertCreated()` | `assertRedirect(route('...'))` + `assertDatabaseHas()` |
| `assertJsonValidationErrors(['field'])` | `assertSessionHasErrors(['field'])` |
| `assertUnauthorized()` | `assertRedirect(route('login'))` |
| `assertForbidden()` | `assertStatus(403)` |
| `assertNotFound()` | `assertStatus(404)` |
| `assertNoContent()` | `assertRedirect()` + `assertDatabaseHas()` |
| `assertJsonPath('data.title', ...)` | `assertSee(...)` |

---

## AuthTest.php（14 件）

### 登録（7 件）

| テスト名 | 検証内容 |
|---|---|
| `test_register_page_is_accessible` | GET /register → 200 |
| `test_register_creates_user_and_logs_in` | POST /register → ユーザー作成・ログイン状態・events.index にリダイレクト |
| `test_register_fails_with_invalid_email` | 不正メール形式 → email バリデーションエラー |
| `test_register_fails_with_duplicate_email` | 重複メール → email バリデーションエラー |
| `test_register_fails_with_short_password` | 8 文字未満 → password バリデーションエラー |
| `test_register_fails_with_mismatched_password` | 確認不一致 → password バリデーションエラー |
| `test_authenticated_user_is_redirected_from_register` | ログイン済みで GET /register → events.index にリダイレクト（guest ミドルウェア） |

### ログイン・ログアウト（7 件）

| テスト名 | 検証内容 |
|---|---|
| `test_login_page_is_accessible` | GET /login → 200 |
| `test_login_with_valid_credentials` | POST /login → events.index にリダイレクト・認証済み |
| `test_login_fails_with_unknown_email` | 存在しないメール → email バリデーションエラー |
| `test_login_fails_with_wrong_password` | パスワード不一致 → email バリデーションエラー |
| `test_login_fails_for_inactive_user` | Inactive ユーザー → 「凍結」バリデーションエラー |
| `test_authenticated_user_is_redirected_from_login` | ログイン済みで GET /login → リダイレクト（guest ミドルウェア） |
| `test_logout_logs_out_user` | POST /logout → ゲスト状態・events.index にリダイレクト |

---

## EventTest.php（42 件）

### index（17 件）

| テスト名 | 検証内容 |
|---|---|
| `test_index_returns_200` | GET / → 200 |
| `test_index_shows_only_published_events` | Draft・Private は表示されない |
| `test_index_excludes_soft_deleted_events` | soft delete 済みは表示されない |
| `test_index_sorts_by_event_date_ascending` | event_date 昇順で表示 |
| `test_index_paginates_with_12_per_page` | 13 件 → 12 件表示・2 ページ目あり |
| `test_index_filters_by_keyword_in_title` | ?q=Laravel → タイトル部分一致 |
| `test_index_filters_by_keyword_in_description` | ?q=Laravel → description 部分一致 |
| `test_index_returns_empty_when_keyword_does_not_match` | マッチしない場合 → 0 件表示 |
| `test_index_filters_by_category` | ?category=N → カテゴリ一致のみ |
| `test_index_rejects_invalid_category` | ?category=99 → 422 |
| `test_index_filters_by_prefecture` | ?prefecture=東京都 → 都道府県一致のみ |
| `test_index_filters_by_from_date` | ?from=... → 指定日以降のみ |
| `test_index_filters_by_to_date_with_end_of_day_completion` | ?to=日付のみ → endOfDay 補完が効く |
| `test_index_filters_by_date_range` | ?from=...&to=... → 範囲内のみ |
| `test_index_rejects_invalid_from_date` | ?from=not-a-date → 422 |
| `test_index_rejects_to_before_from` | ?to が ?from より前 → 422 |
| `test_index_combines_multiple_filters_with_and` | ?q=...&category=... → AND 結合 |

### show（6 件）

| テスト名 | 検証内容 |
|---|---|
| `test_show_returns_200_for_published_event` | Published → ゲストでも 200 |
| `test_show_displays_event_info` | タイトル・主催者名が表示される |
| `test_show_returns_404_for_draft_to_guest` | Draft → ゲストは 404 |
| `test_show_allows_owner_to_view_draft` | Draft → オーナーは 200 |
| `test_show_returns_404_for_private_to_other_user` | Private → 他人は 404 |
| `test_show_returns_404_for_soft_deleted_event` | soft delete → 404 |

### create / store（9 件）

| テスト名 | 検証内容 |
|---|---|
| `test_create_page_requires_auth` | GET /events/create → ゲストは login にリダイレクト |
| `test_create_page_is_accessible_for_auth_user` | 認証済み → 200 |
| `test_store_creates_event_for_authenticated_user` | POST /events → イベント作成・show にリダイレクト |
| `test_store_defaults_status_to_draft_when_omitted` | status 省略 → DB に Draft で保存 |
| `test_store_requires_auth` | ゲスト → login にリダイレクト |
| `test_store_fails_when_title_is_missing` | title なし → title バリデーションエラー |
| `test_store_fails_when_event_date_is_in_the_past` | 過去日時 → event_date バリデーションエラー |
| `test_store_fails_when_capacity_is_zero` | capacity が 0 以下 → capacity バリデーションエラー |
| `test_store_fails_when_category_is_invalid` | 無効 category 値 → category バリデーションエラー |

### edit / update（7 件）

| テスト名 | 検証内容 |
|---|---|
| `test_edit_page_requires_auth` | ゲスト → login にリダイレクト |
| `test_edit_page_returns_403_for_non_owner` | 他人 → 403 |
| `test_edit_page_is_accessible_for_owner` | オーナー → 200 |
| `test_update_succeeds_for_owner` | PUT /events/{id} → 更新・show にリダイレクト |
| `test_update_returns_403_for_non_owner` | 他人 → 403 |
| `test_update_requires_auth` | ゲスト → login にリダイレクト |
| `test_update_returns_404_for_soft_deleted_event` | soft delete → 404 |

### destroy（3 件）

| テスト名 | 検証内容 |
|---|---|
| `test_destroy_soft_deletes_and_sets_status_to_private` | soft delete + status=Private・index にリダイレクト |
| `test_destroy_requires_auth` | ゲスト → login にリダイレクト |
| `test_destroy_returns_403_for_non_owner` | 他人 → 403 |

---

## EventAttendanceTest.php（12 件）

### store - 申し込み（8 件）

| テスト名 | 検証内容 |
|---|---|
| `test_store_creates_applied_attendance` | DB に Applied で保存・success フラッシュ |
| `test_store_reapplies_when_previously_cancelled` | キャンセル後の再申し込み → 既存レコードを Applied に更新（新規作成しない） |
| `test_store_requires_auth` | ゲスト → login にリダイレクト |
| `test_store_returns_404_for_draft_event` | Draft イベント → 404 |
| `test_store_fails_for_past_event` | 過去イベント → エラーメッセージ付きでリダイレクトバック |
| `test_store_fails_for_event_owner` | オーナー自身 → エラーメッセージ付きでリダイレクトバック |
| `test_store_fails_when_capacity_is_full` | 定員オーバー → エラーメッセージ付きでリダイレクトバック |
| `test_store_fails_when_already_applied` | 重複申し込み → エラーメッセージ付きでリダイレクトバック |

### destroy - キャンセル（4 件）

| テスト名 | 検証内容 |
|---|---|
| `test_destroy_cancels_attendance` | DB に Cancelled + cancelled_at セット・success フラッシュ |
| `test_destroy_requires_auth` | ゲスト → login にリダイレクト |
| `test_destroy_fails_when_not_applied` | 申し込みなし → エラーメッセージ付きでリダイレクトバック |
| `test_destroy_fails_for_past_event` | 過去イベント → エラーメッセージ付きでリダイレクトバック |

---

## MyAttendanceTest.php（5 件）

| テスト名 | 検証内容 |
|---|---|
| `test_index_requires_auth` | ゲスト → login にリダイレクト |
| `test_index_returns_200_for_auth_user` | 認証済み → 200 |
| `test_index_shows_only_own_applied_attendances` | 自分の Applied のみ（Cancelled・他人は除外） |
| `test_index_paginates_with_15_per_page` | 16 件 → 15 件表示 |
| `test_index_sorts_by_applied_at_ascending` | applied_at 昇順（早い申込が先頭） |

---

## 合計

| ファイル | テスト数 |
|---|---|
| AuthTest.php | 14 |
| EventTest.php | 42 |
| EventAttendanceTest.php | 12 |
| MyAttendanceTest.php | 5 |
| **合計** | **73** |
