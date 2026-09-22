// UI test di browser sungguhan (Chromium headless via Playwright).
// Prasyarat: aplikasi sudah terinstal (jalankan tests/e2e.php dulu), server di UI_URL.
// Jalankan: node tests/ui.mjs
import { createRequire } from 'module';
const require = createRequire(import.meta.url);
const { chromium } = require(process.env.PW_PATH || 'playwright');
import fs from 'fs';

const BASE = process.env.UI_URL || 'http://127.0.0.1:8080';
const OUT = process.env.SHOT_DIR || './screens';
const USER = process.env.UI_USER || 'admin';
const PASS = process.env.UI_PASS || 'BaruSekali9';
fs.mkdirSync(OUT, { recursive: true });

let pass = 0, fail = 0; const failures = [];
const ok = (c, m, d = '') => { if (c) { pass++; console.log('  \x1b[32m✔\x1b[0m ' + m); } else { fail++; failures.push(m + ' ' + d); console.log('  \x1b[31m✘ ' + m + '\x1b[0m ' + d); } };
const group = (g) => console.log('\n\x1b[1;35m▸ ' + g + '\x1b[0m');

const browser = await chromium.launch({ executablePath: process.env.CHROME || undefined });
const errors = [];
function watch(page, label) {
  page.on('pageerror', (e) => errors.push(label + ': ' + e.message));
  page.on('console', (m) => {
    // 409 = respons check-in "sudah hadir" yang memang diharapkan
    if (m.type() === 'error' && !/status of 409/.test(m.text())) errors.push(label + ' console: ' + m.text());
  });
}

const desktop = await browser.newContext({ viewport: { width: 1366, height: 900 }, acceptDownloads: true });
const page = await desktop.newPage();
watch(page, 'desktop');

group('Login & dasbor');
await page.goto(BASE + '/admin/login');
await page.screenshot({ path: `${OUT}/01-login.png` });
await page.fill('#username', USER);
await page.fill('#password', PASS);
await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
ok(page.url().endsWith('/admin'), 'login lewat form di browser', page.url());
ok(await page.locator('.toast').count() > 0, 'toast selamat datang muncul');
await page.screenshot({ path: `${OUT}/02-dashboard.png`, fullPage: true });

group('Buat event dengan form builder (JS)');
await page.goto(BASE + '/admin/event/baru');
await page.fill('#title', 'Seminar UI Test Nasional');
ok((await page.textContent('[data-slug-preview]')).trim() === 'seminar-ui-test-nasional', 'preview slug otomatis dari judul');
await page.click('[data-builder-add]');
await page.fill('.builder-item:nth-child(1) [data-k=label]', 'Jabatan');
await page.check('.builder-item:nth-child(1) [data-k=required]');
await page.click('[data-builder-add]');
await page.fill('.builder-item:nth-child(2) [data-k=label]', 'Sesi');
await page.selectOption('.builder-item:nth-child(2) [data-k=type]', 'radio');
ok(await page.locator('.builder-item:nth-child(2).has-opts').count() === 1, 'textarea opsi muncul untuk tipe pilihan');
await page.fill('.builder-item:nth-child(2) [data-k=options]', 'Pagi\nSiang');
await page.click('[data-builder-add]');
await page.fill('.builder-item:nth-child(3) [data-k=label]', 'Hapus saya');
await page.click('.builder-item:nth-child(3) [data-act=del]');
ok(await page.locator('.builder-item').count() === 2, 'hapus kolom di builder');
await page.click('.builder-item:nth-child(2) [data-act=up]');
ok((await page.inputValue('.builder-item:nth-child(1) [data-k=label]')) === 'Sesi', 'urutkan kolom (naik)');
const json = JSON.parse(await page.inputValue('[data-builder-input]'));
ok(json.length === 2 && json[0].options.join() === 'Pagi,Siang', 'JSON field tersinkron', JSON.stringify(json));
await page.fill('#location', 'Gedung Serbaguna Medan');
await page.fill('#starts_at', '2026-12-20T09:00');
await page.fill('#group_link', 'https://chat.whatsapp.com/UITEST');
await page.click('label.theme-opt:has(input[value=sunset])');
await page.screenshot({ path: `${OUT}/03-event-form.png`, fullPage: true });
await Promise.all([page.waitForNavigation(), page.click('form#event-form button.btn-lg[type=submit]')]);
ok(/\/admin\/event\/\d+\/edit$/.test(page.url()), 'event tersimpan', page.url());
await page.waitForTimeout(300);
await page.click('[data-qr-modal]');
await page.waitForSelector('.modal .qr-box canvas');
ok(true, 'modal QR pendaftaran tampil');
await page.screenshot({ path: `${OUT}/04-event-edit-qr.png` });
await page.click('.modal [data-close]');

