// v7 性能試験の共通ジャーニー。
// 前提: perf:seed 済み（perf_user_{i}@perf.test / perf-pass-2026 / TARGET_EVENT_ID）
import http from 'k6/http';
import { check, fail } from 'k6';

export const BASE = __ENV.BASE_URL || 'http://localhost';
export const TARGET_EVENT_ID = __ENV.TARGET_EVENT_ID;
export const TEST_ACCOUNTS = Number(__ENV.TEST_ACCOUNTS || 5000);

// スペックのSLO（判断基準）をそのまま合否判定に使う
export const SLO_THRESHOLDS = {
  'http_req_duration{type:read}': ['p(95)<500'],
  'http_req_duration{type:auth}': ['p(95)<1000'],
  'http_req_duration{type:apply}': ['p(95)<1000'],
  http_req_failed: ['rate<0.01'],
};

// BladeフォームからCSRFトークンを抜き出す
export function extractToken(body) {
  const m = String(body).match(/name="_token"\s+value="([^"]+)"/);
  return m ? m[1] : null;
}

// ログイン: GET /login でトークン取得 → POST /login（bcrypt検証＋セッション作成）
export function login(userIndex) {
  const page = http.get(`${BASE}/login`, { tags: { type: 'read' } });
  const token = extractToken(page.body);
  if (!token) {
    fail('CSRFトークンが /login から取得できない');
  }
  // 注: remember=1 を送るとアプリ側の既知バグ（usersテーブルにremember_tokenカラムが無く
  // Auth::attempt()のremember書き込みでSQLエラー500になる）を踏むため、送信しない。
  const res = http.post(`${BASE}/login`, {
    _token: token,
    email: `perf_user_${userIndex}@perf.test`,
    password: 'perf-pass-2026',
  }, { tags: { type: 'auth' } });
  check(res, { 'ログイン成功（/loginに戻されていない）': (r) => r.status === 200 && !r.url.endsWith('/login') });
  return res;
}

// 閲覧: 一覧 → 対象イベント詳細（リロード含め2〜3回相当は呼び出し側で回数調整）
export function browse() {
  http.get(`${BASE}/`, { tags: { type: 'read' } });
  http.get(`${BASE}/events/${TARGET_EVENT_ID}`, { tags: { type: 'read' } });
}

// 申込: 詳細ページからトークンを取り直してPOST（ハイブリッドなので attendance_mode 必須）
// 満席後の「キャンセル待ちに登録」も同じPOSTなので区別せず投げる
export function applyToEvent() {
  const page = http.get(`${BASE}/events/${TARGET_EVENT_ID}`, { tags: { type: 'read' } });
  const token = extractToken(page.body);
  if (!token) {
    return null; // 申込フォームが無い（自分が主催者等）ケースは負荷対象外として黙って抜ける
  }
  const res = http.post(`${BASE}/events/${TARGET_EVENT_ID}/attendances`, {
    _token: token,
    attendance_mode: Math.random() < 0.9 ? 'online' : 'in_person', // 会場200/オンライン1800の比率相当
  }, { tags: { type: 'apply' } });
  check(res, { '申込POSTが2xxで完了（リダイレクト追跡後）': (r) => r.status === 200 });
  return res;
}

// マイ参加予定の確認（申込ジャーニーの終点）
export function myAttendances() {
  http.get(`${BASE}/my/attendances`, { tags: { type: 'read' } });
}

// ログアウト（実利用では少数派。呼び出し側で約1割に制限する）
export function logout() {
  const page = http.get(`${BASE}/`, { tags: { type: 'read' } });
  const token = extractToken(page.body);
  if (!token) return;
  http.post(`${BASE}/logout`, { _token: token }, { tags: { type: 'auth' } });
}

// VUごとに重複しない試験アカウント番号を返す（1..TEST_ACCOUNTS を循環）
export function pickUserIndex() {
  return ((__VU - 1) % TEST_ACCOUNTS) + 1;
}

// 会員登録（ロングランで低頻度に混入。usersテーブルの成長を24hで観測するため）
export function registerNewUser() {
  const page = http.get(`${BASE}/register`, { tags: { type: 'read' } });
  const token = extractToken(page.body);
  if (!token) return;
  const suffix = `${Date.now()}_${__VU}_${__ITER}`;
  const res = http.post(`${BASE}/register`, {
    _token: token,
    name: `【perf】登録${suffix}`,
    email: `reg_${suffix}@perf.test`,
    password: 'perf-pass-2026',
    password_confirmation: 'perf-pass-2026',
  }, { tags: { type: 'auth' } });
  check(res, { '会員登録が2xxで完了': (r) => r.status === 200 });
}

// マイページ回遊（参加済み一覧・プロフィール。ロングランで低頻度に混入）
export function browseMyPages() {
  http.get(`${BASE}/my/attended-events`, { tags: { type: 'read' } });
  http.get(`${BASE}/profile`, { tags: { type: 'read' } });
}
