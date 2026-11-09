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