group('Pendaftaran publik (mobile)');
const mobile = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, acceptDownloads: true });
const m = await mobile.newPage();
watch(m, 'mobile');
await m.goto(BASE + '/');
await m.screenshot({ path: `${OUT}/05-m-home.png`, fullPage: true });
await m.goto(BASE + '/e/seminar-ui-test-nasional');
await m.screenshot({ path: `${OUT}/06-m-event.png`, fullPage: true });
const overflow = await m.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
ok(!overflow, 'tidak ada scroll horizontal di mobile');
await m.fill('#f-name', 'Siti Aminah');
await m.fill('#f-wa', '081377778888');
await m.fill('#f-rep', 'Universitas Sumatera Utara');
await m.check('input[name="extra[sesi]"][value="Siang"]', { force: true });
await m.fill('#x-jabatan', 'Dosen');
await m.waitForTimeout(2100); // time-trap anti-bot
await Promise.all([m.waitForNavigation(), m.click('form button[type=submit]')]);
ok(/\/t\/[A-Z0-9]{8}\?baru=1$/.test(m.url()), 'pendaftaran lewat browser sukses', m.url());
await m.waitForSelector('.qr-box canvas');
ok(true, 'QR tiket dirender di canvas');
await m.waitForTimeout(600);
await m.screenshot({ path: `${OUT}/07-m-ticket.png`, fullPage: true });
const [dl] = await Promise.all([m.waitForEvent('download'), m.click('[data-download-qr]')]);
const dlPath = `${OUT}/tiket-download.png`;
await dl.saveAs(dlPath);
ok(fs.statSync(dlPath).size > 20000, 'tiket PNG dapat diunduh', String(fs.statSync(dlPath).size));
const code = m.url().match(/\/t\/([A-Z0-9]{8})/)[1];

// Validasi error di mobile
await m.goto(BASE + '/e/seminar-ui-test-nasional');
await m.waitForTimeout(2100);
await Promise.all([m.waitForNavigation(), m.click('form button[type=submit]')]);
ok(await m.locator('.is-invalid').count() >= 2, 'kolom wajib kosong ditandai merah');
await m.screenshot({ path: `${OUT}/08-m-validation.png`, fullPage: true });

group('Check-in (input manual & pencarian)');
await page.goto(BASE + '/admin/checkin');
await page.fill('[data-manual-form] input[name=code]', code);
await page.click('[data-manual-form] button');
await page.waitForSelector('.scan-result.ok');
ok((await page.textContent('.scan-result .t')).includes('Siti Aminah'), 'check-in manual sukses');
await page.fill('[data-manual-form] input[name=code]', code);
await page.click('[data-manual-form] button');
await page.waitForSelector('.scan-result.warn');
ok(true, 'scan ulang -> peringatan sudah hadir');
await page.fill('[data-live-search]', 'siti');
await page.waitForSelector('.sr-item');
ok((await page.textContent('.search-results')).includes('Hadir'), 'pencarian live menampilkan status hadir');
await page.screenshot({ path: `${OUT}/09-checkin.png`, fullPage: true });

