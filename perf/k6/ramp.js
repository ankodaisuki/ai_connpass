// 試験1(a) 限界探索ランプ: 5→10→25→50→100 RPS 相当を各5分維持し、
// SLO違反が最初に出る負荷帯（限界RPS）を特定する。
// 1ジャーニー≒7リクエストなので arrival rate ≒ RPS/7 で設定する。
import { sleep } from 'k6';
import { login, browse, applyToEvent, myAttendances, logout, pickUserIndex, SLO_THRESHOLDS } from './lib/journey.js';

const SMOKE = !!__ENV.SMOKE;
const stageDuration = SMOKE ? '30s' : '5m';
// [iter/s] ≒ [目標RPS]/7 → 5,10,25,50,100 RPS ≒ 1,2,4,7,14 iter/s
const rates = SMOKE ? [1, 2] : [1, 2, 4, 7, 14];

export const options = {
  scenarios: {
    ramp: {
      executor: 'ramping-arrival-rate',
      startRate: rates[0],
      timeUnit: '1s',
      preAllocatedVUs: 50,
      maxVUs: 500,
      stages: rates.map((target) => ({ duration: stageDuration, target })),
    },
  },
  thresholds: SLO_THRESHOLDS,
};

export default function () {
  const user = pickUserIndex();
  login(user);
  browse();
  applyToEvent();
  myAttendances();
  if (Math.random() < 0.1) {
    logout(); // 実利用に合わせ約1割のみログアウト（残りはセッション放置）
  }
  sleep(0.2);
}
