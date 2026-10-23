/* Isolated render-only browser checks. No production service or credentials. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {spawn} = require('node:child_process');
const {chromium} = require(process.env.SAMLA_PLAYWRIGHT_MODULE);
const output = process.env.SAMLA_UI_OUTPUT;
assert(output && process.env.SAMLA_PLAYWRIGHT_MODULE, 'Explicit test module and artifact paths required');
fs.mkdirSync(output, {recursive: true});
const server = spawn('php', ['-S','127.0.0.1:18080','tests/Tools/ui-fixture.php'], {env: {...process.env,SAMLA_UI_FIXTURE:'1'}, stdio:['ignore','pipe','pipe']});
let serverLog='';server.stdout.on('data', b => {serverLog+=b;});server.stderr.on('data', b => {serverLog+=b;});
(async () => {
  let browser;
  try {
    for (let i=0;i<100;i++) {
      if (server.exitCode !== null) throw Error(serverLog);
      try {if ((await fetch('http://127.0.0.1:18080/auth/login')).ok) break;} catch {}
      if (i===99) throw Error('Fixture startup timeout');
      await new Promise(r=>setTimeout(r,100));
    }
    browser=await chromium.launch({headless:true});
    const page=await browser.newPage();
    const errors=[];page.on('pageerror',e=>errors.push(e.message));
    const routes=[['login','/auth/login'],['start','/'],['files','/files'],['detail','/files/detail'],['permissions','/permissions'],['space','/administration/spaces'],['space-create','/administration/spaces?create=1'],['media','/media'],['media-detail','/media/detail'],['catalog','/catalog'],['catalog-draft','/catalog?draft=1']];
    for (const width of [1440,1024,390,320]) {
      await page.setViewportSize({width,height:width<760?844:1000});
      for (const [name,route] of routes) {
        const response=await page.goto('http://127.0.0.1:18080'+route);
        assert.equal(response.status(),200,route);
        await page.getByRole('heading',{level:1}).waitFor();
        assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,`Overflow ${name} at ${width}`);
        await page.screenshot({path:path.join(output,`${name}-${width}.png`),fullPage:true});
      }
      await page.goto('http://127.0.0.1:18080/files');
      if(width<760){
        const toggle=page.getByRole('button',{name:'Menü',exact:true});await toggle.waitFor();
        assert.equal(await toggle.getAttribute('aria-expanded'),'false');
        await toggle.click();assert.equal(await toggle.getAttribute('aria-expanded'),'true');
        await toggle.press('Escape');assert.equal(await toggle.getAttribute('aria-expanded'),'false');
      }
      const firstRow=await page.locator('.file-row').first().boundingBox();
      assert(firstRow.y < (width<760?844:1000)-44, 'File content must be visible without scrolling past forms');
      const contrast=await page.locator('.folder-path a').first().evaluate(el=>{
        const luminance=css=>{
          const rgb=css.match(/[0-9.]+/g).slice(0,3).map(Number).map(n=>n/255).map(n=>n<=0.04045?n/12.92:((n+0.055)/1.055)**2.4);
          return rgb[0]*0.2126+rgb[1]*0.7152+rgb[2]*0.0722;
        };
        const a=luminance(getComputedStyle(el).color), b=luminance(getComputedStyle(document.body).backgroundColor);
        return (Math.max(a,b)+0.05)/(Math.min(a,b)+0.05);
      });
      assert(contrast>=4.5, 'Folder breadcrumbs need readable contrast');
      await page.getByLabel('Notiz <Team>.txt für Export auswählen').check();
      assert.equal(await page.locator('[data-selection-count]').innerText(),'1 Datei ausgewählt');
      await page.getByRole('button',{name:'Auswahl aufheben'}).click();
      assert.equal(await page.getByLabel('Notiz <Team>.txt für Export auswählen').isChecked(),false);
      await page.getByText('Datei hochladen',{exact:true}).click();
      await page.getByLabel(/Datei, maximal/).waitFor();
      await page.getByText('Datei hochladen',{exact:true}).press('Escape');
      assert.equal(await page.getByLabel(/Datei, maximal/).isVisible(),false);
      await page.goto('http://127.0.0.1:18080/files?reader=1');
      assert.equal(await page.getByText('Datei hochladen',{exact:true}).count(),0);
      assert.equal(await page.locator('nav[aria-label="Verwaltung"]').count(),0);
      await page.goto('http://127.0.0.1:18080/catalog?draft=1');
      assert.equal(await page.getByLabel('Beschreibung',{exact:true}).inputValue(),'Entwurf <nicht verlieren>');
      assert.equal(await page.locator('input[name="catalog_revision"]').inputValue(),'1');
    }
    const noJs=await browser.newContext({javaScriptEnabled:false,viewport:{width:390,height:844}});
    const fallback=await noJs.newPage();await fallback.goto('http://127.0.0.1:18080/files');
    assert.equal(await fallback.locator('nav[aria-label="Arbeitsplatz"]').isVisible(),true);
    await fallback.getByText('Neuer Ordner',{exact:true}).click();assert.equal(await fallback.getByLabel('Ordnername').isVisible(),true);
    assert.deepEqual(errors,[]);
    fs.writeFileSync(path.join(output,'result.json'),JSON.stringify({result:'passed',browser:await browser.version(),viewports:[1440,1024,390,320],screenshots:44,checks:'synthetic real templates, no horizontal page overflow, mobile menu and Escape, file selection/reset, progressive disclosures, reader navigation, retained draft conditions, no-JS fallback'},null,2));
    console.log(fs.readFileSync(path.join(output,'result.json'),'utf8'));
  } catch(e){fs.writeFileSync(path.join(output,'fixture.log'),serverLog);throw e;}
  finally {await browser?.close();server.kill('SIGTERM');}
})().catch(e=>{console.error(e);process.exitCode=1;});
