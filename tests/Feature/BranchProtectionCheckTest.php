<?php

// ブランチ保護のCIゲート検証用の使い捨てテスト（検証後に削除）。
test('ブランチ保護の検証: 意図的に失敗させる', function () {
    expect(true)->toBeFalse();
});
