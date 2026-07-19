// ライブラリの動作確認用ミニシナリオ（1VU×3周）。負荷はかけない。
import { sleep } from 'k6';
import { login, browse, applyToEvent, myAttendances, logout, pickUserIndex, SLO_THRESHOLDS } from './lib/journey.js';

export const options = {
  vus: 1,
  iterations: 3,
  thresholds: SLO_THRESHOLDS,
};

export default function () {
  login(pickUserIndex() + __ITER); // 周回ごとに別アカウント
  browse();
  applyToEvent();
  myAttendances();
  logout();
  sleep(0.5);
}
