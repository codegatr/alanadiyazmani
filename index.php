<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/whois.php';

$popular_tlds = WhoisLookup::getPopularTLDs();
$search_domain = trim($_GET['domain'] ?? '');

function adUnit(string $slot, string $format = 'auto', string $style = 'display:block'): string {
    if (!ADS_ENABLED || GOOGLE_ADSENSE_ID === 'ca-pub-XXXXXXXXXX') return '';
    return sprintf(
        '<ins class="adsbygoogle" style="%s" data-ad-client="%s" data-ad-slot="%s" data-ad-format="%s" data-full-width-responsive="true"></ins><script>(adsbygoogle=window.adsbygoogle||[]).push({});<\/script>',
        htmlspecialchars($style), htmlspecialchars(GOOGLE_ADSENSE_ID),
        htmlspecialchars($slot), htmlspecialchars($format)
    );
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars(SITE_NAME) ?> - WHOIS Sorgulama | Alan Adi Kontrol</title>
<meta name="description" content="Ucretsiz WHOIS sorgulama. Alan adi sahiplik bilgileri, kayit tarihi, bitis tarihi, DNS sunuculari. 200+ uzanti destegi, Turkiye uzantilari dahil.">
<meta name="robots" content="index, follow">
<link rel="canonical" href="<?= SITE_URL ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<?php if (ADS_ENABLED && GOOGLE_ADSENSE_ID !== 'ca-pub-XXXXXXXXXX'): ?>
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?= htmlspecialchars(GOOGLE_ADSENSE_ID) ?>" crossorigin="anonymous"></script>
<?php endif; ?>
<style>
/* ─────────────────────────────── TOKENS */
:root {
  --c-bg:       #f0f4f8;
  --c-white:    #ffffff;
  --c-surface:  #ffffff;
  --c-border:   #e2e8f0;
  --c-border2:  #cbd5e1;
  --c-text:     #0f172a;
  --c-text2:    #475569;
  --c-text3:    #94a3b8;
  --c-blue:     #2563eb;
  --c-blue-dk:  #1d4ed8;
  --c-blue-lt:  #eff6ff;
  --c-blue-md:  #dbeafe;
  --c-green:    #16a34a;
  --c-green-lt: #f0fdf4;
  --c-green-bd: #bbf7d0;
  --c-red:      #dc2626;
  --c-red-lt:   #fef2f2;
  --c-red-bd:   #fecaca;
  --c-orange:   #ea580c;
  --c-yellow:   #ca8a04;
  --c-yellow-lt:#fefce8;
  --c-mono:     'JetBrains Mono', monospace;
  --c-sans:     'Inter', sans-serif;
  --radius:     8px;
  --radius-lg:  12px;
  --radius-xl:  16px;
  --shadow-sm:  0 1px 3px rgba(0,0,0,.08),0 1px 2px rgba(0,0,0,.06);
  --shadow:     0 4px 12px rgba(0,0,0,.08),0 2px 6px rgba(0,0,0,.05);
  --shadow-lg:  0 10px 30px rgba(0,0,0,.10),0 4px 12px rgba(0,0,0,.06);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{background:var(--c-bg);color:var(--c-text);font-family:var(--c-sans);font-size:15px;line-height:1.6;min-height:100vh}
a{color:var(--c-blue);text-decoration:none}
a:hover{text-decoration:underline}

/* ─────────────────────────────── HEADER */
.header{background:var(--c-white);border-bottom:1px solid var(--c-border);position:sticky;top:0;z-index:100;box-shadow:var(--shadow-sm)}
.header-inner{max-width:1200px;margin:0 auto;padding:0 24px;height:60px;display:flex;align-items:center;justify-content:space-between}
.logo{display:flex;align-items:center;gap:10px;font-size:1.15rem;font-weight:700;color:var(--c-text);text-decoration:none}
.logo-icon{width:32px;height:32px;background:var(--c-blue);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.logo-icon svg{width:18px;height:18px;fill:none;stroke:#fff;stroke-width:2.5}
.logo:hover{text-decoration:none}
.header-nav{display:flex;align-items:center;gap:4px}
.nav-link{padding:7px 14px;border-radius:var(--radius);font-size:.875rem;font-weight:500;color:var(--c-text2);transition:background .15s,color .15s}
.nav-link:hover{background:var(--c-bg);color:var(--c-text);text-decoration:none}
.nav-btn{padding:7px 16px;border-radius:var(--radius);font-size:.875rem;font-weight:600;background:var(--c-blue);color:#fff;transition:background .15s}
.nav-btn:hover{background:var(--c-blue-dk);color:#fff;text-decoration:none}

/* ─────────────────────────────── HERO */
.hero{background:linear-gradient(135deg,#1e3a8a 0%,#1d4ed8 40%,#2563eb 100%);padding:52px 24px 48px;text-align:center;position:relative;overflow:hidden}
.hero::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle at 20% 50%,rgba(255,255,255,.05) 0%,transparent 50%),radial-gradient(circle at 80% 20%,rgba(255,255,255,.05) 0%,transparent 40%)}
.hero-inner{position:relative;z-index:1;max-width:760px;margin:0 auto}
.hero-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);color:#fff;border-radius:100px;padding:5px 14px;font-size:.78rem;font-weight:500;margin-bottom:20px;letter-spacing:.02em}
.hero-badge-dot{width:6px;height:6px;border-radius:50%;background:#4ade80;animation:blink 2s ease-in-out infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.4}}
.hero h1{color:#fff;font-size:clamp(1.8rem,4vw,2.8rem);font-weight:700;line-height:1.2;margin-bottom:12px;letter-spacing:-.02em}
.hero h1 span{color:#93c5fd}
.hero p{color:rgba(255,255,255,.75);font-size:1rem;max-width:520px;margin:0 auto 32px}

/* ─────────────────────────────── SEARCH BAR */
.search-wrap{background:var(--c-white);border-radius:var(--radius-xl);box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden;max-width:700px;margin:0 auto}
.search-row{display:flex;align-items:stretch}
.search-input-wrap{flex:1;display:flex;align-items:center;gap:10px;padding:0 18px;min-width:0}
.search-prefix{font-family:var(--c-mono);font-size:.82rem;color:var(--c-text3);white-space:nowrap;flex-shrink:0}
#searchInput{flex:1;border:none;outline:none;font-family:var(--c-mono);font-size:1rem;color:var(--c-text);padding:18px 0;min-width:0;background:transparent}
#searchInput::placeholder{color:var(--c-text3)}
#searchBtn{background:var(--c-blue);color:#fff;border:none;cursor:pointer;padding:0 28px;font-family:var(--c-sans);font-weight:600;font-size:.9rem;display:flex;align-items:center;gap:8px;transition:background .15s;flex-shrink:0;letter-spacing:.01em}
#searchBtn:hover{background:var(--c-blue-dk)}
#searchBtn svg{width:16px;height:16px;fill:none;stroke:#fff;stroke-width:2.5}
.search-hints{display:flex;flex-wrap:wrap;gap:6px;padding:12px 16px;border-top:1px solid var(--c-border);background:var(--c-bg)}
.hint-chip{font-family:var(--c-mono);font-size:.72rem;color:var(--c-text2);background:var(--c-white);border:1px solid var(--c-border);border-radius:6px;padding:4px 10px;cursor:pointer;transition:all .15s}
.hint-chip:hover{border-color:var(--c-blue);color:var(--c-blue);background:var(--c-blue-lt)}

/* ─────────────────────────────── STATS */
.stats-row{display:flex;justify-content:center;gap:40px;margin-top:28px}
.stat{text-align:center}
.stat-num{font-size:1.4rem;font-weight:700;color:#fff}
.stat-lbl{font-size:.72rem;color:rgba(255,255,255,.6);margin-top:2px;letter-spacing:.04em;text-transform:uppercase}

/* ─────────────────────────────── AD LEADERBOARD */
.ad-leaderboard{text-align:center;padding:16px 24px 0;max-width:750px;margin:0 auto}
.ad-label{font-size:.65rem;color:var(--c-text3);letter-spacing:.08em;text-transform:uppercase;margin-bottom:4px}

/* ─────────────────────────────── MAIN LAYOUT */
.main{max-width:1200px;margin:0 auto;padding:28px 24px 60px;display:grid;grid-template-columns:1fr 300px;gap:24px;align-items:start}

/* ─────────────────────────────── RESULTS */
.results-section{display:none}
.results-section.visible{display:block;margin-bottom:24px}

/* Loading */
.loading-box{background:var(--c-white);border-radius:var(--radius-lg);border:1px solid var(--c-border);padding:48px 24px;text-align:center;display:none}
.loading-box.visible{display:block}
.spinner{width:36px;height:36px;border:3px solid var(--c-border);border-top-color:var(--c-blue);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto 14px}
@keyframes spin{to{transform:rotate(360deg)}}
.loading-txt{font-size:.875rem;color:var(--c-text2)}

/* ─── STATUS BANNER */
.status-banner{border-radius:var(--radius-lg);padding:20px 24px;display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:16px;flex-wrap:wrap}
.status-banner.available{background:var(--c-green-lt);border:1px solid var(--c-green-bd)}
.status-banner.registered{background:var(--c-red-lt);border:1px solid var(--c-red-bd)}
.status-banner.unknown{background:var(--c-yellow-lt);border:1px solid #fde68a}
.status-left{display:flex;align-items:center;gap:14px}
.status-icon{width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.status-banner.available .status-icon{background:var(--c-green-bd)}
.status-banner.registered .status-icon{background:var(--c-red-bd)}
.status-banner.unknown .status-icon{background:#fde68a}
.status-icon svg{width:22px;height:22px;stroke-width:2.5}
.status-banner.available .status-icon svg{stroke:var(--c-green)}
.status-banner.registered .status-icon svg{stroke:var(--c-red)}
.status-banner.unknown .status-icon svg{stroke:var(--c-yellow)}
.status-domain{font-family:var(--c-mono);font-size:1.25rem;font-weight:700;color:var(--c-text)}
.status-label{font-size:.8rem;font-weight:600;margin-top:2px}
.status-banner.available .status-label{color:var(--c-green)}
.status-banner.registered .status-label{color:var(--c-red)}
.status-banner.unknown .status-label{color:var(--c-yellow)}
.status-actions{display:flex;gap:10px;flex-wrap:wrap}
.btn-register{display:inline-flex;align-items:center;gap:7px;background:var(--c-blue);color:#fff;border:none;cursor:pointer;font-weight:600;font-size:.875rem;padding:11px 22px;border-radius:var(--radius);transition:background .15s;text-decoration:none}
.btn-register:hover{background:var(--c-blue-dk);color:#fff;text-decoration:none}
.btn-register svg{width:15px;height:15px;stroke:#fff;stroke-width:2.5;fill:none;flex-shrink:0}

/* ─── REPORT CARD */
.report-card{background:var(--c-white);border:1px solid var(--c-border);border-radius:var(--radius-lg);overflow:hidden;margin-bottom:16px;box-shadow:var(--shadow-sm)}
.report-card-header{padding:14px 20px;border-bottom:1px solid var(--c-border);display:flex;align-items:center;justify-content:space-between;background:var(--c-bg)}
.report-card-title{font-size:.82rem;font-weight:600;color:var(--c-text2);letter-spacing:.03em;text-transform:uppercase;display:flex;align-items:center;gap:8px}
.report-card-title svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2}
.toggle-btn{font-size:.75rem;color:var(--c-blue);cursor:pointer;background:none;border:none;padding:0;font-family:var(--c-sans)}
.toggle-btn:hover{text-decoration:underline}

/* ─── INFO GRID */
.info-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:0}
.info-cell{padding:16px 20px;border-bottom:1px solid var(--c-border);border-right:1px solid var(--c-border)}
.info-cell:nth-child(2n){border-right:none}
.info-cell-label{font-size:.7rem;font-weight:600;color:var(--c-text3);letter-spacing:.06em;text-transform:uppercase;margin-bottom:5px}
.info-cell-value{font-family:var(--c-mono);font-size:.875rem;color:var(--c-text);word-break:break-all;line-height:1.4}
.info-cell-value.good{color:var(--c-green);font-weight:600}
.info-cell-value.bad{color:var(--c-red);font-weight:600}
.info-cell-value.warn{color:var(--c-orange);font-weight:600}

/* ─── DOMAIN AGE / EXPIRY BARS */
.age-bar-wrap{padding:16px 20px}
.age-bar-label{font-size:.75rem;color:var(--c-text2);margin-bottom:8px;display:flex;justify-content:space-between}
.age-bar{height:8px;background:var(--c-border);border-radius:4px;overflow:hidden}
.age-bar-fill{height:100%;border-radius:4px;transition:width .8s ease}
.age-bar-fill.green{background:var(--c-green)}
.age-bar-fill.orange{background:var(--c-orange)}
.age-bar-fill.red{background:var(--c-red)}

/* ─── DNS TABLE */
.dns-list{padding:0}
.dns-row{display:flex;align-items:center;gap:12px;padding:11px 20px;border-bottom:1px solid var(--c-border)}
.dns-row:last-child{border-bottom:none}
.dns-index{width:22px;height:22px;border-radius:50%;background:var(--c-blue-md);color:var(--c-blue);font-size:.68rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.dns-name{font-family:var(--c-mono);font-size:.875rem;color:var(--c-text)}

/* ─── STATUS BADGES */
.status-tag-list{display:flex;flex-wrap:wrap;gap:6px;padding:14px 20px}
.status-tag{font-family:var(--c-mono);font-size:.72rem;padding:4px 10px;border-radius:4px;font-weight:500}
.status-tag.ok{background:var(--c-green-lt);color:var(--c-green);border:1px solid var(--c-green-bd)}
.status-tag.lock{background:var(--c-blue-lt);color:var(--c-blue);border:1px solid var(--c-blue-md)}
.status-tag.warn{background:var(--c-yellow-lt);color:var(--c-yellow);border:1px solid #fde68a}
.status-tag.other{background:var(--c-bg);color:var(--c-text2);border:1px solid var(--c-border)}

/* ─── RAW WHOIS */
.raw-block{display:none;padding:0 20px 20px}
.raw-block.open{display:block}
.raw-pre{background:#0f172a;color:#e2e8f0;border-radius:var(--radius);padding:16px;font-family:var(--c-mono);font-size:.72rem;line-height:1.7;max-height:320px;overflow-y:auto;white-space:pre-wrap;word-break:break-all}
.raw-pre::-webkit-scrollbar{width:5px}
.raw-pre::-webkit-scrollbar-thumb{background:#334155;border-radius:3px}

/* ─── TLD MULTI RESULTS */
.tld-results-summary{font-size:.875rem;color:var(--c-text2);margin-bottom:14px}
.tld-results-summary b{color:var(--c-text)}
.tld-list{display:flex;flex-direction:column;gap:8px}
.tld-row{background:var(--c-white);border:1px solid var(--c-border);border-radius:var(--radius);padding:13px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px;transition:border-color .15s;animation:fadeUp .25s ease backwards}
@keyframes fadeUp{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
.tld-row.available{border-left:3px solid var(--c-green)}
.tld-row.registered{border-left:3px solid var(--c-red)}
.tld-domain{font-family:var(--c-mono);font-size:.95rem;font-weight:600;color:var(--c-text)}
.tld-domain span{color:var(--c-blue)}
.tld-right{display:flex;align-items:center;gap:10px}
.mini-badge{font-size:.7rem;font-weight:700;padding:3px 9px;border-radius:4px;font-family:var(--c-mono)}
.mini-badge.available{background:var(--c-green-lt);color:var(--c-green)}
.mini-badge.registered{background:var(--c-red-lt);color:var(--c-red)}
.mini-badge.checking{background:var(--c-blue-lt);color:var(--c-blue)}
.btn-add{display:inline-flex;align-items:center;gap:5px;background:var(--c-blue);color:#fff;border:none;cursor:pointer;font-weight:600;font-size:.75rem;padding:7px 14px;border-radius:var(--radius);transition:background .15s;text-decoration:none}
.btn-add:hover{background:var(--c-blue-dk);color:#fff;text-decoration:none}
.btn-add.disabled{background:var(--c-border);color:var(--c-text3);pointer-events:none}

/* ─────────────────────────────── MULTI SEARCH PANEL */
.panel{background:var(--c-white);border:1px solid var(--c-border);border-radius:var(--radius-lg);overflow:hidden;margin-bottom:20px;box-shadow:var(--shadow-sm)}
.panel-head{padding:14px 20px;border-bottom:1px solid var(--c-border);background:var(--c-bg);display:flex;align-items:center;gap:8px}
.panel-title{font-size:.875rem;font-weight:600;color:var(--c-text)}
.panel-body{padding:18px 20px}
.tld-tabs{display:flex;gap:6px;margin-bottom:14px;flex-wrap:wrap}
.tld-tab{font-size:.78rem;font-weight:500;padding:6px 14px;border-radius:20px;border:1px solid var(--c-border);color:var(--c-text2);cursor:pointer;background:transparent;transition:all .15s}
.tld-tab.active,.tld-tab:hover{background:var(--c-blue);color:#fff;border-color:var(--c-blue)}
.tld-check-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(115px,1fr));gap:6px;margin-bottom:16px}
.tld-check-label{display:flex;align-items:center;gap:7px;font-family:var(--c-mono);font-size:.75rem;padding:8px 11px;border:1px solid var(--c-border);border-radius:var(--radius);cursor:pointer;user-select:none;transition:all .15s;color:var(--c-text2)}
.tld-check-label.checked{background:var(--c-blue-lt);border-color:var(--c-blue);color:var(--c-blue)}
.tld-check-label input{display:none}
.check-box{width:14px;height:14px;border-radius:3px;border:1.5px solid currentColor;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:transparent;transition:background .15s}
.tld-check-label.checked .check-box{background:var(--c-blue);border-color:var(--c-blue)}
.btn-search-multi{display:inline-flex;align-items:center;gap:7px;background:var(--c-blue);color:#fff;border:none;cursor:pointer;font-weight:600;font-size:.875rem;padding:11px 22px;border-radius:var(--radius);transition:background .15s}
.btn-search-multi:hover{background:var(--c-blue-dk)}

/* ─────────────────────────────── POPULAR TLDS */
.section-title{font-size:.875rem;font-weight:600;color:var(--c-text2);letter-spacing:.04em;text-transform:uppercase;margin-bottom:12px;display:flex;align-items:center;gap:8px}
.section-title::after{content:'';flex:1;height:1px;background:var(--c-border)}
.tld-chip-grid{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:20px}
.tld-chip{font-family:var(--c-mono);font-size:.78rem;color:var(--c-text2);background:var(--c-white);border:1px solid var(--c-border);border-radius:var(--radius);padding:6px 12px;cursor:pointer;transition:all .15s}
.tld-chip:hover{border-color:var(--c-blue);color:var(--c-blue);background:var(--c-blue-lt)}

/* ─────────────────────────────── SIDEBAR */
.sidebar{display:flex;flex-direction:column;gap:18px;position:sticky;top:80px}
.ad-box{background:var(--c-white);border:1px solid var(--c-border);border-radius:var(--radius-lg);padding:12px;text-align:center;min-height:260px;display:flex;flex-direction:column;align-items:center;justify-content:center;box-shadow:var(--shadow-sm)}
.ad-placeholder{width:100%;height:250px;background:var(--c-bg);border-radius:var(--radius);display:flex;align-items:center;justify-content:center;font-size:.72rem;color:var(--c-text3);font-family:var(--c-mono)}
.info-box{background:var(--c-white);border:1px solid var(--c-border);border-radius:var(--radius-lg);padding:18px;box-shadow:var(--shadow-sm)}
.info-box-title{font-size:.8rem;font-weight:600;color:var(--c-text);margin-bottom:12px;display:flex;align-items:center;gap:6px}
.info-box-title svg{width:14px;height:14px;stroke:var(--c-blue);fill:none;stroke-width:2}
.info-box p{font-size:.8rem;color:var(--c-text2);line-height:1.6;margin-bottom:8px}
.info-box p:last-child{margin-bottom:0}
.info-box b{color:var(--c-text)}

/* ─────────────────────────────── FOOTER */
footer{background:var(--c-white);border-top:1px solid var(--c-border);padding:20px 24px;text-align:center}
.footer-inner{max-width:1200px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px}
.footer-copy{font-size:.8rem;color:var(--c-text3)}
.footer-links{display:flex;gap:16px}
.footer-links a{font-size:.8rem;color:var(--c-text3);transition:color .15s}
.footer-links a:hover{color:var(--c-text);text-decoration:none}
.footer-badge{font-size:.72rem;background:var(--c-blue-lt);color:var(--c-blue);border:1px solid var(--c-blue-md);border-radius:4px;padding:2px 8px;font-weight:600}

/* ─────────────────────────────── ALERT */
.alert{border-radius:var(--radius);padding:14px 18px;font-size:.875rem;display:flex;align-items:center;gap:10px;margin-bottom:16px}
.alert-error{background:var(--c-red-lt);color:var(--c-red);border:1px solid var(--c-red-bd)}
.alert-info{background:var(--c-blue-lt);color:var(--c-blue);border:1px solid var(--c-blue-md)}

/* ─────────────────────────────── UTILS */
::-webkit-scrollbar{width:6px;height:6px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--c-border2);border-radius:3px}

/* ─────────────────────────────── RESPONSIVE */
@media(max-width:900px){
  .main{grid-template-columns:1fr}
  .sidebar{position:static}
}
@media(max-width:640px){
  .header-inner{padding:0 16px}
  .header-nav .nav-link{display:none}
  .hero{padding:36px 16px 32px}
  .hero h1{font-size:1.6rem}
  .stats-row{gap:24px}
  .stat-num{font-size:1.1rem}
  .main{padding:20px 16px 48px}
  .search-row{flex-direction:column}
  #searchBtn{padding:16px;justify-content:center;border-radius:0}
  .status-banner{flex-direction:column;align-items:flex-start}
  .info-grid{grid-template-columns:1fr}
  .info-cell{border-right:none}
  footer{padding:16px}
  .footer-inner{flex-direction:column;text-align:center}
}
</style>
</head>
<body>

<!-- HEADER -->
<header class="header">
  <div class="header-inner">
    <a href="/" class="logo">
      <div class="logo-icon">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
      </div>
      <?= htmlspecialchars(SITE_NAME) ?>
    </a>
    <nav class="header-nav">
      <a href="/" class="nav-link">WHOIS Sorgula</a>
      <a href="<?= WISECP_URL ?>" target="_blank" class="nav-link">Alan Adi Kaydet</a>
      <a href="admin/" class="nav-link">Admin</a>
      <a href="<?= WISECP_URL ?>" target="_blank" class="nav-btn">Giris Yap</a>
    </nav>
  </div>
</header>

<!-- HERO -->
<section class="hero">
  <div class="hero-inner">
    <div class="hero-badge">
      <span class="hero-badge-dot"></span>
      200+ UZANTI &bull; TURKIYE UZANTILARI DAHIL &bull; ANLIK SORGULAMA
    </div>
    <h1>Alan Adiniz <span>Musait mi?</span></h1>
    <p>WHOIS sorgulama ile sahiplik bilgileri, kayit tarihi, bitis tarihi ve DNS kayitlarini aninda goruntuleyin.</p>
    <div class="search-wrap">
      <div class="search-row">
        <div class="search-input-wrap">
          <span class="search-prefix">whois://</span>
          <input type="text" id="searchInput" placeholder="ornek.com.tr veya ornek.com"
            autocomplete="off" autocapitalize="off" spellcheck="false"
            value="<?= htmlspecialchars($search_domain) ?>">
        </div>
        <button id="searchBtn" onclick="doSearch()">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.65-4.65"/></svg>
          SORGULA
        </button>
      </div>
      <div class="search-hints">
        <span class="hint-chip" onclick="appendTLD('.com.tr')">.com.tr</span>
        <span class="hint-chip" onclick="appendTLD('.net.tr')">.net.tr</span>
        <span class="hint-chip" onclick="appendTLD('.org.tr')">.org.tr</span>
        <span class="hint-chip" onclick="appendTLD('.com')">.com</span>
        <span class="hint-chip" onclick="appendTLD('.net')">.net</span>
        <span class="hint-chip" onclick="appendTLD('.io')">.io</span>
        <span class="hint-chip" onclick="appendTLD('.app')">.app</span>
        <span class="hint-chip" onclick="appendTLD('.co')">.co</span>
        <span class="hint-chip" onclick="appendTLD('.xyz')">.xyz</span>
      </div>
    </div>
    <div class="stats-row">
      <div class="stat"><div class="stat-num">200+</div><div class="stat-lbl">Uzanti</div></div>
      <div class="stat"><div class="stat-num">&lt;2s</div><div class="stat-lbl">Sorgu Suresi</div></div>
      <div class="stat"><div class="stat-num">7/24</div><div class="stat-lbl">Canli Sistem</div></div>
    </div>
  </div>
</section>

<!-- AD LEADERBOARD -->
<?php if (ADS_ENABLED && GOOGLE_ADSENSE_ID !== 'ca-pub-XXXXXXXXXX'): ?>
<div style="text-align:center;padding:14px 24px 0;max-width:750px;margin:0 auto">
  <div class="ad-label">REKLAM</div>
  <?= adUnit(AD_SLOT_HEADER, 'horizontal', 'display:inline-block;width:728px;height:90px') ?>
</div>
<?php endif; ?>

<!-- MAIN -->
<div class="main">
  <div class="main-content">

    <!-- RESULTS -->
    <section class="results-section" id="resultsSection">
      <div class="loading-box" id="loadingBox">
        <div class="spinner"></div>
        <div class="loading-txt">WHOIS sunucusu sorgulanıyor...</div>
      </div>
      <div id="singleResult"></div>
      <div id="multiResult"></div>
    </section>

    <!-- MULTI TLD PANEL -->
    <div class="panel">
      <div class="panel-head">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        <span class="panel-title">Coklu Uzanti Sorgulama</span>
      </div>
      <div class="panel-body">
        <div class="tld-tabs" id="tldCatTabs">
          <?php foreach ($popular_tlds as $group => $tlds): ?>
          <button class="tld-tab <?= $group === 'Turkiye' ? 'active' : '' ?>" onclick="switchCat('<?= $group ?>')"><?= htmlspecialchars($group) ?></button>
          <?php endforeach; ?>
        </div>
        <?php foreach ($popular_tlds as $group => $tlds): ?>
        <div class="tld-check-grid" id="tldGroup_<?= $group ?>" style="<?= $group !== 'Turkiye' ? 'display:none' : '' ?>">
          <?php foreach ($tlds as $tld): ?>
          <?php $checked = in_array($tld, ['.com.tr','.net.tr','.com','.net']); ?>
          <label class="tld-check-label <?= $checked ? 'checked' : '' ?>">
            <input type="checkbox" value="<?= htmlspecialchars($tld) ?>" class="tld-checkbox" <?= $checked ? 'checked' : '' ?>>
            <span class="check-box">
              <?php if ($checked): ?><svg width="9" height="7" viewBox="0 0 10 8" fill="none"><path d="M1 4l3 3 5-6" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg><?php endif; ?>
            </span>
            <?= htmlspecialchars($tld) ?>
          </label>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        <button class="btn-search-multi" onclick="doMultiSearch()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
          Secili Uzantilari Sorgula
        </button>
      </div>
    </div>

    <!-- POPULAR TLDs -->
    <div class="panel">
      <div class="panel-head">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        <span class="panel-title">Populer Uzantilar</span>
      </div>
      <div class="panel-body">
        <?php foreach ($popular_tlds as $group => $tlds): ?>
        <div class="section-title"><?= htmlspecialchars($group) ?></div>
        <div class="tld-chip-grid">
          <?php foreach ($tlds as $tld): ?>
          <button class="tld-chip" onclick="quickSearch('<?= htmlspecialchars($tld) ?>')"><?= htmlspecialchars($tld) ?></button>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div><!-- /main-content -->

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <!-- Ad -->
    <div class="ad-box">
      <?php if (ADS_ENABLED && GOOGLE_ADSENSE_ID !== 'ca-pub-XXXXXXXXXX'): ?>
      <div class="ad-label">REKLAM</div>
      <?= adUnit(AD_SLOT_SIDEBAR, 'rectangle', 'display:inline-block;width:250px;height:250px') ?>
      <?php else: ?>
      <div class="ad-placeholder">[ 250 x 250 Reklam Alani ]</div>
      <?php endif; ?>
    </div>

    <!-- Bilgi kutusu -->
    <div class="info-box">
      <div class="info-box-title">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        WHOIS Nedir?
      </div>
      <p>WHOIS, bir <b>alan adinin kime ait oldugunu</b>, ne zaman kayit edildigini ve ne zaman sona erecegini gosteren bir protokoldur.</p>
      <p><b>.com.tr</b> uzantilari yalnizca Turkiye kayitli sirketlere tahsis edilir. <b>.gen.tr</b> bireysel kullanim icin aciktir.</p>
    </div>

    <!-- 2. Ad -->
    <?php if (ADS_ENABLED && GOOGLE_ADSENSE_ID !== 'ca-pub-XXXXXXXXXX'): ?>
    <div class="ad-box">
      <div class="ad-label">REKLAM</div>
      <?= adUnit(AD_SLOT_SIDEBAR, 'rectangle', 'display:inline-block;width:250px;height:250px') ?>
    </div>
    <?php endif; ?>
  </aside>
</div>

<!-- FOOTER -->
<footer>
  <div class="footer-inner">
    <span class="footer-copy">&copy; <?= date('Y') ?> <?= htmlspecialchars(SITE_NAME) ?> &mdash; alanadiyazmani.com</span>
    <div class="footer-links">
      <a href="<?= WISECP_URL ?>" target="_blank"><span class="footer-badge">WiseCP</span></a>
      <a href="<?= WISECP_URL ?>" target="_blank">Alan Adi Kaydet</a>
      <a href="admin/">Admin Panel</a>
    </div>
  </div>
</footer>

<script>
const CART = '<?= WISECP_CART_URL ?>';
const TLD_GROUPS = <?= json_encode($popular_tlds, JSON_UNESCAPED_UNICODE) ?>;

// Enter key
document.getElementById('searchInput').addEventListener('keypress', e => { if (e.key === 'Enter') doSearch(); });

// Checkbox toggle
document.addEventListener('change', e => {
  if (!e.target.classList.contains('tld-checkbox')) return;
  const lbl = e.target.closest('.tld-check-label');
  lbl.classList.toggle('checked', e.target.checked);
  const box = lbl.querySelector('.check-box');
  box.innerHTML = e.target.checked
    ? '<svg width="9" height="7" viewBox="0 0 10 8" fill="none"><path d="M1 4l3 3 5-6" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>'
    : '';
});

function appendTLD(tld) {
  const inp = document.getElementById('searchInput');
  let v = inp.value.trim();
  const known = ['.com.tr','.net.tr','.org.tr','.gov.tr','.biz.tr','.info.tr','.web.tr','.gen.tr','.tel.tr','.tv.tr','.name.tr','.av.tr','.dr.tr','.k12.tr','.com','.net','.org','.io','.app','.co','.xyz','.me','.tv','.cc','.ws','.dev','.tech','.online','.site','.store','.shop','.eu','.de','.fr','.it','.es','.nl','.uk','.se','.pl'];
  for (const t of known) { if (v.endsWith(t)) { v = v.slice(0,-t.length); break; } }
  inp.value = v + tld;
  inp.focus();
}

function quickSearch(tld) {
  if (!document.getElementById('searchInput').value.trim()) return;
  appendTLD(tld);
  doSearch();
}

function switchCat(name) {
  document.querySelectorAll('.tld-tab').forEach(t => t.classList.toggle('active', t.textContent.trim() === name));
  Object.keys(TLD_GROUPS).forEach(g => {
    const el = document.getElementById('tldGroup_'+g);
    if (el) el.style.display = g === name ? '' : 'none';
  });
}

async function doSearch() {
  const raw = document.getElementById('searchInput').value.trim();
  if (!raw) return;
  showLoading();
  try {
    const res  = await fetch('api.php?action=whois&domain=' + encodeURIComponent(raw));
    const json = await res.json();
    hideLoading();
    if (!json.success) { showError(json.error || 'Bir hata olustu.'); return; }
    renderSingle(json.data);
  } catch(e) { hideLoading(); showError('Sunucuya baglanılamadı.'); }
}

async function doMultiSearch() {
  const inp = document.getElementById('searchInput').value.trim();
  if (!inp) { document.getElementById('searchInput').focus(); return; }
  const parsed = parseDomainJS(inp);
  const sld = parsed.sld || inp;
  const checked = [...document.querySelectorAll('.tld-checkbox:checked')].map(e => e.value);
  if (!checked.length) { alert('Lutfen en az bir uzanti secin.'); return; }
  showLoading();
  try {
    const params = new URLSearchParams({ action:'check', domain:sld });
    checked.forEach(t => params.append('tlds[]', t));
    const res  = await fetch('api.php?' + params);
    const json = await res.json();
    hideLoading();
    if (!json.success) { showError(json.error||'Hata.'); return; }
    renderMulti(json.data, sld);
  } catch(e) { hideLoading(); showError('Sunucuya baglanılamadı.'); }
}

/* ── RENDER SINGLE ──────────────────────────── */
function renderSingle(d) {
  const avail  = d.is_available;
  const cls    = avail === true ? 'available' : avail === false ? 'registered' : 'unknown';
  const lbl    = avail === true ? 'MUSAIT — Hemen Kaydedin!' : avail === false ? 'KAYITLI — Bu Alan Adi Dolu' : 'DURUM BELIRSIZ';

  const cartURL = d.cart_url || (CART + '&sld=' + encodeURIComponent(d.sld||'') + '&tld=' + encodeURIComponent(d.tld||''));

  // Status banner icon
  const icons = {
    available: '<svg viewBox="0 0 24 24" fill="none" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>',
    registered:'<svg viewBox="0 0 24 24" fill="none" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
    unknown:   '<svg viewBox="0 0 24 24" fill="none" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
  };

  const registerBtn = avail === true
    ? `<a href="${esc(cartURL)}" target="_blank" class="btn-register">
        <svg viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
        Hemen Kaydet
      </a>` : '';

  // Build whois detail cards
  let detailHTML = '';

  // Card 1: Temel bilgiler
  const basicFields = [
    ['Alan Adi',        d.domain,       ''],
    ['Kayit Firmasi',   d.registrar,    ''],
    ['Kayit Sahibi',    d.registrant,   ''],
    ['WHOIS Sunucusu',  d.whois_server, ''],
  ].filter(([,v]) => v);

  if (basicFields.length) {
    detailHTML += `<div class="report-card">
      <div class="report-card-header">
        <div class="report-card-title"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>KAYIT BILGILERI</div>
      </div>
      <div class="info-grid">
        ${basicFields.map(([l,v,c])=>`<div class="info-cell"><div class="info-cell-label">${l}</div><div class="info-cell-value ${c}">${esc(v)}</div></div>`).join('')}
      </div>
    </div>`;
  }

  // Card 2: Tarihler + yaş hesabı
  if (d.creation_date || d.expiry_date) {
    const ageDays  = d.creation_date ? daysSince(d.creation_date) : null;
    const expDays  = d.expiry_date   ? daysUntil(d.expiry_date)   : null;
    const ageYears = ageDays  ? (ageDays / 365).toFixed(1)  : null;
    const expClass = expDays !== null ? (expDays < 30 ? 'red' : expDays < 90 ? 'orange' : 'green') : 'green';
    const expPct   = expDays !== null ? Math.max(0, Math.min(100, (expDays / 365) * 100)) : 0;

    const dateFields = [
      ['Kayit Tarihi', d.creation_date ? fmtDate(d.creation_date) : null, ''],
      ['Bitis Tarihi', d.expiry_date   ? fmtDate(d.expiry_date)   : null, expDays !== null && expDays < 30 ? 'bad' : ''],
      ['Alan Adi Yasi', ageYears ? ageYears + ' yil (' + ageDays + ' gun)' : null, 'good'],
      ['Son Guncelleme', d.updated_date ? fmtDate(d.updated_date) : null, ''],
    ].filter(([,v]) => v);

    detailHTML += `<div class="report-card">
      <div class="report-card-header">
        <div class="report-card-title"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>TARIH VE YAS RAPORU</div>
      </div>
      <div class="info-grid">
        ${dateFields.map(([l,v,c])=>`<div class="info-cell"><div class="info-cell-label">${l}</div><div class="info-cell-value ${c}">${esc(v)}</div></div>`).join('')}
      </div>
      ${expDays !== null ? `<div class="age-bar-wrap">
        <div class="age-bar-label">
          <span>Bitis Suresi</span>
          <span>${expDays > 0 ? expDays + ' gun kaldi' : 'SURESI DOLDU'}</span>
        </div>
        <div class="age-bar"><div class="age-bar-fill ${expClass}" style="width:${expPct}%"></div></div>
      </div>` : ''}
    </div>`;
  }

  // Card 3: DNS sunucuları
  if ((d.name_servers||[]).length) {
    const nsItems = d.name_servers.map((ns,i)=>`<div class="dns-row"><div class="dns-index">${i+1}</div><div class="dns-name">${esc(ns)}</div></div>`).join('');
    detailHTML += `<div class="report-card">
      <div class="report-card-header">
        <div class="report-card-title"><svg viewBox="0 0 24 24"><circle cx="12" cy="5" r="3"/><circle cx="5" cy="19" r="3"/><circle cx="19" cy="19" r="3"/><line x1="12" y1="8" x2="5.5" y2="16.5"/><line x1="12" y1="8" x2="18.5" y2="16.5"/></svg>DNS SUNUCULARI (Name Servers)</div>
      </div>
      <div class="dns-list">${nsItems}</div>
    </div>`;
  }

  // Card 4: Alan adı durumu/kilitleri
  if ((d.status||[]).length) {
    const tags = d.status.map(s => {
      const sl = s.toLowerCase();
      const cls = sl.includes('ok') ? 'ok' : sl.includes('lock') ? 'lock' : sl.includes('hold') || sl.includes('pending') ? 'warn' : 'other';
      return `<span class="status-tag ${cls}">${esc(s)}</span>`;
    }).join('');
    detailHTML += `<div class="report-card">
      <div class="report-card-header">
        <div class="report-card-title"><svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>ALAN ADI DURUMLARI (EPP Status)</div>
      </div>
      <div class="status-tag-list">${tags}</div>
    </div>`;
  }

  // Card 5: Ham WHOIS
  if (d.raw) {
    detailHTML += `<div class="report-card">
      <div class="report-card-header">
        <div class="report-card-title"><svg viewBox="0 0 24 24"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>HAM WHOIS VERISI</div>
        <button class="toggle-btn" onclick="toggleRaw()">Goster / Gizle</button>
      </div>
      <div class="raw-block" id="rawBlock"><pre class="raw-pre">${esc(d.raw)}</pre></div>
    </div>`;
  }

  const html = `
    <div class="status-banner ${cls}" style="animation:fadeUp .3s ease">
      <div class="status-left">
        <div class="status-icon">${icons[cls]||''}</div>
        <div>
          <div class="status-domain">${esc(d.domain)}</div>
          <div class="status-label">${lbl}</div>
        </div>
      </div>
      <div class="status-actions">
        ${registerBtn}
        <a href="https://wa.me/?text=${encodeURIComponent(d.domain + ' WHOIS: ' + (d.is_available ? 'Musait' : 'Kayitli'))}" target="_blank" class="btn-register" style="background:#16a34a">Paylas</a>
      </div>
    </div>
    ${detailHTML}`;

  document.getElementById('singleResult').innerHTML = html;
  document.getElementById('multiResult').innerHTML = '';
  showResults(); scrollToResults();
}

/* ── RENDER MULTI ───────────────────────────── */
function renderMulti(items, sld) {
  const avail = items.filter(d => d.is_available === true).length;
  const rows  = items.map((d,i) => {
    const cls  = d.is_available === true ? 'available' : d.is_available === false ? 'registered' : '';
    const badge= d.is_available === true ? 'available">MUSAIT' : d.is_available === false ? 'registered">KAYITLI' : 'checking">KONTROL EDILIYOR';
    const btn  = d.is_available === true
      ? `<a href="${esc(d.cart_url)}" target="_blank" class="btn-add"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/></svg>Sepete Ekle</a>`
      : `<span class="btn-add disabled">Dolu</span>`;
    return `<div class="tld-row ${cls}" style="animation-delay:${i*30}ms">
      <div class="tld-domain">${esc(d.sld||sld)}<span>${esc(d.tld)}</span></div>
      <div class="tld-right">
        <span class="mini-badge ${badge}</span>
        ${btn}
      </div>
    </div>`;
  }).join('');

  document.getElementById('singleResult').innerHTML = '';
  document.getElementById('multiResult').innerHTML  = `
    <div class="tld-results-summary">
      <b>"${esc(sld)}"</b> icin sonuclar:
      <b style="color:#16a34a">${avail} uzanti musait</b> / ${items.length} toplam
    </div>
    <div class="tld-list">${rows}</div>`;
  showResults(); scrollToResults();
}

/* ── HELPERS ────────────────────────────────── */
function showLoading(){ document.getElementById('loadingBox').classList.add('visible'); document.getElementById('singleResult').innerHTML=''; document.getElementById('multiResult').innerHTML=''; showResults(); }
function hideLoading(){ document.getElementById('loadingBox').classList.remove('visible'); }
function showResults(){ document.getElementById('resultsSection').classList.add('visible'); }
function showError(msg){ document.getElementById('singleResult').innerHTML=`<div class="alert alert-error">&#9888; ${esc(msg)}</div>`; document.getElementById('multiResult').innerHTML=''; showResults(); }
function scrollToResults(){ document.getElementById('resultsSection').scrollIntoView({ behavior:'smooth', block:'start' }); }
function toggleRaw(){ document.getElementById('rawBlock')?.classList.toggle('open'); }
function esc(s){ if(!s) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function parseDomainJS(d) {
  d = d.replace(/^https?:\/\/(www\.)?/,'').replace(/\/.*$/,'').toLowerCase();
  const p = d.split('.');
  if (p.length >= 3) {
    const t2 = p.slice(-2).join('.');
    if (['com.tr','net.tr','org.tr','gov.tr','co.uk','com.au','com.br','co.za'].includes(t2))
      return { sld: p.slice(0,-2).join('.'), tld: '.'+t2 };
  }
  return { sld: p.slice(0,-1).join('.'), tld: '.'+(p.slice(-1)[0]||'') };
}

function daysSince(dateStr) {
  try {
    const d = new Date(dateStr.split('T')[0]);
    if (isNaN(d)) return null;
    return Math.floor((Date.now() - d.getTime()) / 86400000);
  } catch(e) { return null; }
}
function daysUntil(dateStr) {
  try {
    const d = new Date(dateStr.split('T')[0]);
    if (isNaN(d)) return null;
    return Math.floor((d.getTime() - Date.now()) / 86400000);
  } catch(e) { return null; }
}
function fmtDate(str) {
  if (!str) return null;
  try {
    const d = new Date(str.split('T')[0]);
    if (isNaN(d)) return str;
    return d.toLocaleDateString('tr-TR', { day:'2-digit', month:'long', year:'numeric' });
  } catch(e) { return str; }
}

<?php if ($search_domain): ?>
window.addEventListener('load', () => doSearch());
<?php endif; ?>
</script>
</body>
</html>