group('Daftar peserta, konfirmasi hapus & bulk');
await page.goto(BASE + '/admin/peserta');
await page.screenshot({ path: `${OUT}/10-peserta.png`, fullPage: true });
await page.check('[data-check-all]');
ok(await page.locator('[data-bulkbar].show').count() === 1, 'bulk bar muncul saat pilih semua');
await page.click('[data-bulkbar] button[value=delete]');
await page.waitForSelector('.modal');
ok(true, 'modal konfirmasi hapus muncul');
await page.screenshot({ path: `${OUT}/11-confirm.png` });
await page.click('.modal [data-no]');
ok(await page.locator('.modal').count() === 0, 'batal menutup modal tanpa menghapus');
await page.uncheck('[data-check-all]');
await page.fill('input[name=q]', 'siti');
await page.waitForNavigation();
ok(page.url().includes('q=siti'), 'filter pencarian otomatis (debounce)');

group('Hapus event dengan ketik-konfirmasi');
await page.goto(BASE + '/admin/event');
const row = page.locator('.event-row', { hasText: 'Seminar UI Test Nasional' });
await row.locator('[data-dropdown-toggle]').click();
await row.locator('button.danger').click();
await page.waitForSelector('.modal [data-type-input]');
ok(await page.locator('.modal [data-yes]').isDisabled(), 'tombol hapus nonaktif sebelum slug diketik');
await page.fill('.modal [data-type-input]', 'seminar-ui-test-nasional');
await Promise.all([page.waitForNavigation(), page.click('.modal [data-yes]')]);
ok((await page.textContent('body')).includes('dihapus'), 'event terhapus setelah konfirmasi');

group('Mode gelap & responsif admin');
await page.goto(BASE + '/admin');
await page.click('.topbar [data-theme-toggle]');
ok((await page.getAttribute('html', 'data-theme')) === 'dark', 'mode gelap aktif');
await page.screenshot({ path: `${OUT}/12-dashboard-dark.png`, fullPage: true });
await page.click('.topbar [data-theme-toggle]');
const madmin = await mobile.newPage();
watch(madmin, 'mobile-admin');
await madmin.goto(BASE + '/admin/login');
await madmin.fill('#username', USER);
await madmin.fill('#password', PASS);
await Promise.all([madmin.waitForNavigation(), madmin.click('button[type=submit]')]);
await madmin.goto(BASE + '/admin/peserta');
ok(!(await madmin.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1)), 'admin peserta tanpa scroll horizontal di mobile');
await madmin.screenshot({ path: `${OUT}/13-m-admin-peserta.png`, fullPage: true });
await madmin.click('[data-sidebar-toggle]');
await madmin.waitForTimeout(350);
ok(await madmin.evaluate(() => document.body.classList.contains('sidebar-open')), 'menu sidebar mobile terbuka');
await madmin.screenshot({ path: `${OUT}/14-m-sidebar.png` });

group('Halaman notifikasi peserta');
await page.goto(BASE + '/admin/notifikasi');
ok((await page.textContent('.alert-warning')).includes('integrasi sistem eksternal'), 'peringatan integrasi eksternal tampil');
await page.check('input[name=notify_wa_provider][value=none]', { force: true });
ok(await page.locator('#notify_wa_token').isHidden(), 'field token disembunyikan bila provider nonaktif');
await page.check('input[name=notify_wa_provider][value=wablas]', { force: true });
ok(await page.locator('#notify_wablas_domain').isVisible(), 'field domain Wablas muncul saat Wablas dipilih');
await page.check('input[name=notify_wa_provider][value=fonnte]', { force: true });
ok(await page.locator('#notify_wablas_domain').isHidden() && await page.locator('#notify_wa_token').isVisible(), 'Fonnte: token tampil, domain Wablas tersembunyi');
await page.screenshot({ path: `${OUT}/15-notifikasi.png`, fullPage: true });

group('Error JavaScript & CSP');
ok(errors.length === 0, 'tidak ada error JS / pelanggaran CSP', errors.join(' | '));

await browser.close();
console.log('\n' + '─'.repeat(60));
if (fail === 0) console.log(`\x1b[1;32m✔ SEMUA LULUS: ${pass} assertion UI\x1b[0m`);
else { console.log(`\x1b[1;31m✘ GAGAL: ${fail} dari ${pass + fail}\x1b[0m`); failures.forEach((f) => console.log('  - ' + f)); }
process.exit(fail ? 1 : 0);
