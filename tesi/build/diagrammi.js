// Esporta in PNG i diagrammi definiti in diagrammi.html (uno per ogni elemento <svg>).
// Uso: node tesi/build/diagrammi.js   (richiede Playwright con Chromium)
const { chromium } = require('playwright');
const path = require('path');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1200, height: 900 }, deviceScaleFactor: 2 });
  await page.goto('file://' + path.join(__dirname, 'diagrammi.html'));
  const ids = await page.$$eval('svg', (els) => els.map((e) => e.id));
  for (const id of ids) {
    await page.locator('#' + id).screenshot({ path: path.join(__dirname, '..', 'figure', id + '.png') });
    console.log('creato', id + '.png');
  }
  await browser.close();
})();
