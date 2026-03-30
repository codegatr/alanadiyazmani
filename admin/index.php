<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

// ── Kimlik ───────────────────────────────────────────────────────────────────
define('ADMIN_USER',        'admin');
define('ADMIN_PASS_PLAIN',  'admin123');
define('ADMIN_SESSION_KEY', 'whois_admin_logged_in');

$_passwdFile = __DIR__ . '/passwd.php';
if (file_exists($_passwdFile)) { @include_once $_passwdFile; }

// ── Ayar okuma/yazma (DB öncelikli, config.php fallback) ─────────────────────
function getSetting(string $key, string $default = ''): string {
    try {
        $db = getDB();
        if (!Database::isConnected()) return defined($key) ? (string)constant($key) : $default;
        $r = $db->query("SELECT sval FROM site_settings WHERE skey=?", [$key]);
        if ($r) { $row = $r->fetch(); if ($row) return (string)$row['sval']; }
    } catch(Exception $e) {}
    return defined($key) ? (string)constant($key) : $default;
}

function saveSetting(string $key, string $value): bool {
    try {
        $db = getDB();
        if (!Database::isConnected()) return false;
        $r = $db->query(
            "INSERT INTO site_settings (skey, sval) VALUES (?,?) ON DUPLICATE KEY UPDATE sval=VALUES(sval)",
            [$key, $value]
        );
        return $r !== false;
    } catch(Exception $e) { return false; }
}

function saveSettings(array $data): array {
    // Önce DB'ye kaydet
    $dbOk = true;
    foreach ($data as $k => $v) {
        if (!saveSetting($k, (string)$v)) $dbOk = false;
    }

    // Sonra config.php'ye de yaz (mümkünse)
    $configFile  = __DIR__ . '/../includes/config.php';
    $configWritten = false;
    if (is_writable($configFile)) {
        $tpl  = "<?php\n// WHOIS SORGULAMA SITESI — Otomatik Olusturuldu\n\n";
        $tpl .= "define('DB_HOST',    " . var_export($data['DB_HOST']    ?? 'localhost',true) . ");\n";
        $tpl .= "define('DB_NAME',    " . var_export($data['DB_NAME']    ?? '',true) . ");\n";
        $tpl .= "define('DB_USER',    " . var_export($data['DB_USER']    ?? '',true) . ");\n";
        $tpl .= "define('DB_PASS',    " . var_export($data['DB_PASS']    ?? '',true) . ");\n";
        $tpl .= "define('DB_CHARSET', 'utf8mb4');\n\n";
        $tpl .= "define('WISECP_URL',      " . var_export($data['WISECP_URL']      ?? '',true) . ");\n";
        $tpl .= "define('WISECP_CART_URL', WISECP_URL . '/cart/?domain=register');\n\n";
        $tpl .= "define('SITE_NAME',        " . var_export($data['SITE_NAME']        ?? '',true) . ");\n";
        $tpl .= "define('SITE_URL',         " . var_export($data['SITE_URL']         ?? '',true) . ");\n";
        $tpl .= "define('SITE_DESCRIPTION', " . var_export($data['SITE_DESCRIPTION'] ?? '',true) . ");\n\n";
        $tpl .= "define('WHOIS_CACHE_TTL',    " . (int)($data['WHOIS_CACHE_TTL']    ?? 3600) . ");\n";
        $tpl .= "define('MAX_QUERIES_PER_IP', " . (int)($data['MAX_QUERIES_PER_IP'] ?? 100)  . ");\n";
        $tpl .= "date_default_timezone_set('Europe/Istanbul');\n\n";
        $tpl .= "define('GOOGLE_ADSENSE_ID', " . var_export($data['GOOGLE_ADSENSE_ID'] ?? 'ca-pub-XXXXXXXXXX',true) . ");\n";
        $tpl .= "define('AD_SLOT_HEADER',    " . var_export($data['AD_SLOT_HEADER']    ?? 'XXXXXXXXXX',true) . ");\n";
        $tpl .= "define('AD_SLOT_SIDEBAR',   " . var_export($data['AD_SLOT_SIDEBAR']   ?? 'XXXXXXXXXX',true) . ");\n";
        $tpl .= "define('AD_SLOT_FOOTER',    " . var_export($data['AD_SLOT_FOOTER']    ?? 'XXXXXXXXXX',true) . ");\n";
        $tpl .= "define('ADS_ENABLED', " . (!empty($data['ADS_ENABLED']) && $data['ADS_ENABLED'] !== 'false' ? 'true' : 'false') . ");\n\n";
        $tpl .= "define('GITHUB_REPO',           " . var_export($data['GITHUB_REPO']           ?? '',true) . ");\n";
        $tpl .= "define('GITHUB_TOKEN',          " . var_export($data['GITHUB_TOKEN']          ?? '',true) . ");\n";
        $tpl .= "define('GITHUB_BRANCH',         " . var_export($data['GITHUB_BRANCH']         ?? 'main',true) . ");\n";
        $tpl .= "define('UPDATE_CHECK_INTERVAL', " . (int)($data['UPDATE_CHECK_INTERVAL'] ?? 24) . ");\n\n";
        $tpl .= "define('NOTIFY_EMAIL', " . var_export($data['NOTIFY_EMAIL'] ?? '',true) . ");\n";
        $tpl .= "define('NOTIFY_BELL',  " . (!empty($data['NOTIFY_BELL']) && $data['NOTIFY_BELL'] !== 'false' ? 'true' : 'false') . ");\n";
        $configWritten = (@file_put_contents($configFile, $tpl) !== false);
    }

    return ['db' => $dbOk, 'file' => $configWritten];
}

function readAllSettings(): array {
    $keys = ['DB_HOST','DB_NAME','DB_USER','DB_PASS','WISECP_URL','SITE_NAME','SITE_URL',
             'SITE_DESCRIPTION','WHOIS_CACHE_TTL','MAX_QUERIES_PER_IP','GOOGLE_ADSENSE_ID',
             'AD_SLOT_HEADER','AD_SLOT_SIDEBAR','AD_SLOT_FOOTER','ADS_ENABLED',
             'GITHUB_REPO','GITHUB_TOKEN','GITHUB_BRANCH','UPDATE_CHECK_INTERVAL',
             'NOTIFY_EMAIL','NOTIFY_BELL'];
    $out = [];
    foreach ($keys as $k) $out[$k] = getSetting($k, defined($k) ? (string)constant($k) : '');
    return $out;
}

// ── GitHub fonksiyonları ──────────────────────────────────────────────────────
function ghCurl(string $method, string $url, array $headers, ?array $body = null): array {
    $ch = @curl_init($url);
    if (!$ch) return ['code'=>0,'data'=>[],'raw'=>''];
    $h = array_merge(['User-Agent: WhoisAdmin/1.0','Accept: application/vnd.github.v3+json'], $headers);
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => $h,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CUSTOMREQUEST  => $method,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code'=>$code,'data'=>json_decode($raw,true)??[],'raw'=>$raw];
}

function checkGithubUpdate(): array {
    $repo   = getSetting('GITHUB_REPO');
    $token  = getSetting('GITHUB_TOKEN');
    $branch = getSetting('GITHUB_BRANCH','main');
    if (!$repo) return ['has_update'=>false,'msg'=>'GitHub repo tanimlanmamis.'];
    $auth = $token ? ["Authorization: token {$token}"] : [];
    $r    = ghCurl('GET',"https://api.github.com/repos/{$repo}/commits/{$branch}",$auth);
    if ($r['code'] !== 200) return ['has_update'=>false,'msg'=>"GitHub API hatasi: HTTP {$r['code']}"];
    $remoteHash = $r['data']['sha'] ?? '';
    $localHash  = file_exists(__DIR__.'/.last_commit') ? trim(file_get_contents(__DIR__.'/.last_commit')) : '';
    return [
        'has_update'     => $remoteHash && $remoteHash !== $localHash,
        'remote_hash'    => $remoteHash,
        'local_hash'     => $localHash,
        'commit_message' => $r['data']['commit']['message'] ?? '',
        'commit_date'    => $r['data']['commit']['author']['date'] ?? '',
        'msg'            => ($remoteHash && $remoteHash !== $localHash)
            ? 'Yeni surum: '.($r['data']['commit']['message']??'')
            : 'Surum guncel.',
    ];
}

