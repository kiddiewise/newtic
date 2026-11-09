import { chromium } from 'playwright';
import { writeFile, mkdir } from 'node:fs/promises';

const origin = process.env.STAGING_URL || 'https://powderblue-dinosaur-538060.hostingersite.com';
const outputDir = 'staging-audit';
await mkdir(outputDir, { recursive: true });

const report = {
  origin,
  checkedAt: new Date().toISOString(),
  pages: [],
  notes: ['Public staging pages only; no credentials or login attempted.'],
};

let browser;
try {
  browser = await chromium.launch({ headless: true });
  const targets = [
    { name: 'mobile-home', path: '/', viewport: { width: 390, height: 844 }, mobile: true },
    { name: 'desktop-home', path: '/', viewport: { width: 1440, height: 900 }, mobile: false },
    { name: 'mobile-product', path: '/urun/demo-renkli-surf-125-gr', viewport: { width: 390, height: 844 }, mobile: true },
    { name: 'mobile-admin-login', path: '/admin/login', viewport: { width: 390, height: 844 }, mobile: true },
  ];

  for (const target of targets) {
    const context = await browser.newContext({
      viewport: target.viewport,
      isMobile: target.mobile,
      hasTouch: target.mobile,
      deviceScaleFactor: 1,
      locale: 'tr-TR',
    });

    const page = await context.newPage();
    const errors = [];
    const resourceErrors = [];
    page.on('pageerror', (error) => errors.push(error.message.slice(0, 300)));
    page.on('response', (response) => {
      if (response.status() >= 400) {
        try {
          const url = new URL(response.url());
          resourceErrors.push({ path: url.pathname, status: response.status() });
        } catch { /* ignored */ }
      }
    });

    const entry = { name: target.name, route: target.path };
    try {
      const response = await page.goto(new URL(target.path, origin).href, {
        waitUntil: 'domcontentloaded',
        timeout: 60000,
      });
      await page.waitForTimeout(1400);
      entry.status = response?.status() ?? null;
      entry.title = (await page.title()).slice(0, 180);
      entry.layout = await page.evaluate(() => ({
        documentWidth: document.documentElement.scrollWidth,
        viewportWidth: window.innerWidth,
        h1: document.querySelector('h1')?.textContent?.trim().slice(0, 120) || null,
        cssLinks: Array.from(document.querySelectorAll('link[rel="stylesheet"]')).map(x => x.getAttribute('href')),
        scripts: Array.from(document.querySelectorAll('script[src]')).map(x => x.getAttribute('src')),
        images: Array.from(document.images).map(x => ({
          src: new URL(x.currentSrc || x.src).pathname,
          loaded: x.complete && x.naturalWidth > 0,
        })).slice(0, 30),
      }));
      entry.errors = errors;
      entry.resourceErrors = resourceErrors.slice(0, 20);
      await page.screenshot({ path: `${outputDir}/${target.name}.png`, fullPage: true });
      if (target.name === 'mobile-product' && entry.status === 200) {
        const chooser = page.locator('[data-sheet-open]');
        entry.interactions = {};
        entry.interactions.quickQuantityBefore = await page.locator('[data-quick-qty]').innerText();
        await page.locator('[data-quick-plus]').click();
        entry.interactions.quickQuantityAfterPlus = await page.locator('[data-quick-qty]').innerText();
        entry.interactions.totalAfterPlus = await page.locator('[data-sticky-total]').innerText();
        await chooser.click();
        await page.waitForTimeout(450);
        entry.interactions.sheetOpen = await page.locator('[data-product-sheet]').evaluate(
          (element) => getComputedStyle(element).visibility === 'visible'
        );
        entry.interactions.quantityInputs = await page.locator('[data-order-quantity]').count();
        await page.screenshot({ path: `${outputDir}/mobile-product-sheet.png`, fullPage: false });
        await page.locator('.pdp-sheet-close').click();
        await page.evaluate(() => window.scrollTo({ top: 650, behavior: 'instant' }));
        await page.waitForTimeout(350);
        entry.interactions.stickyPriceOnScroll = await page.locator('[data-sticky-bar]').evaluate(
          (element) => element.classList.contains('is-price-visible')
        );
        await page.screenshot({ path: `${outputDir}/mobile-product-scrolled.png`, fullPage: false });
        // Public test environment only; isolated browser session, no payment/order.
        await page.locator('.pdp-sticky-add').click();
        await page.waitForURL('**/sepet', { timeout: 15000 });
        await page.waitForTimeout(450);
        entry.interactions.cartUrl = new URL(page.url()).pathname;
        entry.interactions.cartItemCount = await page.locator('.cart-item').count();
        entry.interactions.cartItemQuantity = await page.locator('.cart-update-form input[name="quantity"]').first().inputValue();
        entry.interactions.shippingText = await page.locator('.cart-mobile-summary-sheet .cart-totals').textContent();
        entry.interactions.cartOverflow = await page.evaluate(
          () => document.documentElement.scrollWidth > window.innerWidth
        );
        entry.interactions.postCartErrors = errors.slice();
        await page.screenshot({ path: `${outputDir}/mobile-cart-filled.png`, fullPage: true });
        entry.interactions.mobileCartHeaderVisible = await page.locator('.cart-mobile-header').isVisible();
        entry.interactions.bottomCartBarVisible = await page.locator('.cart-mobile-bottom').isVisible();
        entry.interactions.defaultMobileNavHidden = await page.locator('.mobile-bottom-nav').evaluate(
          (element) => getComputedStyle(element).display === 'none'
        );
        await page.locator('.cart-mobile-summary-open').click();
        await page.waitForTimeout(370);
        entry.interactions.summarySheetVisible = await page.locator('[data-cart-summary-sheet]').evaluate(
          (element) => getComputedStyle(element).visibility === 'visible'
        );
        await page.screenshot({ path: `${outputDir}/mobile-cart-summary-sheet.png`, fullPage: false });
        await page.locator('[data-cart-summary-close]').click();
        const beforePlus = await page.locator('.cart-quantity-stepper input[name="quantity"]').first().inputValue();
        await page.locator('[data-cart-plus]').first().click();
        await page.waitForLoadState('domcontentloaded');
        const afterPlus = await page.locator('.cart-quantity-stepper input').first().inputValue();
        entry.interactions.stepperBeforePlus = beforePlus;
        entry.interactions.stepperAfterPlus = afterPlus;
        await page.locator('.cart-favorite-form button').first().click();
        await page.waitForLoadState('domcontentloaded');
        entry.interactions.favoriteNowSelected = await page.locator('.cart-favorite-form button').first().getAttribute('aria-pressed');
        entry.interactions.newCartOverflow = await page.evaluate(
          () => document.documentElement.scrollWidth > innerWidth
        );
        if (!entry.interactions.mobileCartHeaderVisible || !entry.interactions.bottomCartBarVisible ||
            !entry.interactions.defaultMobileNavHidden || !entry.interactions.summarySheetVisible ||
            Number(afterPlus) !== Number(beforePlus) + 1 ||
            entry.interactions.favoriteNowSelected !== 'true' || entry.interactions.newCartOverflow) {
          throw new Error('Redesigned mobile cart regression: ' + JSON.stringify(entry.interactions));
        }

        if (entry.interactions.cartUrl !== '/sepet' || entry.interactions.cartItemQuantity !== '2') {
          throw new Error('Mobile add-to-cart interaction did not complete correctly.');
        }
      }
    } catch (error) {
      entry.error = String(error).slice(0, 600);
    }
    report.pages.push(entry);
    await context.close();
  }

  const context = await browser.newContext();
  for (const pathname of ['/robots.txt', '/.env', '/composer.json', '/vendor/autoload.php', '/build/manifest.json']) {
    try {
      const response = await context.request.get(new URL(pathname, origin).href, { timeout: 25000 });
      report.pages.push({ name: 'security-or-asset', route: pathname, status: response.status() });
    } catch (error) {
      report.pages.push({ name: 'security-or-asset', route: pathname, error: String(error).slice(0, 250) });
    }
  }
  await context.close();
} catch (error) {
  report.fatal = String(error).slice(0, 600);
} finally {
  await browser?.close();
  await writeFile(`${outputDir}/report.json`, JSON.stringify(report, null, 2));
  console.log(JSON.stringify(report, null, 2));
}
