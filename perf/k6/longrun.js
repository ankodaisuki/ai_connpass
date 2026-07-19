// 試験2 ロングラン: 3〜5 RPS を12時間継続し、毎時5分だけ10 RPSの小ピークを入れる。
// メモリ・テーブル行数・応答時間の「傾き」を見る試験（観測は perf:snapshot が毎時実施）。
// 当初計画は24時間だったが、毎時12点あれば傾き検出には十分・セッション寿命120分の6周期分で
// 検証に支障ないと判断し12時間に短縮（docs/superpowers/specs/2026-07-11-v7-performance-test-design.md 参照）。
// ramping-arrival-rateのstagesは「直前rateからtargetまでdurationかけて線形にランプする」定義のため、
// targetを切り替えるたびに「立ち上がり（ランプ）」＋「同target維持（プラトー）」のペアを積み、
// ベース/小ピークそれぞれが定常状態になるようにする（Task 7 ramp.jsと同じ手法）。
import { sleep } from 'k6';
import { login, browse, applyToEvent, myAttendances, logout, registerNewUser, browseMyPages, pickUserIndex, SLO_THRESHOLDS } from './lib/journey.js';

const HOURS = Number(__ENV.HOURS || 12);

// 1時間 = 55分ベース（6 iter/10s ≒ 4 RPS）＋ 5分小ピーク（14 iter/10s ≒ 10 RPS）
// HOURS が小数（短縮版）の場合は比率を保って縮める。秒単位に丸めて指定する
// 極端に小さいHOURSでも0秒/負秒のstageが生まれないよう最低2秒を確保する
const baseSec = Math.max(Math.round(55 * 60 * (HOURS >= 1 ? 1 : HOURS)), 2);
const peakSec = Math.max(Math.round(5 * 60 * (HOURS >= 1 ? 1 : HOURS)), 2);
const cycles = Math.max(Math.round(HOURS), 1);

// 立ち上がり時間。baseSec/peakSecを超えないよう、それぞれの半分を上限にガードする
const rampSec = Math.max(1, Math.min(10, Math.floor(baseSec / 2), Math.floor(peakSec / 2)));

const stages = [];
for (let i = 0; i < cycles; i++) {
  stages.push({ duration: `${rampSec}s`, target: 6 }); // ベースへ立ち上がり
  stages.push({ duration: `${baseSec - rampSec}s`, target: 6 }); // ベース維持
  stages.push({ duration: `${rampSec}s`, target: 14 }); // 小ピークへ立ち上がり
  stages.push({ duration: `${peakSec - rampSec}s`, target: 14 }); // 小ピーク維持
}

export const options = {
  scenarios: {
    longrun: {
      executor: 'ramping-arrival-rate',
      startRate: 6,
      timeUnit: '10s', // 0.6 iter/s（≒4 RPS）を表現するため10秒単位
      preAllocatedVUs: 30,
      maxVUs: 300,
      stages,
    },
  },
  thresholds: SLO_THRESHOLDS,
};

export default function () {
  if (Math.random() < 0.05) {
    registerNewUser(); // 低頻度の会員登録（usersテーブル成長の観測用）
    sleep(0.5);
    return;
  }
  const user = pickUserIndex();
  login(user);
  browse();
  // 申込とキャンセルのサイクル（複数イベント分散はTARGET_EVENT_ID中心＋一覧閲覧で代替）
  applyToEvent();
  myAttendances();
  if (Math.random() < 0.2) {
    browseMyPages(); // 低頻度のマイページ回遊（参加済み一覧・プロフィール）
  }
  if (Math.random() < 0.1) {
    logout(); // 約1割のみログアウト。9割はセッション放置（sessionsテーブル肥大の再現条件）
  }
  sleep(0.5);
}
