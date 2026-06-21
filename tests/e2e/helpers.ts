import { Page } from '@playwright/test';

export function uniqueEmail(prefix: string = 'user'): string {
    return `${prefix}_${Date.now()}_${Math.floor(Math.random() * 10000)}@example.com`;
}

export async function register(page: Page, name: string, email: string, password: string): Promise<void> {
    await page.goto('/register');
    await page.fill('input[name=name]', name);
    await page.fill('input[name=email]', email);
    await page.fill('input[name=password]', password);
    await page.fill('input[name=password_confirmation]', password);
    await page.click('button[type=submit]');
    await page.waitForURL('/');
}

export async function login(page: Page, email: string, password: string): Promise<void> {
    await page.goto('/login');
    await page.fill('input[name=email]', email);
    await page.fill('input[name=password]', password);
    await page.click('button[type=submit]');
    await page.waitForURL('/');
}

export async function logout(page: Page): Promise<void> {
    await page.goto('/');
    // デスクトップのドロップダウンを開く
    await page.locator('details').first().click();
    const logoutForm = page.locator('details').first().locator('form[action*="logout"]');
    await logoutForm.locator('button[type=submit]').click();
    await page.waitForURL('/');
}

/** イベント作成フォームに必要な全フィールドを入力して送信する */
export async function createEvent(page: Page, title: string): Promise<string> {
    await page.goto('/events/create');
    await page.fill('input[name=title]', title);
    await page.selectOption('select[name=category]', { index: 1 });
    await page.selectOption('select[name=prefecture]', { label: '東京都' });
    await page.fill('input[name=location]', 'テスト会場');
    await page.fill('input[name=event_date]', '2030-12-31T10:00');
    await page.fill('input[name=end_date]', '2030-12-31T18:00');
    await page.fill('input[name=capacity]', '10');
    await page.getByRole('button', { name: '作成する' }).click();
    await page.waitForURL(/\/events\/\d+$/);
    return page.url();
}
