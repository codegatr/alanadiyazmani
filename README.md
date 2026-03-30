# WHOIS Sorgulama Sitesi — Kurulum Kılavuzu
## alanadiyazmani.com | WiseCP (sisyatek.com) Entegrasyonu

---

## 📁 Dosya Yapısı

```
/
├── index.php              # Ana sayfa (WHOIS arama arayüzü)
├── api.php                # AJAX API endpoint (WHOIS sorguları)
├── .htaccess              # Apache yönlendirme ve güvenlik
├── includes/
│   ├── config.php         # ⚙️ AYARLAR BURASI
│   ├── db.php             # Veritabanı bağlantısı
│   ├── whois.php          # WHOIS sorgu motoru
│   ├── whois-servers.php  # 200+ TLD sunucu listesi
│   └── schema.sql         # Veritabanı tabloları
```

---

## 🚀 Kurulum Adımları

### 1. Dosyaları Sunucuya Yükle
Tüm dosyaları web sunucunuzun kök dizinine (`public_html`) yükleyin.

### 2. Veritabanı Oluştur
MySQL'e bağlanın ve schema.sql dosyasını içeri aktarın:
```sql
mysql -u root -p < includes/schema.sql
```
Veya cPanel → phpMyAdmin üzerinden `schema.sql` dosyasını import edin.

### 3. `includes/config.php` Dosyasını Düzenle

```php
// Veritabanı bilgileri
define('DB_HOST', 'localhost');
define('DB_NAME', 'whois_db');        // oluşturduğunuz DB adı
define('DB_USER', 'whois_user');       // DB kullanıcısı
define('DB_PASS', 'SIFRENIZ');         // DB şifresi

// WiseCP (sisyatek.com) URL'leri
define('WISECP_URL', 'https://sisyatek.com');
```

> **ÖNEMLİ:** `WISECP_CART_URL` değişkenini sisyatek.com'daki
> WiseCP'nin domain sipariş URL yapısına göre ayarlayın.
> Varsayılan format: `https://sisyatek.com/cart/?domain=register`

### 4. PHP Gereksinimleri
- PHP 7.4+
- `fsockopen` aktif (socket bağlantısı için)
- PDO + PDO_MySQL
- Mod Rewrite (.htaccess için)

### 5. İzinler
```bash
chmod 644 includes/config.php
chmod 644 .htaccess
```

---

## 🔗 WiseCP Sepete Ekleme URL Formatı

Site, müşteri bir alan adını sorgulayıp "Sepete Ekle" butonuna tıkladığında
şu URL formatını kullanır:

```
https://sisyatek.com/cart/?domain=register&sld=DOMAINADI&tld=.com.tr
```

Örnek:
- `orneksite` için `.com.tr` → `https://sisyatek.com/cart/?domain=register&sld=orneksite&tld=.com.tr`
- `mycompany` için `.com` → `https://sisyatek.com/cart/?domain=register&sld=mycompany&tld=.com`

**WiseCP'nizde bu URL formatının çalıştığını test edin. Farklıysa
`api.php` içindeki `buildCartUrl()` fonksiyonunu güncelleyin.**

---

## 📊 Veritabanı Tabloları

| Tablo | Açıklama |
|-------|----------|
| `whois_queries` | Tüm sorgulama geçmişi |
| `whois_cache` | WHOIS sonuç önbelleği (1 saat TTL) |
| `rate_limits` | IP başına günlük sorgu limiti |
| `domain_stats` | TLD bazlı arama istatistikleri |

---

## ⚙️ Önemli Ayarlar (`config.php`)

```php
define('WHOIS_CACHE_TTL', 3600);    // Cache süresi (saniye) — varsayılan 1 saat
define('MAX_QUERIES_PER_IP', 100);  // IP başına günlük maks sorgu
```

---

## 🛡️ Güvenlik Notları

1. `includes/config.php` dışarıdan erişime kapalı (.htaccess ile)
2. Rate limiting aktif (IP başına günlük 100 sorgu)
3. Tüm kullanıcı girdileri sanitize edilmekte
4. PDO prepared statements kullanılıyor (SQL injection koruması)

---

## 📞 Destek

Herhangi bir sorun için: sisyatek.com destek hattı
