<?php
/**
 * WHOIS AJAX API Endpoint
 * GET/POST ?action=whois&domain=example.com
 * GET/POST ?action=check&domain=example&tlds[]=.com&tlds[]=.net
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/whois.php';

// CORS - sadece kendi sitenizden
header('Access-Control-Allow-Origin: ' . SITE_URL);

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function getClientIP(): string {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_REAL_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

function checkRateLimit(string $ip): bool {
    try {
        $db  = getDB();
        $today = date('Y-m-d');
        $stmt = $db->query("SELECT count FROM rate_limits WHERE ip_address=? AND query_date=?", [$ip, $today]);
        if (!$stmt) return true;
        $row = $stmt->fetch();
        if (!$row) {
            $db->query("INSERT INTO rate_limits (ip_address, query_date, count) VALUES (?,?,1)", [$ip, $today]);
            return true;
        }
        if ($row['count'] >= MAX_QUERIES_PER_IP) return false;
        $db->query("UPDATE rate_limits SET count=count+1 WHERE ip_address=? AND query_date=?", [$ip, $today]);
        return true;
    } catch (Exception $e) {
        return true; // DB yoksa izin ver
    }
}

function getFromCache(string $domain): ?array {
    try {
        $db   = getDB();
        $stmt = $db->query(
            "SELECT * FROM whois_cache WHERE domain=? AND expires_at > NOW()",
            [strtolower($domain)]
        );
        if (!$stmt) return null;
        $row = $stmt->fetch();
        if (!$row) return null;
        $row['name_servers'] = json_decode($row['name_servers'] ?? '[]', true);
        $row['status']       = json_decode($row['status'] ?? '[]', true);
        $row['from_cache']   = true;
        return $row;
    } catch (Exception $e) {
        return null;
    }
}

function saveToCache(array $result): void {
    try {
        $db = getDB();
        if (!Database::isConnected()) return;
        $expires = date('Y-m-d H:i:s', time() + WHOIS_CACHE_TTL);
        $db->query(
            "INSERT INTO whois_cache 
             (domain, is_available, whois_raw, registrar, registrant, creation_date, expiry_date, name_servers, status, cached_at, expires_at)
             VALUES (?,?,?,?,?,?,?,?,?, NOW(),?)
             ON DUPLICATE KEY UPDATE
             is_available=VALUES(is_available), whois_raw=VALUES(whois_raw), registrar=VALUES(registrar),
             registrant=VALUES(registrant), creation_date=VALUES(creation_date), expiry_date=VALUES(expiry_date),
             name_servers=VALUES(name_servers), status=VALUES(status), cached_at=NOW(), expires_at=VALUES(expires_at)",
            [
                strtolower($result['domain']),
                isset($result['is_available']) ? (int)$result['is_available'] : null,
                $result['raw'] ?? null,
                $result['registrar'] ?? null,
                $result['registrant'] ?? null,
                $result['creation_date'] ?? null,
                $result['expiry_date'] ?? null,
                json_encode($result['name_servers'] ?? []),
                json_encode($result['status'] ?? []),
                $expires,
            ]
        );
    } catch (Exception $e) {
        // sessizce geç
    }
}

function logQuery(string $domain, string $tld, string $sld, string $ip, ?bool $available): void {
    try {
        $db = getDB();
        if (!Database::isConnected()) return;
        $db->query(
            "INSERT INTO whois_queries (domain, tld, sld, ip_address, is_available) VALUES (?,?,?,?,?)",
            [$domain, $tld, $sld, $ip, $available === null ? null : (int)$available]
        );
        // TLD istatistik
        $db->query(
            "INSERT INTO domain_stats (tld, search_count) VALUES (?,1)
             ON DUPLICATE KEY UPDATE search_count=search_count+1",
            [$tld]
        );
    } catch (Exception $e) {}
}

// ──────────────────────────────────────────────
$action = $_REQUEST['action'] ?? 'whois';
$ip     = getClientIP();

// Rate limit
if (!checkRateLimit($ip)) {
    jsonResponse(['success' => false, 'error' => 'Çok fazla sorgu. Lütfen daha sonra tekrar deneyin.'], 429);
}

// ── Aksiyon: Tekli WHOIS sorgusu ──────────────
if ($action === 'whois') {
    $domain = trim($_REQUEST['domain'] ?? '');
    if (!$domain) {
        jsonResponse(['success' => false, 'error' => 'Alan adı gereklidir.'], 400);
    }

    // Domain doğrulama
    $domain = strtolower($domain);
    $domain = preg_replace('#^https?://(www\.)?#', '', $domain);

    // Cache'den kontrol
    $cached = getFromCache($domain);
    if ($cached) {
        $cached['cart_url'] = buildCartUrl($cached['sld'] ?? '', $cached['tld'] ?? '');
        jsonResponse(['success' => true, 'data' => $cached]);
    }

    $whois   = new WhoisLookup();
    $result  = $whois->query($domain);

    if ($result['success']) {
        saveToCache($result);
        logQuery($result['domain'], $result['tld'], $result['sld'], $ip, $result['is_available']);
    }

    $result['cart_url'] = buildCartUrl($result['sld'] ?? '', $result['tld'] ?? '');
    jsonResponse(['success' => true, 'data' => $result]);
}

// ── Aksiyon: Çoklu TLD kontrol ────────────────
if ($action === 'check') {
    $sld  = trim($_REQUEST['domain'] ?? '');
    $tlds = $_REQUEST['tlds'] ?? [];

    if (!$sld || empty($tlds)) {
        jsonResponse(['success' => false, 'error' => 'Domain ve TLD gereklidir.'], 400);
    }

    if (!is_array($tlds)) $tlds = [$tlds];
    $tlds = array_slice($tlds, 0, 20); // Maksimum 20 TLD

    $whois   = new WhoisLookup();
    $results = [];

    foreach ($tlds as $tld) {
        $tld    = '.' . ltrim($tld, '.');
        $domain = $sld . $tld;

        $cached = getFromCache($domain);
        if ($cached) {
            $cached['cart_url'] = buildCartUrl($sld, $tld);
            $results[]          = $cached;
            continue;
        }

        $result = $whois->query($domain);
        if ($result['success']) {
            saveToCache($result);
            logQuery($result['domain'], $result['tld'], $result['sld'], $ip, $result['is_available']);
        }
        $result['cart_url'] = buildCartUrl($sld, $tld);
        $results[]          = $result;

        usleep(200000); // 0.2 sn bekleme - WHOIS sunucularını bunaltmamak için
    }

    jsonResponse(['success' => true, 'data' => $results]);
}

// ── Aksiyon: TLD listesi ───────────────────────
if ($action === 'tlds') {
    jsonResponse(['success' => true, 'data' => WhoisLookup::getPopularTLDs()]);
}

jsonResponse(['success' => false, 'error' => 'Geçersiz aksiyon.'], 400);

// ── Yardımcı fonksiyonlar ─────────────────────
function buildCartUrl(string $sld, string $tld): string {
    if (!$sld || !$tld) return WISECP_CART_URL;
    $tldClean = ltrim($tld, '.');
    return WISECP_CART_URL . '&sld=' . urlencode($sld) . '&tld=.' . urlencode($tldClean);
}
