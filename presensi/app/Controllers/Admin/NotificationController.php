<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Crypt;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\Setting;
use App\Services\Notifier;

/**
 * Pengaturan notifikasi peserta (khusus Administrator).
 * ⚠️ Mengaktifkan fitur ini berarti data peserta dikirim ke layanan pihak ketiga.
 */
final class NotificationController extends Controller
{
    public function index(Request $request): string
    {
        $status = $request->query('status');
        return view('admin.notifications.index', [
            'logs'    => Notification::paginate(max(1, $request->int('page', 1)), $status),
            'counts'  => Notification::counts(),
            'status'  => $status,
            'hasSecret' => array_combine(Notifier::SECRET_KEYS, array_map(
                static fn($k) => (string) Setting::get($k, '') !== '', Notifier::SECRET_KEYS
            )),
            'preview' => Notifier::render((string) setting('notify_wa_template', Notifier::DEFAULT_WA_TEMPLATE), Notifier::sampleVars()),
        ]);
    }

    public function update(Request $request): Response
    {
        $back = route('admin.notifications');
        $data = $request->only([
            'notify_wa_provider', 'notify_wablas_domain', 'notify_wa_template',
            'notify_email_driver', 'notify_smtp_host', 'notify_smtp_port', 'notify_smtp_encryption', 'notify_smtp_username',
            'notify_mail_from', 'notify_mail_from_name', 'notify_email_subject', 'notify_email_template', 'notify_webhook_url',
        ]);
        // Template boleh multi-baris: ambil mentah (hanya buang karakter kontrol).
        foreach (['notify_wa_template', 'notify_email_template'] as $k) {
            $raw = $request->input($k, '');
            $data[$k] = is_string($raw) ? trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', str_replace("\r\n", "\n", $raw)) ?? '') : '';
        }
        $flags = [
            'notify_enabled'         => $request->bool('notify_enabled'),
            'notify_email_enabled'   => $request->bool('notify_email_enabled'),
            'notify_webhook_enabled' => $request->bool('notify_webhook_enabled'),
        ];
        $rules = [
            'notify_wa_provider'     => 'required|in:' . implode(',', array_keys(Notifier::WA_PROVIDERS)),
            'notify_wablas_domain'   => 'nullable|url|max:120',
            'notify_wa_template'     => 'nullable|max:2000',
            'notify_email_driver'    => 'required|in:smtp,mail',
            'notify_smtp_host'       => 'nullable|max:120|regex:/^[a-z0-9.\-]+$/i',
            'notify_smtp_port'       => 'nullable|integer|numeric|min:1|max:65535',
            'notify_smtp_encryption' => 'required|in:tls,ssl,none',
            'notify_smtp_username'   => 'nullable|max:150',
            'notify_mail_from'       => 'nullable|email|max:150',
            'notify_mail_from_name'  => 'nullable|max:100',
            'notify_email_subject'   => 'nullable|max:200',
            'notify_email_template'  => 'nullable|max:4000',
            'notify_webhook_url'     => 'nullable|url|max:190',
        ];
        $labels = [
            'notify_wablas_domain' => 'Domain server Wablas', 'notify_wa_template' => 'Template WhatsApp',
            'notify_smtp_host' => 'Host SMTP', 'notify_smtp_port' => 'Port SMTP', 'notify_mail_from' => 'Email pengirim',
            'notify_email_subject' => 'Subjek email', 'notify_email_template' => 'Template email', 'notify_webhook_url' => 'URL webhook',
        ];
        $v = \App\Core\Validator::make($data, $rules, $labels);
        $v->fails();
        // Wajib-bersyarat sesuai kanal yang diaktifkan.
        if ($data['notify_wa_provider'] === 'wablas' && $data['notify_wablas_domain'] === '') {
            $v->addError('notify_wablas_domain', 'Domain server Wablas wajib diisi, mis. https://jkt.wablas.com');
        }
        if ($data['notify_wa_provider'] !== 'none' && $request->str('notify_wa_token') === '' && (string) Setting::get('notify_wa_token', '') === '') {
            $v->addError('notify_wa_token', 'Token API WhatsApp wajib diisi.');
        }
        if ($flags['notify_email_enabled']) {
            if ($data['notify_mail_from'] === '') {
                $v->addError('notify_mail_from', 'Email pengirim wajib diisi.');
            }
            if ($data['notify_email_driver'] === 'smtp' && $data['notify_smtp_host'] === '') {
                $v->addError('notify_smtp_host', 'Host SMTP wajib diisi.');
            }
        }
        if ($flags['notify_webhook_enabled']) {
            $url = $data['notify_webhook_url'];
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            if ($url === '') {
                $v->addError('notify_webhook_url', 'URL webhook wajib diisi.');
            } elseif (parse_url($url, PHP_URL_SCHEME) !== 'https' && !in_array($host, ['localhost', '127.0.0.1'], true)) {
                $v->addError('notify_webhook_url', 'URL webhook wajib memakai https:// agar data peserta terenkripsi.');
            }
        }
        if ($v->errors()) {
            throw new \App\Core\ValidationException($v->errors(), $request->all(), $back);
        }

        $save = $data;
        foreach ($flags as $k => $on) {
            $save[$k] = $on ? '1' : '0';
        }
        if ($save['notify_wa_template'] === '') {
            $save['notify_wa_template'] = Notifier::DEFAULT_WA_TEMPLATE;
        }
        if ($save['notify_email_template'] === '') {
            $save['notify_email_template'] = Notifier::DEFAULT_EMAIL_TEMPLATE;
        }
        if ($save['notify_email_subject'] === '') {
            $save['notify_email_subject'] = Notifier::DEFAULT_EMAIL_SUBJECT;
        }
        // Rahasia: kosong = pertahankan nilai lama; centang "hapus" = kosongkan.
        $secretInputs = ['notify_wa_token' => 'notify_wa_token', 'notify_smtp_password' => 'notify_smtp_password', 'notify_webhook_secret' => 'notify_webhook_secret'];
        foreach ($secretInputs as $input => $key) {
            $val = (string) $request->input($input, '');
            if ($request->bool('clear_' . $input)) {
                $save[$key] = '';
            } elseif ($val !== '') {
                $save[$key] = Crypt::encrypt(trim($val));
            }
        }
        Setting::setMany($save);
        ActivityLog::record('settings_update', 'Memperbarui pengaturan notifikasi peserta (' . ($flags['notify_enabled'] ? 'aktif' : 'nonaktif') . ')');
        return Response::redirect($back)->with('success', 'Pengaturan notifikasi disimpan.');
    }

