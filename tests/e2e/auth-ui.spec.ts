import { test, expect } from '@playwright/test';
import { register, login, logout, createEvent, uniqueEmail } from './helpers';

test.describe('未ログイン時の UI', () => {
    test('イベント詳細ページに参加ボタンが表示されない', async ({ page }) => {
        await page.goto('/');
        const firstEvent = page.locator('a[href*="/events/"]').first();
        if (await firstEvent.count() === 0) { test.skip(); return; }
        await firstEvent.click();

        await expect(page.getByRole('button', { name: /参加|申込/ })).toHaveCount(0);
        await expect(page.getByRole('link', { name: 'ログイン' })).toBeVisible();
    });

    test('/admin にアクセスするとログインページにリダイレクトされる', async ({ page }) => {
        await page.goto('/admin');
        await expect(page).toHaveURL(/login/);
    });
});

test.describe('一般ユーザーの UI', () => {
    let email: string;

    test.beforeEach(async ({ page }) => {
        email = uniqueEmail('user');
        await register(page, 'テストユーザー', email, 'password123');
    });

    test('/admin にアクセスすると 403 になる', async ({ page }) => {
        const response = await page.goto('/admin');
        expect(response?.status()).toBe(403);
    });

    test('自分のイベントに編集ボタンが表示される', async ({ page }) => {
        await createEvent(page, '自分のイベント');
        await expect(page.getByRole('link', { name: '編集', exact: true })).toBeVisible();
    });

    test('他人のイベントに編集ボタンが表示されない', async ({ page }) => {
        const organizerEmail = uniqueEmail('organizer');
        await logout(page);
        await register(page, 'オーガナイザー', organizerEmail, 'password123');
        const eventUrl = await createEvent(page, '他人のイベント');

        await logout(page);
        await login(page, email, 'password123');
        await page.goto(eventUrl);

        await expect(page.getByRole('link', { name: '編集', exact: true })).toHaveCount(0);
    });
});