function githubPushFile(string $repoPath, string $content, string $token, string $repo, string $branch, string $commitMsg): array {
    $auth = ["Authorization: token {$token}"];
    // Mevcut dosyanın SHA'sını al
    $existing = ghCurl('GET', "https://api.github.com/repos/{$repo}/contents/{$repoPath}?ref={$branch}", $auth);
    $sha = $existing['data']['sha'] ?? null;

    $payload = ['message'=>$commitMsg,'content'=>base64_encode($content),'branch'=>$branch];
    if ($sha) $payload['sha'] = $sha;

    $r = ghCurl('PUT', "https://api.github.com/repos/{$repo}/contents/{$repoPath}", $auth, $payload);
    return ['ok'=>in_array($r['code'],[200,201]),'code'=>$r['code'],'msg'=>$r['data']['content']['name']??($r['data']['message']??'')];
}

function githubPushAll(): array {
    $repo   = getSetting('GITHUB_REPO');
    $token  = getSetting('GITHUB_TOKEN');
    $branch = getSetting('GITHUB_BRANCH','main');
    if (!$repo || !$token) return ['ok'=>false,'msg'=>'Repo veya token eksik.'];

    $root  = realpath(__DIR__.'/..');
    $files = [
        'index.php','api.php','.htaccess','manifest.json','README.md','.gitignore',
        'admin/index.php','admin/cron.php','admin/.htaccess',
        'includes/db.php','includes/whois.php','includes/whois-servers.php','includes/schema.sql',
    ];
    // config.example.php
    $cfgExample = "<?php\n// Ornek config — gercek degerlerle doldurun\n"
        ."define('DB_HOST','localhost');\ndefine('DB_NAME','DB_ADINIZ');\n"
        ."define('DB_USER','DB_KULLANICI');\ndefine('DB_PASS','DB_SIFRE');\n"
        ."define('DB_CHARSET','utf8mb4');\ndefine('WISECP_URL','https://sisyatek.com');\n"
        ."define('WISECP_CART_URL',WISECP_URL.'/cart/?domain=register');\n"
        ."define('SITE_NAME','SiteAdiniz');\ndefine('SITE_URL','https://siteniz.com');\n"
        ."define('SITE_DESCRIPTION','WHOIS Sorgulama');\n"
        ."define('WHOIS_CACHE_TTL',3600);\ndefine('MAX_QUERIES_PER_IP',100);\n"
        ."date_default_timezone_set('Europe/Istanbul');\n"
        ."define('GOOGLE_ADSENSE_ID','ca-pub-XXXXXXXXXX');\n"
        ."define('AD_SLOT_HEADER','XXXXXXXXXX');\ndefine('AD_SLOT_SIDEBAR','XXXXXXXXXX');\n"
        ."define('AD_SLOT_FOOTER','XXXXXXXXXX');\ndefine('ADS_ENABLED',false);\n"
        ."define('GITHUB_REPO','kullanici/repo');\ndefine('GITHUB_TOKEN','ghp_TOKEN');\n"
        ."define('GITHUB_BRANCH','main');\ndefine('UPDATE_CHECK_INTERVAL',24);\n"
        ."define('NOTIFY_EMAIL','');\ndefine('NOTIFY_BELL',true);\n";

    $ok=[]; $fail=[];
    foreach ($files as $rel) {
        $path = $root.'/'.$rel;
        if (!file_exists($path)) continue;
        $r = githubPushFile($rel, file_get_contents($path), $token, $repo, $branch, "v1.5 guncelleme: {$rel}");
        if ($r['ok']) $ok[] = $rel; else $fail[] = $rel.' (HTTP '.$r['code'].')';
        usleep(200000);
    }
    // config.example
    $r = githubPushFile('includes/config.example.php', $cfgExample, $token, $repo, $branch, "v1.5 ornek config");
    if ($r['ok']) $ok[] = 'includes/config.example.php'; else $fail[] = 'config.example.php (HTTP '.$r['code'].')';

    // .gitignore
    $gi = "includes/config.php\nadmin/passwd.php\nadmin/.pending_bell\nadmin/.last_commit\nadmin/cron.log\ngithub_upload.php\n*.bak\n.DS_Store\n";
    $r  = githubPushFile('.gitignore', $gi, $token, $repo, $branch, "v1.5 gitignore");
    if ($r['ok']) $ok[] = '.gitignore'; else $fail[] = '.gitignore (HTTP '.$r['code'].')';

    // Commit hash kaydet
    $auth = ["Authorization: token {$token}"];
    $cr   = ghCurl('GET',"https://api.github.com/repos/{$repo}/commits/{$branch}",$auth);
    if (!empty($cr['data']['sha'])) @file_put_contents(__DIR__.'/.last_commit', $cr['data']['sha']);

    if ($fail) return ['ok'=>false,'msg'=>count($ok).' yuklendi, '.count($fail).' hata: '.implode(', ',$fail)];
    return ['ok'=>true,'msg'=>count($ok).' dosya basariyla GitHub\'a yuklendi.'];
}

function githubPullUpdate(): array {
    $repo   = getSetting('GITHUB_REPO');
    $token  = getSetting('GITHUB_TOKEN');
    $branch = getSetting('GITHUB_BRANCH','main');
    if (!$repo) return ['ok'=>false,'msg'=>'Repo tanimlanmamis.'];
    $auth    = $token ? ["Authorization: token {$token}"] : [];
    $rawBase = "https://raw.githubusercontent.com/{$repo}/{$branch}";

    // manifest.json indir
    $mr = ghCurl('GET',"{$rawBase}/manifest.json",$auth);
    if ($mr['code']!==200) return ['ok'=>false,'msg'=>"manifest.json indirilemedi (HTTP {$mr['code']})."];
    $manifest = json_decode($mr['raw'],true);
    if (!$manifest) return ['ok'=>false,'msg'=>'manifest.json parse hatasi.'];

    $skip  = $manifest['do_not_overwrite'] ?? ['includes/config.php','admin/passwd.php'];
    $files = array_keys($manifest['files'] ?? []);
    $root  = realpath(__DIR__.'/..');
    $ok=[]; $fail=[];

    foreach ($files as $rel) {
        if (in_array($rel,$skip)) continue;
        $fr = ghCurl('GET',"{$rawBase}/{$rel}",$auth);
        if ($fr['code']!==200) { $fail[]=$rel; continue; }
        $local = $root.'/'.$rel;
        @mkdir(dirname($local),0755,true);
        if (@file_put_contents($local,$fr['raw'])!==false) $ok[]=$rel; else $fail[]=$rel.' (yazma hatasi)';
        usleep(100000);
    }
    // Commit hash kaydet
    $cr = ghCurl('GET',"https://api.github.com/repos/{$repo}/commits/{$branch}",$auth);
    if (!empty($cr['data']['sha'])) @file_put_contents(__DIR__.'/.last_commit',$cr['data']['sha']);

    if ($fail) return ['ok'=>false,'msg'=>count($ok).' guncellendi, '.count($fail).' hata: '.implode(', ',$fail)];
    return ['ok'=>true,'msg'=>count($ok).' dosya basariyla guncellendi.'];
}

// ── Actions ───────────────────────────────────────────────────────────────────
$action = $_REQUEST['action'] ?? '';
$flash  = [];

if ($action === 'logout') { $_SESSION=[]; session_destroy(); header('Location: index.php'); exit; }

