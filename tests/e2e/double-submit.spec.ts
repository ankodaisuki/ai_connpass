import { test, expect } from '@playwright/test';
import { register, createEvent, uniqueEmail } from './helpers';

test.describe('二重送信防止', () => {
    test.beforeEach(async ({ page }) => {
        await register(page, 'テストユーザー', uniqueEmail(), 'password123');
    });

    test('フォーム送信後に送信ボタンが disabled になる', async ({ page }) => {
        await page.goto('/events/create');
        await page.fill('input[name=title]', 'テストイベント');
        await page.selectOption('select[name=category]', { index: 1 });
        await page.selectOption('select[name=prefecture]', { label: '東京都' });
        await page.fill('input[name=location]', 'テスト会場');
        await page.fill('input[name=event_date]', '2030-12-31T10:00');
        await page.fill('input[name=end_date]', '2030-12-31T18:00');
        await page.fill('input[name=capacity]', '10');

        const submitButton = page.getByRole('button', { name: '作成する' });

        // ボタンが disabled になった瞬間を捉える
        const disabledPromise = page.waitForFunction(() => {
            const btn = document.querySelector<HTMLButtonElement>('button[type="submit"]:not(details button)');
            return btn?.disabled === true;
        });

        await Promise.all([
            disabledPromise,
            submitButton.click(),
        ]);

        // ナビゲーション後にイベント詳細ページに到達していること
        await page.waitForURL(/\/events\/\d+$/);
        await expect(page).toHaveURL(/\/events\/\d+$/);
    });
});