    /** Kirim pesan tes langsung (tanpa antrean). */
    public function test(Request $request): Response
    {
        $back = route('admin.notifications');
        $channel = $request->str('channel');
        $target = mb_substr($request->str('target'), 0, 150);
        $vars = Notifier::sampleVars();
        try {
            if ($channel === 'whatsapp') {
                $provider = (string) Setting::get('notify_wa_provider', 'none');
                $wa = normalize_wa($target, (string) setting('wa_country_code', '62'));
                if (!valid_wa($wa)) {
                    return Response::redirect($back)->with('error', 'Nomor WhatsApp tujuan tes tidak valid.');
                }
                Notifier::sendWhatsApp($provider, $wa, '[TES] ' . Notifier::render((string) Setting::get('notify_wa_template', Notifier::DEFAULT_WA_TEMPLATE), $vars));
            } elseif ($channel === 'email') {
                Notifier::sendEmail($target, '[TES] ' . Notifier::render((string) Setting::get('notify_email_subject', Notifier::DEFAULT_EMAIL_SUBJECT), $vars),
                    Notifier::render((string) Setting::get('notify_email_template', Notifier::DEFAULT_EMAIL_TEMPLATE), $vars));
            } elseif ($channel === 'webhook') {
                Notifier::sendWebhook((string) Setting::get('notify_webhook_url', ''), (string) json_encode(['event' => 'test', 'sent_at' => date('c'), 'message' => 'Tes webhook dari ' . app_name()]));
                $target = (string) Setting::get('notify_webhook_url', '');
            } else {
                throw new HttpException(400);
            }
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            ActivityLog::record('notify_test', 'Tes notifikasi ' . $channel . ' GAGAL: ' . mb_substr($e->getMessage(), 0, 150));
            return Response::redirect($back)->with('error', 'Tes ' . $channel . ' gagal: ' . $e->getMessage());
        }
        ActivityLog::record('notify_test', 'Tes notifikasi ' . $channel . ' berhasil ke ' . str_limit($target, 60));
        return Response::redirect($back)->with('success', 'Tes ' . $channel . ' berhasil dikirim ke ' . $target . '.');
    }

    public function retry(Request $request, string $id): Response
    {
        $row = Notification::find((int) $id);
        if (!$row) {
            throw new HttpException(404);
        }
        Notification::retry((int) $row['id']);
        $r = Notifier::process([(int) $row['id']], 0);
        $ok = $r['sent'] > 0;
        return back(route('admin.notifications'))->with($ok ? 'success' : 'error', $ok ? 'Notifikasi terkirim ulang.' : 'Masih gagal: ' . (string) (Notification::find((int) $row['id'])['last_error'] ?? '-'));
    }

    public function processQueue(Request $request): Response
    {
        $r = Notifier::process([], 50);
        return back(route('admin.notifications'))->with('success', sprintf('Antrean diproses: %d terkirim, %d gagal.', $r['sent'], $r['failed']));
    }
}