if ($action === 'login' && $_SERVER['REQUEST_METHOD']==='POST') {
    $u = trim($_POST['username']??'');
    $p = trim($_POST['password']??'');
    $activePass = defined('ADMIN_PASS_OVERRIDE') ? ADMIN_PASS_OVERRIDE : ADMIN_PASS_PLAIN;
    if ($u===ADMIN_USER && $p===$activePass) {
        $_SESSION[ADMIN_SESSION_KEY]=true;
        $_SESSION['admin_login_time']=time();
        header('Location: index.php'); exit;
    }
    $loginError='Kullanici adi veya sifre hatali.';
}

$isLoggedIn = !empty($_SESSION[ADMIN_SESSION_KEY]);
if ($isLoggedIn) {

    if ($action==='save_settings' && $_SERVER['REQUEST_METHOD']==='POST') {
        $data = readAllSettings();
        $editable = ['SITE_NAME','SITE_URL','SITE_DESCRIPTION','WISECP_URL',
            'GOOGLE_ADSENSE_ID','AD_SLOT_HEADER','AD_SLOT_SIDEBAR','AD_SLOT_FOOTER',
            'WHOIS_CACHE_TTL','MAX_QUERIES_PER_IP','GITHUB_REPO','GITHUB_TOKEN',
            'GITHUB_BRANCH','UPDATE_CHECK_INTERVAL','NOTIFY_EMAIL','DB_HOST','DB_NAME','DB_USER'];
        foreach ($editable as $f) { if (isset($_POST[$f])) $data[$f]=trim($_POST[$f]); }
        $data['ADS_ENABLED'] = !empty($_POST['ADS_ENABLED']) ? 'true' : 'false';
        $data['NOTIFY_BELL'] = !empty($_POST['NOTIFY_BELL']) ? 'true' : 'false';
        if (!empty(trim($_POST['DB_PASS']??''))) $data['DB_PASS']=trim($_POST['DB_PASS']);
        $res = saveSettings($data);
        if ($res['db'] || $res['file']) {
            $how = $res['file'] ? 'config.php dosyasina yazildi.' : 'Veritabanina kaydedildi (config.php yazma izni yok — normal).';
            $_SESSION['flash']=['type'=>'ok','msg'=>'Ayarlar kaydedildi. '.$how];
        } else {
            $_SESSION['flash']=['type'=>'err','msg'=>'HATA: Ne DB ne de dosyaya yazilabildi. DB baglantisini ve includes/ klasoru iznini kontrol edin.'];
        }
        header('Location: index.php?tab=settings'); exit;
    }

    if ($action==='change_password' && $_SERVER['REQUEST_METHOD']==='POST') {
        $cur=$_POST['current_password']??''; $new1=$_POST['new_password']??''; $new2=$_POST['new_password2']??'';
        $activePass=defined('ADMIN_PASS_OVERRIDE')?ADMIN_PASS_OVERRIDE:ADMIN_PASS_PLAIN;
        if (trim($cur)!==$activePass) { $_SESSION['flash']=['type'=>'err','msg'=>'Mevcut sifre yanlis.']; }
        elseif (strlen(trim($new1))<6) { $_SESSION['flash']=['type'=>'err','msg'=>'Min. 6 karakter.']; }
        elseif (trim($new1)!==trim($new2)) { $_SESSION['flash']=['type'=>'err','msg'=>'Sifreler eslesmedi.']; }
        else {
            $c="<?php define('ADMIN_PASS_OVERRIDE','".addslashes(trim($new1))."'); ?>";
            if (@file_put_contents(__DIR__.'/passwd.php',$c)!==false) {
                $_SESSION=[]; session_destroy(); session_start();
                $_SESSION['flash']=['type'=>'ok','msg'=>'Sifre guncellendi. Tekrar giris yapin.'];
                header('Location: index.php'); exit;
            } else { $_SESSION['flash']=['type'=>'err','msg'=>'Dosya yazma hatasi. admin/ chmod 755 olmali.']; }
        }
        header('Location: index.php?tab=settings'); exit;
    }

    if ($action==='clear_cache') {
        try { $db=getDB(); $db->query("DELETE FROM whois_cache WHERE expires_at<NOW()"); $db->query("DELETE FROM rate_limits WHERE query_date<CURDATE()"); $_SESSION['flash']=['type'=>'ok','msg'=>'Cache temizlendi.']; }
        catch(Exception $e) { $_SESSION['flash']=['type'=>'err','msg'=>$e->getMessage()]; }
        header('Location: index.php?tab=settings'); exit;
    }

    if ($action==='github_push' && $_SERVER['REQUEST_METHOD']==='POST') {
        $r=$_SESSION['flash']=($res=githubPushAll())['ok']
            ? ['type'=>'ok', 'msg'=>$res['msg']]
            : ['type'=>'err','msg'=>$res['msg']];
        header('Location: index.php?tab=github'); exit;
    }

    if ($action==='github_update' && $_SERVER['REQUEST_METHOD']==='POST') {
        $res=githubPullUpdate();
        $_SESSION['flash']=$res['ok']?['type'=>'ok','msg'=>$res['msg']]:['type'=>'err','msg'=>$res['msg']];
        header('Location: index.php?tab=github'); exit;
    }

    if ($action==='check_update') { header('Content-Type: application/json'); echo json_encode(checkGithubUpdate()); exit; }

    if ($action==='dismiss_bell') {
        unset($_SESSION['pending_bell']); @unlink(__DIR__.'/.pending_bell');
        header('Content-Type: application/json'); echo '{"ok":true}'; exit;
    }

    // Veri yükle
    $cfg=$s=[];
    $cfg=readAllSettings();
    $dbError=null;
    $recent_queries=$tld_stats=$daily_chart=[];
    try {
        $db=getDB();
        if (Database::isConnected()) {
            $q=$db->query("SELECT COUNT(*) FROM whois_queries");          $s['total']=$q?$q->fetchColumn():0;
            $q=$db->query("SELECT COUNT(*) FROM whois_queries WHERE DATE(created_at)=CURDATE()"); $s['today']=$q?$q->fetchColumn():0;
            $q=$db->query("SELECT COUNT(*) FROM whois_queries WHERE is_available=1"); $s['avail']=$q?$q->fetchColumn():0;
            $q=$db->query("SELECT COUNT(DISTINCT ip_address) FROM whois_queries"); $s['ips']=$q?$q->fetchColumn():0;
            $q=$db->query("SELECT COUNT(*) FROM whois_cache"); $s['cache']=$q?$q->fetchColumn():0;
            $q=$db->query("SELECT domain,tld,ip_address,is_available,created_at FROM whois_queries ORDER BY id DESC LIMIT 30"); $recent_queries=$q?$q->fetchAll():[];
            $q=$db->query("SELECT tld,search_count FROM domain_stats ORDER BY search_count DESC LIMIT 10"); $tld_stats=$q?$q->fetchAll():[];
            $q=$db->query("SELECT DATE(created_at) as day,COUNT(*) as cnt FROM whois_queries WHERE created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY) GROUP BY DATE(created_at) ORDER BY day ASC"); $daily_chart=$q?$q->fetchAll():[];
        }
    } catch(Exception $e) { $dbError=$e->getMessage(); }

    if (!empty($_SESSION['flash'])) { $flash=$_SESSION['flash']; unset($_SESSION['flash']); }
    $activeTab=$_GET['tab']??'dashboard';

    $pendingBell=$_SESSION['pending_bell']??null;
    if (!$pendingBell && file_exists(__DIR__.'/.pending_bell')) {
        $bd=@json_decode(@file_get_contents(__DIR__.'/.pending_bell'),true);
        if ($bd) $pendingBell=$bd;
    }
    $lastCommit=file_exists(__DIR__.'/.last_commit')?trim(file_get_contents(__DIR__.'/.last_commit')):'';
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Admin Panel — <?= htmlspecialchars(getSetting('SITE_NAME','WHOIS')) ?></title>
<style>
:root{--bg:#0b0b12;--sf:#13131f;--sf2:#1a1a28;--bd:#252538;--bd2:#30304a;--ac:#6c63ff;--ac2:#9d97ff;--ac3:#4a43cc;--gn:#00d68f;--rd:#ff4d6a;--yw:#ffd166;--tx:#e8e8f0;--t2:#9898b8;--t3:#5a5a7a;--mono:'Courier New',monospace;--r:8px;--r2:12px}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--tx);font-family:'Segoe UI',system-ui,sans-serif;min-height:100vh}
a{color:var(--ac2);text-decoration:none}
input,select,textarea{font-family:inherit}
::-webkit-scrollbar{width:5px;height:5px}::-webkit-scrollbar-thumb{background:var(--bd2);border-radius:3px}

/* LOGIN */
.lw{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;background:radial-gradient(ellipse 70% 50% at 50% 0%,rgba(108,99,255,.15),transparent 70%)}
.lc{background:var(--sf);border:1.5px solid var(--bd2);border-radius:18px;padding:44px 40px;width:100%;max-width:390px;box-shadow:0 24px 64px rgba(0,0,0,.6)}
.li{width:52px;height:52px;background:var(--ac);border-radius:14px;display:inline-flex;align-items:center;justify-content:center;margin-bottom:12px;box-shadow:0 8px 24px rgba(108,99,255,.4)}
.li svg{width:26px;height:26px;stroke:#fff;fill:none;stroke-width:2.5}
.fl{font-size:.72rem;color:var(--t2);font-family:var(--mono);letter-spacing:.08em;text-transform:uppercase;display:block;margin-bottom:7px}
.fi{width:100%;background:var(--bg);border:1.5px solid var(--bd2);border-radius:var(--r);padding:12px 14px;color:var(--tx);font-size:.95rem;outline:none;transition:border-color .2s}
.fi:focus{border-color:var(--ac)}
.fs{width:100%;background:var(--bg);border:1.5px solid var(--bd2);border-radius:var(--r);padding:11px 14px;color:var(--tx);font-size:.9rem;outline:none;appearance:none;cursor:pointer}
.fs:focus{border-color:var(--ac)}
.bf{width:100%;background:var(--ac);color:#fff;border:none;cursor:pointer;font-weight:700;font-size:.95rem;padding:14px;border-radius:var(--r);transition:background .2s;margin-top:6px}
.bf:hover{background:var(--ac3)}

/* LAYOUT */
.aw{display:grid;grid-template-columns:240px 1fr;min-height:100vh}
.sb{background:var(--sf);border-right:1px solid var(--bd);display:flex;flex-direction:column;position:sticky;top:0;height:100vh;overflow-y:auto}
.sl{padding:22px 20px 18px;border-bottom:1px solid var(--bd)}
.si{width:34px;height:34px;background:var(--ac);border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.si svg{width:18px;height:18px;stroke:#fff;fill:none;stroke-width:2.5}
.sn{padding:12px 0;flex:1}
.ngl{font-family:var(--mono);font-size:.6rem;color:var(--t3);letter-spacing:.12em;text-transform:uppercase;padding:10px 20px 4px}
.ni{display:flex;align-items:center;gap:10px;padding:10px 20px;font-size:.85rem;color:var(--t2);cursor:pointer;border:none;background:none;width:100%;text-align:left;transition:color .15s,background .15s;border-left:2px solid transparent}
.ni svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:1.8;flex-shrink:0}
.ni:hover{color:var(--tx);background:rgba(108,99,255,.07)}
.ni.active{color:var(--ac2);background:rgba(108,99,255,.1);border-left-color:var(--ac)}
.nb{margin-left:auto;background:var(--rd);color:#fff;font-size:.6rem;font-weight:700;padding:2px 6px;border-radius:10px;animation:bp 2s ease-in-out infinite}
@keyframes bp{0%,100%{opacity:1}50%{opacity:.6}}
.sbt{padding:16px 20px;border-top:1px solid var(--bd)}

/* MAIN */
.am{min-width:0;overflow-x:hidden}
.tb{background:var(--sf);border-bottom:1px solid var(--bd);padding:16px 28px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50}
.tr{display:flex;align-items:center;gap:10px}

/* BELL */
.bb{width:36px;height:36px;border-radius:var(--r);background:var(--sf2);border:1px solid var(--bd2);display:flex;align-items:center;justify-content:center;cursor:pointer;position:relative;transition:background .15s}
.bb svg{width:16px;height:16px;stroke:var(--t2);fill:none;stroke-width:2}
.bd2{position:absolute;top:6px;right:6px;width:8px;height:8px;border-radius:50%;background:var(--rd);border:2px solid var(--sf);animation:bp 1.5s ease-in-out infinite}
.bp{position:absolute;top:44px;right:0;width:300px;background:var(--sf);border:1px solid var(--bd2);border-radius:var(--r2);box-shadow:0 16px 48px rgba(0,0,0,.5);padding:16px;z-index:200;display:none}
.bp.open{display:block}

/* PAGE */
.pc{padding:28px;display:none}.pc.active{display:block}
.ph{margin-bottom:24px}
.pt{font-size:1.3rem;font-weight:700;margin-bottom:4px}
.ps{font-size:.78rem;color:var(--t3);font-family:var(--mono)}

/* FLASH */
.fls{display:flex;align-items:center;gap:10px;padding:13px 18px;border-radius:var(--r);margin-bottom:20px;font-size:.875rem}
.fls.ok{background:rgba(0,214,143,.1);color:var(--gn);border:1px solid rgba(0,214,143,.3)}
.fls.err{background:rgba(255,77,106,.1);color:var(--rd);border:1px solid rgba(255,77,106,.3)}

/* STATS */
.sg{display:grid;grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:14px;margin-bottom:22px}
.sc{background:var(--sf);border:1px solid var(--bd);border-radius:var(--r2);padding:18px}
.sv{font-family:var(--mono);font-size:1.7rem;font-weight:700;color:var(--ac2)}
.slb{font-size:.72rem;color:var(--t3);margin-top:3px}

/* CHART */
.cc{background:var(--sf);border:1px solid var(--bd);border-radius:var(--r2);padding:20px;margin-bottom:18px}
.bc{display:flex;align-items:flex-end;gap:8px;height:100px}
.bcl{flex:1;display:flex;flex-direction:column;align-items:center;gap:4px}
.bar{background:var(--ac);border-radius:4px 4px 0 0;width:100%;min-height:3px}

/* TABLE */
.tc{background:var(--sf);border:1px solid var(--bd);border-radius:var(--r2);overflow:hidden;margin-bottom:18px}
.tch{padding:13px 18px;border-bottom:1px solid var(--bd);background:var(--sf2);display:flex;align-items:center;justify-content:space-between}
.tct{font-size:.78rem;font-weight:600;color:var(--t2);letter-spacing:.04em;text-transform:uppercase}
table{width:100%;border-collapse:collapse}
th{padding:9px 15px;font-size:.67rem;color:var(--t3);font-family:var(--mono);letter-spacing:.06em;text-transform:uppercase;text-align:left;background:var(--sf2);font-weight:500}
td{padding:9px 15px;font-size:.82rem;border-top:1px solid var(--bd);font-family:var(--mono)}
tr:hover td{background:rgba(108,99,255,.04)}
.bg{font-size:.65rem;font-weight:700;padding:3px 7px;border-radius:4px}
.bgg{background:rgba(0,214,143,.15);color:var(--gn)}
.bgr{background:rgba(255,77,106,.15);color:var(--rd)}
.bgy{background:rgba(255,209,102,.12);color:var(--yw)}

/* SETTINGS */
.stg{display:grid;gap:16px}
.stc{background:var(--sf);border:1px solid var(--bd);border-radius:var(--r2);overflow:hidden}
.stch{padding:13px 20px;border-bottom:1px solid var(--bd);background:var(--sf2);display:flex;align-items:center;gap:8px}
.stct{font-size:.82rem;font-weight:600;color:var(--tx)}
.stcb{padding:20px}
.fg{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.fg1{grid-template-columns:1fr}
.fg3{grid-template-columns:1fr 1fr 1fr}
.fgg{display:flex;flex-direction:column;gap:5px}
.fgl{font-size:.7rem;color:var(--t2);font-family:var(--mono);letter-spacing:.06em;text-transform:uppercase}
.fgh{font-size:.68rem;color:var(--t3);margin-top:1px}
/* toggle */
.tgr{display:flex;align-items:center;justify-content:space-between;padding:11px 0;border-bottom:1px solid var(--bd)}
.tgr:last-child{border-bottom:none;padding-bottom:0}
.tgi{font-size:.85rem;color:var(--tx)}.tgs{font-size:.72rem;color:var(--t3);margin-top:2px}
.tsw{position:relative;width:42px;height:24px;flex-shrink:0}
.tsw input{opacity:0;width:0;height:0}
.tsl{position:absolute;cursor:pointer;inset:0;background:var(--bd2);border-radius:12px;transition:background .2s}
.tsl::before{content:'';position:absolute;width:16px;height:16px;left:4px;top:4px;background:#fff;border-radius:50%;transition:transform .2s}
input:checked+.tsl{background:var(--ac)}
input:checked+.tsl::before{transform:translateX(18px)}

/* BUTTONS */
.btn{display:inline-flex;align-items:center;gap:6px;border:none;cursor:pointer;font-family:inherit;font-weight:600;font-size:.82rem;padding:9px 18px;border-radius:var(--r);transition:background .15s,transform .1s}
.btn:active{transform:scale(.97)}
.btn svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2.5}
.btn-p{background:var(--ac);color:#fff}.btn-p:hover{background:var(--ac3)}
.btn-s{background:var(--sf2);color:var(--t2);border:1px solid var(--bd2)}.btn-s:hover{color:var(--tx)}
.btn-d{background:rgba(255,77,106,.12);color:var(--rd);border:1px solid rgba(255,77,106,.3)}.btn-d:hover{background:var(--rd);color:#fff}
.btn-g{background:#238636;color:#fff}.btn-g:hover{background:#2ea043}

/* GITHUB */
.ghs{background:var(--sf);border:1px solid var(--bd);border-radius:var(--r2);padding:20px;margin-bottom:16px}
.ghd{width:10px;height:10px;border-radius:50%;flex-shrink:0}
.ghd.gn{background:var(--gn);box-shadow:0 0 8px var(--gn)}
.ghd.yw{background:var(--yw);box-shadow:0 0 8px var(--yw)}
.ghd.rd{background:var(--rd);box-shadow:0 0 8px var(--rd)}
.ghd.gr{background:var(--t3)}
.gcb{background:var(--bg);border:1px solid var(--bd);border-radius:var(--r);padding:13px 16px;margin-top:13px;font-family:var(--mono);font-size:.78rem}
.cron{background:var(--sf);border:1px solid var(--bd);border-radius:var(--r2);padding:20px;margin-top:16px}
.code{background:var(--bg);border:1px solid var(--bd);border-radius:var(--r);padding:12px 15px;font-family:var(--mono);font-size:.75rem;color:var(--t2);overflow-x:auto;white-space:pre;margin-top:6px}

@media(max-width:768px){.aw{grid-template-columns:1fr}.sb{display:none}.fg{grid-template-columns:1fr}.fg3{grid-template-columns:1fr}}
</style>
</head>
<body>

<?php if (!$isLoggedIn): ?>
<div class="lw">
  <div class="lc" style="text-align:center">
    <div class="li"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg></div>
    <div style="font-size:1.4rem;font-weight:700;margin-bottom:4px">Admin Panel</div>
    <div style="font-size:.82rem;color:var(--t3);margin-bottom:28px"><?= htmlspecialchars(getSetting('SITE_NAME','WHOIS')) ?></div>
    <?php if(!empty($loginError)):?><div style="background:rgba(255,77,106,.12);color:var(--rd);border:1px solid rgba(255,77,106,.3);border-radius:var(--r);padding:10px 14px;font-size:.82rem;margin-bottom:16px">&#9888; <?= htmlspecialchars($loginError) ?></div><?php endif;?>
    <?php if(!empty($_SESSION['flash'])):$f=$_SESSION['flash'];unset($_SESSION['flash']);?><div class="fls <?=$f['type']?>"><?=htmlspecialchars($f['msg'])?></div><?php endif;?>
    <form method="POST" action="?action=login" style="text-align:left">
      <div style="margin-bottom:14px"><label class="fl">Kullanici Adi</label><input type="text" name="username" class="fi" placeholder="admin" required autocomplete="username"></div>
      <div style="margin-bottom:14px"><label class="fl">Sifre</label><input type="password" name="password" class="fi" placeholder="••••••••" required autocomplete="current-password"></div>
      <button type="submit" class="bf">Giris Yap &rarr;</button>
    </form>
    <div style="margin-top:14px;font-size:.7rem;color:var(--t3);font-family:var(--mono)">admin / admin123 &mdash; <span style="color:var(--rd)">girisin ardindan degistirin</span></div>
  </div>
</div>

<?php else: ?>
<div class="aw">
  <!-- SIDEBAR -->
  <aside class="sb">
    <div class="sl">
      <div style="display:flex;align-items:center;gap:10px">
        <div class="si"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg></div>
        <div><div style="font-weight:700;font-size:.95rem"><?= htmlspecialchars(getSetting('SITE_NAME','WHOIS')) ?></div><div style="font-size:.62rem;color:var(--t3);font-family:var(--mono)">Admin v1.5</div></div>
      </div>
    </div>
    <nav class="sn">
      <div class="ngl">Genel</div>
      <button class="ni <?=$activeTab==='dashboard'?'active':''?>" onclick="sw('dashboard')"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>Dashboard</button>
      <button class="ni <?=$activeTab==='queries'?'active':''?>" onclick="sw('queries')"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>Sorgular</button>
      <button class="ni <?=$activeTab==='tld_stats'?'active':''?>" onclick="sw('tld_stats')"><svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>TLD Istatistik</button>
      <div class="ngl">Yonetim</div>
      <button class="ni <?=$activeTab==='settings'?'active':''?>" onclick="sw('settings')"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>Site Ayarlari</button>
      <button class="ni <?=$activeTab==='github'?'active':''?>" onclick="sw('github')"><svg viewBox="0 0 24 24"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 00-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0020 4.77 5.07 5.07 0 0019.91 1S18.73.65 16 2.48a13.38 13.38 0 00-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 005 4.77a5.44 5.44 0 00-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 009 18.13V22"/></svg>GitHub
        <?php if($pendingBell):?><span class="nb">!</span><?php endif;?>
      </button>
    </nav>
    <div class="sbt">
      <div style="font-size:.75rem;color:var(--t3);margin-bottom:8px;font-family:var(--mono)"><?= htmlspecialchars(ADMIN_USER) ?> &bull; <?= date('H:i') ?></div>
      <button class="btn btn-s" style="width:100%" onclick="location.href='?action=logout'"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Cikis</button>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="am">
    <!-- TOPBAR -->
    <div class="tb">
      <div>
        <div style="font-size:1rem;font-weight:600" id="tbTitle">Dashboard</div>
        <div style="font-size:.74rem;color:var(--t3);font-family:var(--mono)"><?= date('d.m.Y') ?> &bull; <?= htmlspecialchars(getSetting('SITE_URL','')) ?></div>
      </div>
      <div class="tr">
        <?php if($pendingBell||getSetting('NOTIFY_BELL')): ?>
        <div style="position:relative">
          <button class="bb" onclick="toggleBell()" id="bellBtn">
            <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
            <?php if($pendingBell):?><div class="bd2"></div><?php endif;?>
          </button>
          <div class="bp" id="bellPopup">
            <div style="font-size:.75rem;font-weight:600;color:var(--ac2);margin-bottom:8px;font-family:var(--mono)">&#128276; GUNCELLEME</div>
            <?php if($pendingBell):?>
            <div style="font-size:.82rem;color:var(--tx);margin-bottom:10px;line-height:1.5"><?=htmlspecialchars($pendingBell['msg']??'Yeni surum mevcut.')?></div>
            <div style="display:flex;gap:8px">
              <button class="btn btn-g" onclick="sw('github');closeBell()">Guncelle</button>
              <button class="btn btn-s" onclick="dismissBell()">Kapat</button>
            </div>
            <?php else:?>
            <div style="font-size:.82rem;color:var(--t2)">Aktif bildirim yok.</div>
            <?php endif;?>
          </div>
        </div>
        <?php endif;?>
        <a href="../" target="_blank" class="btn btn-s"><svg viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>Siteyi Ac</a>
      </div>
    </div>

    <!-- FLASH -->
    <?php if($flash):?>
    <div style="padding:16px 28px 0"><div class="fls <?=$flash['type']?>"><?=$flash['type']==='ok'?'&#10003;':'&#9888;'?> <?=htmlspecialchars($flash['msg'])?></div></div>
    <?php endif;?>

    <!-- DASHBOARD -->
    <div class="pc <?=$activeTab==='dashboard'?'active':''?>" id="tab_dashboard">
      <div class="ph"><div class="pt">Dashboard</div><div class="ps">// Genel bakim</div></div>
      <?php if($dbError):?><div class="fls err">&#9888; DB hatasi: <?=htmlspecialchars($dbError)?></div><?php endif;?>
      <div class="sg">
        <div class="sc"><div style="font-size:1.3rem;margin-bottom:7px">&#128269;</div><div class="sv"><?=number_format($s['total']??0)?></div><div class="slb">Toplam Sorgu</div></div>
        <div class="sc"><div style="font-size:1.3rem;margin-bottom:7px">&#128197;</div><div class="sv"><?=number_format($s['today']??0)?></div><div class="slb">Bugunun Sorgulari</div></div>
        <div class="sc"><div style="font-size:1.3rem;margin-bottom:7px">&#9989;</div><div class="sv"><?=number_format($s['avail']??0)?></div><div class="slb">Musait Bulunan</div></div>
        <div class="sc"><div style="font-size:1.3rem;margin-bottom:7px">&#127760;</div><div class="sv"><?=number_format($s['ips']??0)?></div><div class="slb">Tekil Kullanici</div></div>
        <div class="sc"><div style="font-size:1.3rem;margin-bottom:7px">&#128190;</div><div class="sv"><?=number_format($s['cache']??0)?></div><div class="slb">Cache Girisi</div></div>
      </div>
      <div class="cc">
        <div style="font-size:.78rem;font-weight:600;color:var(--t2);text-transform:uppercase;letter-spacing:.04em;margin-bottom:14px">Son 7 Gun</div>
        <?php $mx=max(array_column($daily_chart?:[['cnt'=>1]],'cnt')); ?>
        <div class="bc">
          <?php foreach($daily_chart as $r):?>
          <div class="bcl">
            <div style="font-size:.62rem;color:var(--ac2);font-family:var(--mono)"><?=$r['cnt']?></div>
            <div class="bar" style="height:<?=round(($r['cnt']/$mx)*80)?>px"></div>
            <div style="font-size:.58rem;color:var(--t3);font-family:var(--mono)"><?=date('d/m',strtotime($r['day']))?></div>
          </div>
          <?php endforeach;?>
          <?php if(empty($daily_chart)):?><div style="color:var(--t3);font-size:.8rem">Veri yok</div><?php endif;?>
        </div>
      </div>
      <div class="tc">
        <div class="tch"><span class="tct">Son 20 Sorgu</span></div>
        <table><thead><tr><th>Domain</th><th>TLD</th><th>Durum</th><th>IP</th><th>Tarih</th></tr></thead><tbody>
        <?php foreach(array_slice($recent_queries,0,20) as $q):?>
        <tr><td><?=htmlspecialchars($q['domain'])?></td><td style="color:var(--ac2)"><?=htmlspecialchars($q['tld'])?></td>
        <td><?php if($q['is_available']==='1'):?><span class="bg bgg">MUSAIT</span><?php elseif($q['is_available']==='0'):?><span class="bg bgr">KAYITLI</span><?php else:?><span class="bg bgy">?</span><?php endif;?></td>
        <td style="color:var(--t3)"><?=htmlspecialchars($q['ip_address'])?></td>
        <td style="color:var(--t3)"><?=date('d.m H:i',strtotime($q['created_at']))?></td></tr>
        <?php endforeach;?>
        <?php if(empty($recent_queries)):?><tr><td colspan="5" style="text-align:center;color:var(--t3);padding:18px">Henuz sorgu yok</td></tr><?php endif;?>
        </tbody></table>
      </div>
    </div>

    <!-- SORGULAR -->
    <div class="pc <?=$activeTab==='queries'?'active':''?>" id="tab_queries">
      <div class="ph"><div class="pt">Sorgu Gecmisi</div><div class="ps">// Tum kullanici sorgulari</div></div>
      <div class="tc"><div class="tch"><span class="tct">Son 30 Sorgu</span></div>
        <table><thead><tr><th>Domain</th><th>TLD</th><th>Durum</th><th>IP</th><th>Tarih/Saat</th></tr></thead><tbody>
        <?php foreach($recent_queries as $q):?>
        <tr><td><?=htmlspecialchars($q['domain'])?></td><td style="color:var(--ac2)"><?=htmlspecialchars($q['tld'])?></td>
        <td><?php if($q['is_available']==='1'):?><span class="bg bgg">MUSAIT</span><?php elseif($q['is_available']==='0'):?><span class="bg bgr">KAYITLI</span><?php else:?><span class="bg bgy">?</span><?php endif;?></td>
        <td style="color:var(--t3)"><?=htmlspecialchars($q['ip_address'])?></td>
        <td style="color:var(--t3)"><?=htmlspecialchars($q['created_at'])?></td></tr>
        <?php endforeach;?>
        </tbody></table>
      </div>
    </div>

    <!-- TLD İSTATİSTİK -->
    <div class="pc <?=$activeTab==='tld_stats'?'active':''?>" id="tab_tld_stats">
      <div class="ph"><div class="pt">TLD Istatistikleri</div><div class="ps">// En cok sorgulanan uzantilar</div></div>
      <div class="tc"><table><thead><tr><th>#</th><th>Uzanti</th><th>Sorgu</th><th>Grafik</th></tr></thead><tbody>
      <?php foreach($tld_stats as $i=>$row):?>
      <tr><td style="color:var(--t3)"><?=$i+1?></td><td style="color:var(--ac2)"><?=htmlspecialchars($row['tld'])?></td>
      <td><?=number_format($row['search_count'])?></td>
      <td><div style="height:6px;width:<?=min(180,($row['search_count']/max(1,$tld_stats[0]['search_count']))*160)?>px;background:var(--ac);border-radius:3px;opacity:.8"></div></td></tr>
      <?php endforeach;?>
      <?php if(empty($tld_stats)):?><tr><td colspan="4" style="text-align:center;color:var(--t3);padding:18px">Veri yok</td></tr><?php endif;?>
      </tbody></table></div>
    </div>

    <!-- AYARLAR -->
    <div class="pc <?=$activeTab==='settings'?'active':''?>" id="tab_settings">
      <div class="ph"><div class="pt">Site Ayarlari</div><div class="ps">// Degisiklikler DB + config.php'ye kaydedilir</div></div>
      <form method="POST" action="?action=save_settings">
      <div class="stg">

        <!-- Site -->
        <div class="stc"><div class="stch"><span>&#127760;</span><span class="stct">Site Bilgileri</span></div><div class="stcb">
          <div class="fg">
            <div class="fgg"><label class="fgl">Site Adi</label><input type="text" name="SITE_NAME" class="fi" value="<?=htmlspecialchars($cfg['SITE_NAME']??'')?>"></div>
            <div class="fgg"><label class="fgl">Site URL</label><input type="url" name="SITE_URL" class="fi" value="<?=htmlspecialchars($cfg['SITE_URL']??'')?>"></div>
            <div class="fgg" style="grid-column:1/-1"><label class="fgl">Aciklama</label><input type="text" name="SITE_DESCRIPTION" class="fi" value="<?=htmlspecialchars($cfg['SITE_DESCRIPTION']??'')?>"></div>
          </div>
        </div></div>

        <!-- WiseCP -->
        <div class="stc"><div class="stch"><span>&#128279;</span><span class="stct">WiseCP (sisyatek.com)</span></div><div class="stcb">
          <div class="fg fg1">
            <div class="fgg"><label class="fgl">WiseCP URL</label><input type="url" name="WISECP_URL" class="fi" value="<?=htmlspecialchars($cfg['WISECP_URL']??'')?>"><div class="fgh">Sepet URL otomatik: {WISECP_URL}/cart/?domain=register</div></div>
          </div>
        </div></div>

        <!-- DB -->
        <div class="stc"><div class="stch"><span>&#128190;</span><span class="stct">Veritabani</span></div><div class="stcb">
          <div class="fg">
            <div class="fgg"><label class="fgl">DB Host</label><input type="text" name="DB_HOST" class="fi" value="<?=htmlspecialchars($cfg['DB_HOST']??'localhost')?>"></div>
            <div class="fgg"><label class="fgl">DB Adi</label><input type="text" name="DB_NAME" class="fi" value="<?=htmlspecialchars($cfg['DB_NAME']??'')?>"></div>
            <div class="fgg"><label class="fgl">DB Kullanici</label><input type="text" name="DB_USER" class="fi" value="<?=htmlspecialchars($cfg['DB_USER']??'')?>" autocomplete="off"></div>
            <div class="fgg"><label class="fgl">DB Sifre</label><input type="password" name="DB_PASS" class="fi" placeholder="Degistirmek icin doldurun" autocomplete="new-password"><div class="fgh">Bos birakırsanız mevcut korunur</div></div>
          </div>
        </div></div>

        <!-- Ads -->
        <div class="stc"><div class="stch"><span>&#128226;</span><span class="stct">Google AdSense</span></div><div class="stcb">
          <div class="tgr"><div><div class="tgi">Reklamlari Etkinlestir</div><div class="tgs">ADS_ENABLED</div></div>
          <label class="tsw"><input type="checkbox" name="ADS_ENABLED" value="1" <?=($cfg['ADS_ENABLED']??'false')==='true'||!empty($cfg['ADS_ENABLED'])&&$cfg['ADS_ENABLED']!=='false'?'checked':''?>><span class="tsl"></span></label></div>
          <div class="fg" style="margin-top:14px">
            <div class="fgg" style="grid-column:1/-1"><label class="fgl">Yayinci ID (ca-pub-...)</label><input type="text" name="GOOGLE_ADSENSE_ID" class="fi" value="<?=htmlspecialchars($cfg['GOOGLE_ADSENSE_ID']??'')?>" placeholder="ca-pub-XXXXXXXXXXXX"></div>
            <div class="fgg"><label class="fgl">Header Slot (728x90)</label><input type="text" name="AD_SLOT_HEADER" class="fi" value="<?=htmlspecialchars($cfg['AD_SLOT_HEADER']??'')?>"></div>
            <div class="fgg"><label class="fgl">Sidebar Slot (250x250)</label><input type="text" name="AD_SLOT_SIDEBAR" class="fi" value="<?=htmlspecialchars($cfg['AD_SLOT_SIDEBAR']??'')?>"></div>
            <div class="fgg"><label class="fgl">Footer Slot (responsive)</label><input type="text" name="AD_SLOT_FOOTER" class="fi" value="<?=htmlspecialchars($cfg['AD_SLOT_FOOTER']??'')?>"></div>
          </div>
        </div></div>

        <!-- WHOIS -->
        <div class="stc"><div class="stch"><span>&#9881;</span><span class="stct">WHOIS Ayarlari</span></div><div class="stcb">
          <div class="fg">
            <div class="fgg"><label class="fgl">Cache Suresi (sn)</label><input type="number" name="WHOIS_CACHE_TTL" class="fi" value="<?=(int)($cfg['WHOIS_CACHE_TTL']??3600)?>" min="60" max="86400"><div class="fgh">3600=1 saat, 86400=1 gun</div></div>
            <div class="fgg"><label class="fgl">IP Gunluk Limit</label><input type="number" name="MAX_QUERIES_PER_IP" class="fi" value="<?=(int)($cfg['MAX_QUERIES_PER_IP']??100)?>" min="1"></div>
          </div>
        </div></div>

        <!-- GitHub -->
        <div class="stc"><div class="stch"><span>&#128279;</span><span class="stct">GitHub Guncelleme Ayarlari</span></div><div class="stcb">
          <div class="fg">
            <div class="fgg"><label class="fgl">Repo (kullanici/repo)</label><input type="text" name="GITHUB_REPO" class="fi" value="<?=htmlspecialchars($cfg['GITHUB_REPO']??'')?>" placeholder="codegatr/alanadiyazmani"></div>
            <div class="fgg"><label class="fgl">Branch</label><input type="text" name="GITHUB_BRANCH" class="fi" value="<?=htmlspecialchars($cfg['GITHUB_BRANCH']??'main')?>"></div>
            <div class="fgg" style="grid-column:1/-1"><label class="fgl">Personal Access Token</label><input type="password" name="GITHUB_TOKEN" class="fi" value="<?=htmlspecialchars($cfg['GITHUB_TOKEN']??'')?>" autocomplete="off"></div>
            <div class="fgg"><label class="fgl">Kontrol Araligi (saat)</label><input type="number" name="UPDATE_CHECK_INTERVAL" class="fi" value="<?=(int)($cfg['UPDATE_CHECK_INTERVAL']??24)?>" min="1" max="168"></div>
          </div>
        </div></div>

        <!-- Bildirimler -->
        <div class="stc"><div class="stch"><span>&#128276;</span><span class="stct">Bildirimler</span></div><div class="stcb">
          <div class="tgr"><div><div class="tgi">Admin Panel Can Bildirimi</div><div class="tgs">Yeni surum zil ikoni</div></div>
          <label class="tsw"><input type="checkbox" name="NOTIFY_BELL" value="1" <?=($cfg['NOTIFY_BELL']??'false')==='true'||!empty($cfg['NOTIFY_BELL'])&&$cfg['NOTIFY_BELL']!=='false'?'checked':''?>><span class="tsl"></span></label></div>
          <div class="fgg" style="margin-top:14px"><label class="fgl">E-posta (istegle baglidir)</label><input type="email" name="NOTIFY_EMAIL" class="fi" value="<?=htmlspecialchars($cfg['NOTIFY_EMAIL']??'')?>" placeholder="admin@ornek.com"></div>
        </div></div>

        <!-- Sifre -->
        <div class="stc"><div class="stch"><span>&#128274;</span><span class="stct">Admin Sifresi Guncelle</span></div><div class="stcb">
          <div class="fg" style="max-width:480px">
            <div class="fgg"><label class="fgl">Mevcut Sifre</label><input type="password" id="cp" class="fi" autocomplete="current-password"></div>
            <div class="fgg"><label class="fgl">Yeni Sifre (min.6)</label><input type="password" id="np" class="fi" autocomplete="new-password"></div>
            <div class="fgg"><label class="fgl">Yeni Sifre Tekrar</label><input type="password" id="np2" class="fi" autocomplete="new-password"></div>
            <div class="fgg"><button type="button" class="btn btn-s" onclick="chPass()">&#128274; Sifreyi Guncelle</button></div>
          </div>
          <div style="margin-top:10px;padding:9px 13px;background:rgba(255,209,102,.07);border:1px solid rgba(255,209,102,.2);border-radius:var(--r);font-size:.73rem;color:var(--yw)">Degisiklikten sonra oturum kapatilir.</div>
        </div></div>

        <!-- Cache -->
        <div class="stc"><div class="stch"><span>&#128465;</span><span class="stct">Cache Yonetimi</span></div><div class="stcb" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
          <a href="?action=clear_cache" class="btn btn-d" onclick="return confirm('Cache temizlensin mi?')">Cache Temizle</a>
          <span style="font-size:.82rem;color:var(--t2)">Su an: <strong style="color:var(--tx)"><?=number_format($s['cache']??0)?></strong> giris</span>
        </div></div>

      </div>
      <div style="margin-top:18px;display:flex;gap:10px">
        <button type="submit" class="btn btn-p" style="padding:12px 28px;font-size:.9rem"><svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>Tum Ayarlari Kaydet</button>
        <a href="?tab=settings" class="btn btn-s">Iptal</a>
      </div>
      </form>
    </div>

    <!-- GITHUB -->
    <div class="pc <?=$activeTab==='github'?'active':''?>" id="tab_github">
      <div class="ph"><div class="pt">GitHub Guncelleme</div><div class="ps">// Repo: <?=htmlspecialchars($cfg['GITHUB_REPO']??'(tanimlanmamis)')?></div></div>

      <?php if(empty($cfg['GITHUB_REPO'])):?>
      <div class="fls err">&#9888; GitHub repo tanimlanmamis. Ayarlar sekmesinden GITHUB_REPO alanini doldurun.</div>
      <?php endif;?>

      <!-- Durum -->
      <div class="ghs" id="ghBox">
        <div style="display:flex;align-items:center;gap:13px">
          <div class="ghd gr" id="ghDot"></div>
          <div><div style="font-size:.95rem;font-weight:600" id="ghTitle">Kontrol ediliyor...</div>
          <div style="font-size:.74rem;color:var(--t3);font-family:var(--mono);margin-top:2px" id="ghSub"></div></div>
        </div>
        <div class="gcb" id="ghCommit" style="display:none">
          <div style="color:var(--ac2)" id="ghHash"></div>
          <div style="color:var(--tx)" id="ghMsg"></div>
          <div style="color:var(--t3);font-size:.7rem;margin-top:2px" id="ghDate"></div>
        </div>
      </div>

      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px">
        <button class="btn btn-s" onclick="chkUpdate()"><svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/></svg>Guncel mi Kontrol Et</button>
        <form method="POST" action="?action=github_push" style="display:inline" onsubmit="return confirm('Tum dosyalar GitHub reposuna yuklensin mi?')">
          <button type="submit" class="btn btn-g"><svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>Siteyi GitHub'a Yukle (Push)</button>
        </form>
        <form method="POST" action="?action=github_update" style="display:inline" onsubmit="return confirm('GitHub reposundan guncelleme cekilsin mi?')">
          <button type="submit" class="btn btn-p"><svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>GitHub'dan Guncelle (Pull)</button>
        </form>
      </div>

      <!-- Ozet tablo -->
      <div class="stc" style="margin-bottom:16px"><div class="stch"><span>&#9881;</span><span class="stct">GitHub Ozet</span></div><div class="stcb">
        <table style="width:100%">
          <tr><td style="padding:6px 0;width:170px;font-size:.78rem;color:var(--t3)">Repo</td><td style="font-family:var(--mono);font-size:.82rem"><?=htmlspecialchars($cfg['GITHUB_REPO']??'-')?></td></tr>
          <tr><td style="padding:6px 0;font-size:.78rem;color:var(--t3)">Branch</td><td style="font-family:var(--mono);font-size:.82rem"><?=htmlspecialchars($cfg['GITHUB_BRANCH']??'main')?></td></tr>
          <tr><td style="padding:6px 0;font-size:.78rem;color:var(--t3)">Token</td><td style="font-family:var(--mono);font-size:.82rem"><?=!empty($cfg['GITHUB_TOKEN'])?'****'.substr($cfg['GITHUB_TOKEN'],-4):'<span style="color:var(--yw)">Tanimlanmamis</span>'?></td></tr>
          <tr><td style="padding:6px 0;font-size:.78rem;color:var(--t3)">Kontrol Araligi</td><td style="font-family:var(--mono);font-size:.82rem"><?=(int)($cfg['UPDATE_CHECK_INTERVAL']??24)?> saat</td></tr>
          <tr><td style="padding:6px 0;font-size:.78rem;color:var(--t3)">Son Commit</td><td style="font-family:var(--mono);font-size:.82rem"><?=$lastCommit?substr($lastCommit,0,12).'...':'<span style="color:var(--t3)">Hic push yapilmamis</span>'?></td></tr>
        </table>
      </div></div>

      <!-- Cron -->
      <div class="cron">
        <div style="font-weight:600;margin-bottom:4px">&#9200; Zamanlanmis Gorev (cPanel Cron Jobs)</div>
        <div style="font-size:.75rem;color:var(--t3);margin-bottom:12px">Her <?=(int)($cfg['UPDATE_CHECK_INTERVAL']??24)?> saatte bir kontrol &mdash; yeni surum varsa zil ikonu belirir</div>
        <div class="code">0 */<?=(int)($cfg['UPDATE_CHECK_INTERVAL']??24)?> * * *   /usr/bin/php <?=realpath(__DIR__.'/..')?>/admin/cron.php</div>
      </div>
    </div>

  </main>
</div>

<script>
const TABS={dashboard:'Dashboard',queries:'Sorgu Gecmisi',tld_stats:'TLD Istatistik',settings:'Site Ayarlari',github:'GitHub Guncelleme'};
function sw(n){
  document.querySelectorAll('.pc').forEach(e=>e.classList.remove('active'));
  document.querySelectorAll('.ni').forEach(e=>e.classList.remove('active'));
  document.getElementById('tab_'+n)?.classList.add('active');
  event?.currentTarget?.classList.add('active');
  document.getElementById('tbTitle').textContent=TABS[n]||n;
  history.replaceState(null,'','?tab='+n);
  if(n==='github') chkUpdate();
}
function toggleBell(){document.getElementById('bellPopup')?.classList.toggle('open')}
function closeBell(){document.getElementById('bellPopup')?.classList.remove('open')}
function dismissBell(){fetch('?action=dismiss_bell').then(()=>location.reload())}
document.addEventListener('click',e=>{if(!e.target.closest('.bb')&&!e.target.closest('.bp'))document.getElementById('bellPopup')?.classList.remove('open')});
function chPass(){
  const c=document.getElementById('cp').value.trim(),n=document.getElementById('np').value.trim(),n2=document.getElementById('np2').value.trim();
  if(!c){alert('Mevcut sifreyi girin.');return}
  if(n.length<6){alert('Min. 6 karakter.');return}
  if(n!==n2){alert('Sifreler eslesmedi.');return}
  const f=document.createElement('form');f.method='POST';f.action='?action=change_password';
  [['current_password',c],['new_password',n],['new_password2',n2]].forEach(([k,v])=>{const i=document.createElement('input');i.type='hidden';i.name=k;i.value=v;f.appendChild(i)});
  document.body.appendChild(f);f.submit();
}
async function chkUpdate(){
  const dot=document.getElementById('ghDot'),title=document.getElementById('ghTitle'),sub=document.getElementById('ghSub'),cb=document.getElementById('ghCommit');
  dot.className='ghd gr';title.textContent='Kontrol ediliyor...';sub.textContent='';
  try{
    const r=await fetch('?action=check_update'),d=await r.json();
    if(d.has_update){dot.className='ghd yw';title.textContent='Yeni surum mevcut!';sub.textContent=d.msg||'';}
    else if(d.remote_hash){dot.className='ghd gn';title.textContent='Surum guncel';sub.textContent=d.msg||'';}
    else{dot.className='ghd rd';title.textContent='Baglanti hatasi';sub.textContent=d.msg||'';}
    if(d.remote_hash){cb.style.display='block';document.getElementById('ghHash').textContent='Commit: '+(d.remote_hash||'').substring(0,12)+'...';document.getElementById('ghMsg').textContent=d.commit_message||'';document.getElementById('ghDate').textContent=d.commit_date||'';}
  }catch(e){dot.className='ghd rd';title.textContent='Hata';sub.textContent=e.message;}
}
<?php if($activeTab==='github'):?>window.addEventListener('load',chkUpdate);<?php endif;?>
</script>
<?php endif;?>
</body>
</html>
