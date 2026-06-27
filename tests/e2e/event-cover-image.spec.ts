import { test, expect } from '@playwright/test';
import { register, uniqueEmail } from './helpers';

// 1x1 透明PNG（base64）
const PNG_1PX = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC',
    'base64',
);

test('作成時にカバー画像をアップロードすると詳細ページに表示される', async ({ page }) => {
    await register(page, 'テストユーザー', uniqueEmail(), 'password123');

    await page.goto('/events/create');
    await page.fill('input[name=title]', 'カバー画像テスト');
    await page.selectOption('select[name=category]', { index: 1 });
    await page.selectOption('select[name=prefecture]', { label: '東京都' });
    await page.fill('input[name=location]', 'テスト会場');
    await page.fill('input[name=event_date]', '2030-12-31T10:00');
    await page.fill('input[name=end_date]', '2030-12-31T18:00');
    await page.fill('input[name=capacity]', '10');

    await page.setInputFiles('input[name=cover_image]', {
        name: 'cover.png',
        mimeType: 'image/png',
        buffer: PNG_1PX,
    });

    await page.getByRole('button', { name: '作成する' }).click();
    await page.waitForURL(/\/events\/\d+$/);

    const img = page.locator('img[alt$="のカバー画像"]').first();
    await expect(img).toBeVisible();
    await expect(img).not.toHaveAttribute('src', /event-placeholder\.svg/);
});
