<?php
/**
 * CRON JOB — Zamanlanmis Guncelleme Kontrolu
 * Kullanim 1 (CLI): /usr/bin/php /home/user/public_html/admin/cron.php
 * Kullanim 2 (URL): https://siteniz.com/admin/cron.php?secret=XXXX
 *
 * cPanel Cron Jobs ornegi (her 24 saatte bir):
 *   0 0 * * * /usr/bin/php /home/user/public_html/admin/cron.php
 */

define('CRON_MODE', true);
session_start();

require_once __DIR__ . '/../includes/config.php';

// URL ile cagriliyorsa secret kontrol
if (PHP_SAPI !== 'cli') {
    $expectedSecret = md5((defined('ADMIN_PASS_PLAIN') ? 'admin123' : 'default') . (defined('SITE_URL') ? SITE_URL : ''));
    $givenSecret    = $_GET['secret'] ?? '';
    if ($givenSecret !== $expectedSecret) {
        http_response_code(403);
        die(json_encode(['error' => 'Yetkisiz erisim.']));
    }
    header('Content-Type: application/json');
}

// ── GitHub kontrol fonksiyonlari ─────────────────────────
function curlGetCron(string $url, array $headers = []): string|false {
    $ch = @curl_init($url);
    if (!$ch) return false;
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'WhoisCron/1.0',
    ]);
    $r = curl_exec($ch);
    $c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($c === 200 && $r) ? $r : false;
}

$repo   = defined('GITHUB_REPO')   ? GITHUB_REPO   : '';
$token  = defined('GITHUB_TOKEN')  ? GITHUB_TOKEN  : '';
$branch = defined('GITHUB_BRANCH') ? GITHUB_BRANCH : 'main';

$log = [
    'timestamp' => date('Y-m-d H:i:s'),
    'repo'      => $repo,
];

if (!$repo) {
    $log['status'] = 'skip';
    $log['msg']    = 'GITHUB_REPO tanimlanmamis. includes/config.php duzenleyin.';
    outputAndLog($log); exit;
}

$headers = ['User-Agent: WhoisCron/1.0', 'Accept: application/vnd.github.v3+json'];
if ($token) $headers[] = "Authorization: token {$token}";

$apiUrl = "https://api.github.com/repos/{$repo}/commits/{$branch}";
$body   = curlGetCron($apiUrl, $headers);

if (!$body) {
    $log['status'] = 'error';
    $log['msg']    = 'GitHub API ye erisilemedí.';
    outputAndLog($log); exit;
}

$data        = json_decode($body, true);
$remoteHash  = $data['sha'] ?? '';
$commitMsg   = $data['commit']['message'] ?? '';
$commitDate  = $data['commit']['author']['date'] ?? '';

$localHashFile = __DIR__ . '/.last_commit';
$localHash     = file_exists($localHashFile) ? trim(file_get_contents($localHashFile)) : '';

$hasUpdate = $remoteHash && $remoteHash !== $localHash;

$log['remote_hash']  = substr($remoteHash, 0, 12);
$log['local_hash']   = substr($localHash, 0, 12);
$log['has_update']   = $hasUpdate;
$log['commit_msg']   = $commitMsg;
$log['commit_date']  = $commitDate;

if ($hasUpdate) {
    $log['status'] = 'update_available';
    $log['msg']    = "Yeni surum mevcut: {$commitMsg}";

    // Bell bildirimi icin session dosyasina yaz
    $bellFile = __DIR__ . '/.pending_bell';
    $bellData = json_encode([
        'time'    => time(),
        'msg'     => "Yeni GitHub surumu: {$commitMsg}",
        'hash'    => $remoteHash,
        'date'    => $commitDate,
    ]);
    file_put_contents($bellFile, $bellData);

    // E-posta gonder
    if (defined('NOTIFY_EMAIL') && NOTIFY_EMAIL) {
        $to      = NOTIFY_EMAIL;
        $subject = 'Site Guncelleme Mevcut - ' . (defined('SITE_NAME') ? SITE_NAME : 'WHOIS Sitesi');
        $message = "Merhaba,\n\n";
        $message .= defined('SITE_NAME') ? SITE_NAME . " icin " : '';
        $message .= "yeni bir GitHub surumu mevcut.\n\n";
        $message .= "Commit: {$commitMsg}\n";
        $message .= "Tarih: {$commitDate}\n";
        $message .= "Hash: {$remoteHash}\n\n";
        $message .= "Admin panelinden guncelleyebilirsiniz:\n";
        $message .= (defined('SITE_URL') ? SITE_URL : '') . "/admin/?tab=github\n\n";
        $message .= "-- Otomatik bildirim";
        $headers_mail = "From: noreply@" . (defined('SITE_URL') ? parse_url(SITE_URL, PHP_URL_HOST) : 'localhost') . "\r\n";
        $headers_mail .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $sent = @mail($to, $subject, $message, $headers_mail);
        $log['email_sent'] = $sent;
    }

    // CLI modunda log yaz
    if (PHP_SAPI === 'cli') {
        echo "[" . date('Y-m-d H:i:s') . "] GUNCELLEME MEVCUT: {$commitMsg}\n";
    }
} else {
    $log['status'] = 'up_to_date';
    $log['msg']    = 'Surum guncel.';
    if (PHP_SAPI === 'cli') {
        echo "[" . date('Y-m-d H:i:s') . "] Surum guncel. Hash: " . substr($localHash,0,12) . "\n";
    }
}

// Cron log dosyasina yaz
$logFile = __DIR__ . '/cron.log';
$logLine = date('Y-m-d H:i:s') . ' | ' . ($hasUpdate ? 'UPDATE' : 'OK') . ' | ' . $log['msg'] . "\n";
$existing = file_exists($logFile) ? file_get_contents($logFile) : '';
$lines    = array_filter(explode("\n", $existing));
$lines    = array_slice($lines, -99); // Son 100 satir
array_push($lines, trim($logLine));
file_put_contents($logFile, implode("\n", $lines) . "\n");

outputAndLog($log);

function outputAndLog(array $data): void {
    if (PHP_SAPI !== 'cli') {
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
