// 試験1(b) スパイク再現: 受付開始直後の殺到（1分・約100 RPS）と、
// 限界超過時の壊れ方・負荷が去った後の自力回復（クールダウン）を観察する。
import { sleep } from 'k6';
import { login, browse, applyToEvent, myAttendances, pickUserIndex, SLO_THRESHOLDS } from './lib/journey.js';

const SMOKE = !!__ENV.SMOKE;
const m = (min) => (SMOKE ? '30s' : `${min}m`);

// 閲覧のみのジャーニー（ウォームアップ・クールダウン用、約2リクエスト）
export function browseOnly() {
  browse();
  sleep(0.2);
}

// 申込ジャーニー（殺到・後続用、約7リクエスト）
export function applyJourney() {
  login(pickUserIndex());
  browse();
  applyToEvent();
  myAttendances();
  sleep(0.2);
}

export const options = {
  scenarios: {
    // ① ウォームアップ: 日常ピーク帯 3〜5 RPS（閲覧2req×2 iter/s ≒ 4 RPS）
    warmup: {
      executor: 'constant-arrival-rate',
      exec: 'browseOnly',
      rate: 2, timeUnit: '1s',
      duration: m(3),
      preAllocatedVUs: 20, maxVUs: 100,
    },
    // ② 殺到: 1分・約100 RPS（7req×14 iter/s）
    rush: {
      executor: 'constant-arrival-rate',
      exec: 'applyJourney',
      rate: 14, timeUnit: '1s',
      duration: m(1),
      startTime: m(3),
      preAllocatedVUs: 100, maxVUs: 1000,
    },
    // ③ 後続: 10分・約10〜20 RPS（7req×2 iter/s ≒ 14 RPS）＋背景閲覧
    followup: {
      executor: 'constant-arrival-rate',
      exec: 'applyJourney',
      rate: 2, timeUnit: '1s',
      duration: m(10),
      startTime: SMOKE ? '60s' : '4m',
      preAllocatedVUs: 50, maxVUs: 300,
    },
    // ④ クールダウン: 3分・閲覧のみ（ベースライン復帰の確認）
    cooldown: {
      executor: 'constant-arrival-rate',
      exec: 'browseOnly',
      rate: 2, timeUnit: '1s',
      duration: m(3),
      startTime: SMOKE ? '90s' : '14m',
      preAllocatedVUs: 20, maxVUs: 100,
    },
  },
  thresholds: SLO_THRESHOLDS,
};
