<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Validator sederhana dengan pesan bahasa Indonesia.
 *
 * $v = Validator::make($data, ['name' => 'required|max:120'], ['name' => 'Nama']);
 * if ($v->fails()) ...
 */
final class Validator
{
    /** @var array<string,mixed> */
    private array $data;
    /** @var array<string,string|array> */
    private array $rules;
    /** @var array<string,string> */
    private array $labels;
    /** @var array<string,string[]> */
    private array $errors = [];
    private bool $ran = false;

    public function __construct(array $data, array $rules, array $labels = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->labels = $labels;
    }

    public static function make(array $data, array $rules, array $labels = []): Validator
    {
        return new Validator($data, $rules, $labels);
    }

    public function fails(): bool
    {
        $this->run();
        return !empty($this->errors);
    }

    public function passes(): bool
    {
        return !$this->fails();
    }

    /** @return array<string,string[]> */
    public function errors(): array
    {
        $this->run();
        return $this->errors;
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    private function run(): void
    {
        if ($this->ran) {
            return;
        }
        $this->ran = true;
        foreach ($this->rules as $field => $ruleSet) {
            $rules = is_array($ruleSet) ? $ruleSet : explode('|', $ruleSet);
            $value = $this->data[$field] ?? null;
            $isEmpty = $value === null || $value === '' || (is_array($value) && count($value) === 0);
            $label = $this->labels[$field] ?? ucfirst(str_replace('_', ' ', $field));

            if (in_array('required', $rules, true) && $isEmpty) {
                $this->addError($field, $label . ' wajib diisi.');
                continue;
            }
            if ($isEmpty) {
                continue;
            }
            foreach ($rules as $rule) {
                $param = null;
                if (is_string($rule) && str_contains($rule, ':')) {
                    [$rule, $param] = explode(':', $rule, 2);
                }
                $msg = $this->check((string) $rule, $param, $value, $label, $field);
                if ($msg !== null) {
                    $this->addError($field, $msg);
                    break;
                }
            }
        }
    }

    /** @param mixed $value */
    private function check(string $rule, ?string $param, $value, string $label, string $field): ?string
    {
        $str = is_scalar($value) ? (string) $value : '';
        switch ($rule) {
            case 'required':
            case 'nullable':
                return null;
            case 'string':
                return is_string($value) ? null : $label . ' harus berupa teks.';
            case 'array':
                return is_array($value) ? null : $label . ' tidak valid.';
            case 'max':
                if (is_array($value)) {
                    return count($value) <= (int) $param ? null : $label . ' maksimal ' . $param . ' item.';
                }
                if (is_numeric($value) && in_array('numeric', $this->rulesFor($field), true)) {
                    return (float) $value <= (float) $param ? null : $label . ' maksimal ' . $param . '.';
                }
                return mb_strlen($str) <= (int) $param ? null : $label . ' maksimal ' . $param . ' karakter.';
            case 'min':
                if (is_numeric($value) && in_array('numeric', $this->rulesFor($field), true)) {
                    return (float) $value >= (float) $param ? null : $label . ' minimal ' . $param . '.';
                }
                return mb_strlen($str) >= (int) $param ? null : $label . ' minimal ' . $param . ' karakter.';
            case 'email':
                return filter_var($str, FILTER_VALIDATE_EMAIL) ? null : $label . ' harus berupa alamat email yang valid.';
            case 'url':
                return safe_url($str) !== '' ? null : $label . ' harus berupa URL yang valid (diawali https://).';
            case 'numeric':
                return is_numeric($str) ? null : $label . ' harus berupa angka.';
            case 'integer':
                return preg_match('/^-?\d+$/', $str) ? null : $label . ' harus berupa bilangan bulat.';
            case 'in':
                $allowed = explode(',', (string) $param);
                if (is_array($value)) {
                    foreach ($value as $v) {
                        if (!in_array((string) $v, $allowed, true)) {
                            return 'Pilihan ' . $label . ' tidak valid.';
                        }
                    }
                    return null;
                }
                return in_array($str, $allowed, true) ? null : 'Pilihan ' . $label . ' tidak valid.';
            case 'date':
                return self::parseDate($str) !== null ? null : $label . ' harus berupa tanggal yang valid.';
            case 'regex':
                return preg_match((string) $param, $str) ? null : 'Format ' . $label . ' tidak valid.';
            case 'same':
                return $str === (string) ($this->data[$param] ?? '') ? null : 'Konfirmasi ' . strtolower($label) . ' tidak cocok.';
            case 'slug':
                return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $str) ? null : $label . ' hanya boleh huruf kecil, angka, dan tanda "-".';
            case 'username':
                return preg_match('/^[a-zA-Z0-9_.\-]{3,40}$/', $str) ? null : $label . ' 3-40 karakter: huruf, angka, titik, "_" atau "-".';
            case 'wa':
                return valid_wa(normalize_wa($str, (string) setting('wa_country_code', '62')))
                    ? null : $label . ' tidak valid. Contoh: 081234567890.';
            case 'password':
                if (mb_strlen($str) < 8) {
                    return $label . ' minimal 8 karakter.';
                }
                if (!preg_match('/[A-Za-z]/', $str) || !preg_match('/\d/', $str)) {
                    return $label . ' harus mengandung huruf dan angka.';
                }
                return null;
            default:
                throw new \InvalidArgumentException('Aturan validasi tidak dikenal: ' . $rule);
        }
    }

    private function rulesFor(string $field): array
    {
        $r = $this->rules[$field] ?? [];
        $r = is_array($r) ? $r : explode('|', $r);
        return array_map(static fn($x) => explode(':', (string) $x, 2)[0], $r);
    }

    /** Terima "Y-m-d", "Y-m-d H:i", "Y-m-dTH:i", "Y-m-d H:i:s". */
    public static function parseDate(string $value): ?string
    {
        $value = trim(str_replace('T', ' ', $value));
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $fmt) {
            $d = \DateTime::createFromFormat('!' . $fmt, $value);
            $errors = \DateTime::getLastErrors();
            $hasErrors = is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);
            if ($d !== false && !$hasErrors) {
                return $d->format($fmt === 'Y-m-d' ? 'Y-m-d' : 'Y-m-d H:i:s');
            }
        }
        return null;
    }
}
