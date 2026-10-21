// Optional browser test tool, not an application dependency. Uses an isolated seeded development instance.
const {chromium} = require('playwright');
const assert = require('node:assert/strict');

(async () => {
  const base = new URL(process.argv[2] || 'http://127.0.0.1:8080');
  assert(['127.0.0.1', 'localhost'].includes(base.hostname) && base.protocol === 'http:');
  const browser = await chromium.launch({headless: true, ...(process.env.SAMLA_BROWSER_EXECUTABLE ? {executablePath: process.env.SAMLA_BROWSER_EXECUTABLE} : {})});
  try {
    const page = await browser.newPage({viewport: {width: 1440, height: 1080}});
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    for (const width of [1440, 390]) {
      await page.setViewportSize({width, height: width === 390 ? 844 : 1080});
      for (const route of ['/', '/users', '/groups']) {
        const response = await page.goto(new URL(route, base).href);
        assert.equal(response.status(), 200);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `Page overflow: ${route}, ${width}px`);
      }
    }
    const name = 'Browser Test ' + Date.now();
    await page.getByLabel('Gruppenname', {exact: true}).first().fill(name);
    await page.getByRole('button', {name: 'Gruppe anlegen'}).click();
    await page.getByRole('status').waitFor();
    let group = page.locator('article').filter({has: page.getByRole('heading', {name, exact: true})});
    await group.locator('summary').click();
    await group.getByLabel('Gruppenname', {exact: true}).fill(name + ' geändert');
    await group.getByRole('button', {name: 'Speichern'}).click();
    group = page.locator('article').filter({has: page.getByRole('heading', {name: name + ' geändert', exact: true})});
    await group.locator('summary').click();
    await group.getByLabel('Benutzerkennung aus dem Benutzerverzeichnis').fill('dev.member');
    await group.getByRole('button', {name: 'Anwenden'}).click();
    await page.getByRole('status').waitFor();
    await page.getByRole('link', {name: 'Benutzer', exact: false}).first().click();
    await page.getByRole('heading', {name: 'Die Menschen hinter dem Team.', exact: true}).waitFor();
    assert.deepEqual(errors, []);
    console.log(JSON.stringify({browser: await browser.version(), viewports: ['1440x1080', '390x844'], result: 'passed'}));
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
