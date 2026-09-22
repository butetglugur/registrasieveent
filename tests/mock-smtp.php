<?php
// Server SMTP tiruan (tanpa TLS) untuk pengujian. Simpan email yang diterima ke file.
$port = (int) ($argv[1] ?? 2525);
$out = $argv[2] ?? sys_get_temp_dir() . '/presensi-mock-smtp.log';
$srv = stream_socket_server('tcp://127.0.0.1:' . $port, $errno, $errstr);
if (!$srv) { fwrite(STDERR, "gagal: $errstr\n"); exit(1); }
while ($c = @stream_socket_accept($srv, -1)) {
    fwrite($c, "220 mock ESMTP\r\n");
    $data = false; $buf = ''; $env = [];
    while (($line = fgets($c)) !== false) {
        if ($data) {
            if (rtrim($line, "\r\n") === '.') { $data = false; file_put_contents($out, json_encode($env + ['data' => $buf]) . "\n", FILE_APPEND); $buf = ''; fwrite($c, "250 OK queued\r\n"); continue; }
            $buf .= $line; continue;
        }
        $cmd = strtoupper(substr(trim($line), 0, 4));
        if ($cmd === 'EHLO') fwrite($c, "250-mock\r\n250 AUTH LOGIN\r\n");
        elseif ($cmd === 'AUTH') { fwrite($c, "334 VXNlcm5hbWU6\r\n"); $env['user'] = base64_decode(trim(fgets($c))); fwrite($c, "334 UGFzc3dvcmQ6\r\n"); $env['pass'] = base64_decode(trim(fgets($c))); fwrite($c, "235 OK\r\n"); }
        elseif ($cmd === 'MAIL') { $env['from'] = trim($line); fwrite($c, "250 OK\r\n"); }
        elseif ($cmd === 'RCPT') { $env['to'] = trim($line); fwrite($c, "250 OK\r\n"); }
        elseif ($cmd === 'DATA') { $data = true; fwrite($c, "354 go\r\n"); }
        elseif ($cmd === 'QUIT') { fwrite($c, "221 bye\r\n"); break; }
        else fwrite($c, "250 OK\r\n");
    }
    fclose($c);
}
