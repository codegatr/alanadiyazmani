<?php
require_once __DIR__ . '/config.php';

class WhoisLookup {

    private $servers;
    private $timeout = 10;

    public function __construct() {
        $this->servers = require __DIR__ . '/whois-servers.php';
    }

    /**
     * Alan adını parçala: sld + tld
     */
    public function parseDomain(string $domain): array {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = rtrim($domain, '/');
        $domain = preg_replace('#/.*$#', '', $domain);

        $parts = explode('.', $domain);
        $count = count($parts);

        // İkinci seviye TLD kontrolü (com.tr, co.uk vb.)
        if ($count >= 3) {
            $potentialTld2 = $parts[$count-2] . '.' . $parts[$count-1];
            if (isset($this->servers[$potentialTld2])) {
                $tld = '.' . $potentialTld2;
                $sld = implode('.', array_slice($parts, 0, $count-2));
                return ['sld' => $sld, 'tld' => $tld, 'full' => $domain];
            }
        }

        if ($count >= 2) {
            $tld = '.' . $parts[$count-1];
            $sld = implode('.', array_slice($parts, 0, $count-1));
            return ['sld' => $sld, 'tld' => $tld, 'full' => $domain];
        }

        return ['sld' => $domain, 'tld' => '', 'full' => $domain];
    }

    /**
     * WHOIS sorgusu yap
     */
    public function query(string $domain): array {
        $parsed = $this->parseDomain($domain);
        $tldKey = ltrim($parsed['tld'], '.');

        if (!isset($this->servers[$tldKey])) {
            $whoisServer  = 'whois.iana.org';
            $availPattern = 'not found';
        } else {
            $whoisServer  = $this->servers[$tldKey]['host'];
            $availPattern = $this->servers[$tldKey]['pattern'];
        }

        // TR uzantıları için HTTPS API kullan (port 43 çoğu hostingde kapalı)
        $tr_tlds = ['com.tr','net.tr','org.tr','gov.tr','edu.tr','mil.tr','k12.tr',
                    'av.tr','dr.tr','bbs.tr','tel.tr','info.tr','tv.tr','bel.tr',
                    'pol.tr','tsk.tr','name.tr','web.tr','gen.tr','biz.tr'];

        if (in_array($tldKey, $tr_tlds)) {
            return $this->queryTR($parsed);
        }

        $raw = $this->socketQuery($whoisServer, $parsed['full']);

        // Socket başarısız olduysa RDAP dene
        if ($raw === false) {
            $rdap = $this->queryRDAP($parsed['full']);
            if ($rdap) {
                return array_merge([
                    'success'      => true,
                    'domain'       => $parsed['full'],
                    'sld'          => $parsed['sld'],
                    'tld'          => $parsed['tld'],
                    'whois_server' => 'rdap',
                    'raw'          => json_encode($rdap, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                ], $this->parseRDAPData($rdap));
            }
            return [
                'success'      => false,
                'error'        => 'WHOIS sunucusuna baglanilamadi. Hosting saglayaniniz port 43 e izin vermeyebilir.',
                'domain'       => $parsed['full'],
                'sld'          => $parsed['sld'],
                'tld'          => $parsed['tld'],
                'is_available' => null,
            ];
        }

        $isAvailable = $this->checkAvailability($raw, $availPattern, $tldKey);
        $parsed_data = $this->parseWhoisData($raw);

        return [
            'success'       => true,
            'domain'        => $parsed['full'],
            'sld'           => $parsed['sld'],
            'tld'           => $parsed['tld'],
            'is_available'  => $isAvailable,
            'raw'           => $raw,
            'registrar'     => $parsed_data['registrar'] ?? null,
            'registrant'    => $parsed_data['registrant'] ?? null,
            'creation_date' => $parsed_data['creation_date'] ?? null,
            'expiry_date'   => $parsed_data['expiry_date'] ?? null,
            'name_servers'  => $parsed_data['name_servers'] ?? [],
            'status'        => $parsed_data['status'] ?? [],
            'whois_server'  => $whoisServer,
        ];
    }

    /**
     * TR uzantıları için NIC.TR web servisi
     * port 43 yerine HTTPS üzerinden sorgu yapar
     */
    private function queryTR(array $parsed): array {
        $domain = $parsed['full'];
        $tld    = $parsed['tld'];
        $sld    = $parsed['sld'];

        // Önce TCP socket dene (en güvenilir)
        $raw = $this->socketQuery('whois.nic.tr', $domain);
        if (!$raw || strlen($raw) < 10) {
            $raw = $this->socketQuery('whois.metu.edu.tr', $domain);
        }

        // Socket başarısızsa cURL dene
        if (!$raw || strlen($raw) < 10) {
            $raw = $this->curlQuery('https://www.whois.nic.tr/' . rawurlencode($domain));
        }

        // HTTP başarısızsa TCP'yi dene
        if ($raw === false || strlen($raw) < 30) {
            $raw = $this->socketQuery('whois.metu.edu.tr', $domain);
        }
        if ($raw === false || strlen($raw) < 30) {
            $raw = $this->socketQuery('whois.nic.tr', $domain);
        }

        if ($raw === false || strlen($raw) < 10) {
            // Son çare: RDAP
            $rdap = $this->queryRDAP($domain);
            if ($rdap) {
                return array_merge([
                    'success'      => true,
                    'domain'       => $domain,
                    'sld'          => $sld,
                    'tld'          => $tld,
                    'whois_server' => 'rdap',
                    'raw'          => json_encode($rdap, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                ], $this->parseRDAPData($rdap));
            }
            return [
                'success'      => false,
                'error'        => '.tr WHOIS sunucusuna ulaşılamadı. Lütfen whois.nic.tr adresini manuel kontrol edin.',
                'domain'       => $domain,
                'sld'          => $sld,
                'tld'          => $tld,
                'is_available' => null,
            ];
        }

        $raw_lower   = strtolower($raw);
        $isAvailable = false;

        // NIC.TR yanıt formatları
        if (str_contains($raw_lower, 'no match found') ||
            str_contains($raw_lower, 'no match') ||
            str_contains($raw_lower, 'not found') ||
            str_contains($raw_lower, 'no object found')) {
            $isAvailable = true;
        } elseif (str_contains($raw_lower, 'domain-name:') ||
                  str_contains($raw_lower, 'registrar:') ||
                  str_contains($raw_lower, 'holder-c:') ||
                  str_contains($raw_lower, 'nserver:')) {
            $isAvailable = false;
        }

        $parsed_data = $this->parseWhoisData($raw);

        return [
            'success'       => true,
            'domain'        => $domain,
            'sld'           => $sld,
            'tld'           => $tld,
            'is_available'  => $isAvailable,
            'raw'           => $raw,
            'registrar'     => $parsed_data['registrar'] ?? null,
            'registrant'    => $parsed_data['registrant'] ?? null,
            'creation_date' => $parsed_data['creation_date'] ?? null,
            'expiry_date'   => $parsed_data['expiry_date'] ?? null,
            'name_servers'  => $parsed_data['name_servers'] ?? [],
            'status'        => $parsed_data['status'] ?? [],
            'updated_date'  => $parsed_data['updated_date'] ?? null,
            'whois_server'  => 'whois.nic.tr',
        ];
    }

    /**
     * cURL ile HTTP sorgusu (file_get_contents fallback)
     */
    private function curlQuery(string $url): string|false {
        if (!function_exists('curl_init')) return false;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'WhoisBot/1.0',
        ]);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result ?: false;
    }

    /**
     * RDAP (Registration Data Access Protocol) sorgusu
     * Port 43 kapalı olduğunda HTTPS fallback
     */
    private function queryRDAP(string $domain): ?array {
        $url  = 'https://rdap.org/domain/' . rawurlencode($domain);
        $body = $this->curlQuery($url);
        if (!$body) {
            // cURL yoksa file_get_contents dene
            $ctx = stream_context_create([
                'http' => ['timeout'=>8,'ignore_errors'=>true,'user_agent'=>'WhoisBot/1.0'],
                'ssl'  => ['verify_peer'=>false,'verify_peer_name'=>false],
            ]);
            $body = @file_get_contents($url, false, $ctx);
        }
        if (!$body) return null;
        $data = json_decode($body, true);
        return is_array($data) ? $data : null;
    }

    private function parseRDAPData(array $rdap): array {
        $isAvailable = false;
        if (isset($rdap['errorCode']) && $rdap['errorCode'] == 404) {
            $isAvailable = true;
        }

        $registrar = null;
        if (!empty($rdap['entities'])) {
            foreach ($rdap['entities'] as $e) {
                if (in_array('registrar', $e['roles'] ?? [])) {
                    $registrar = $e['vcardArray'][1][1][3] ?? ($e['handle'] ?? null);
                }
            }
        }

        $ns = [];
        foreach ($rdap['nameservers'] ?? [] as $n) {
            $ns[] = strtolower($n['ldhName'] ?? '');
        }

        $creation = null;
        $expiry   = null;
        foreach ($rdap['events'] ?? [] as $ev) {
            if ($ev['eventAction'] === 'registration') $creation = $ev['eventDate'] ?? null;
            if ($ev['eventAction'] === 'expiration')   $expiry   = $ev['eventDate'] ?? null;
        }

        $status = $rdap['status'] ?? [];

        return [
            'is_available'  => $isAvailable,
            'registrar'     => $registrar,
            'registrant'    => null,
            'creation_date' => $creation,
            'expiry_date'   => $expiry,
            'name_servers'  => array_filter($ns),
            'status'        => is_array($status) ? $status : [],
        ];
    }

    /**
     * TCP socket ile WHOIS sorgusu
     */
    private function socketQuery(string $server, string $domain): string|false {
        $port = 43;
        $fp = @fsockopen($server, $port, $errno, $errstr, $this->timeout);
        if (!$fp) return false;

        stream_set_timeout($fp, $this->timeout);
        fwrite($fp, $domain . "\r\n");

        $response = '';
        while (!feof($fp)) {
            $response .= fgets($fp, 4096);
        }
        fclose($fp);

        return $response ?: false;
    }

    /**
     * Domain müsaitlik kontrolü
     */
    private function checkAvailability(string $raw, string $pattern, string $tld): bool {
        $raw_lower = strtolower($raw);

        $registered_patterns = [
            'registrar:', 'registrant:', 'creation date:', 'created:',
            'domain name:', 'registry domain id:',
        ];
        foreach ($registered_patterns as $rp) {
            if (str_contains($raw_lower, $rp)) return false;
        }

        $available_patterns = [
            $pattern, 'no match for', 'no match', 'not found',
            'no entries found', 'no match found', 'domain not found',
            'object does not exist', 'the queried object does not exist',
            'status: free', 'is available', 'no data found',
            'available for registration', 'no object found',
        ];
        foreach ($available_patterns as $p) {
            if ($p && str_contains($raw_lower, strtolower($p))) return true;
        }

        return false;
    }

    /**
     * Ham WHOIS verisini ayrıştır
     */
    private function parseWhoisData(string $raw): array {
        $data = [
            'registrar'     => null,
            'registrant'    => null,
            'creation_date' => null,
            'expiry_date'   => null,
            'name_servers'  => [],
            'status'        => [],
            'updated_date'  => null,
        ];

        $lines = explode("\n", $raw);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '%') || str_starts_with($line, '#')) continue;

            $parts = explode(':', $line, 2);
            if (count($parts) < 2) continue;

            $key   = strtolower(trim($parts[0]));
            $value = trim($parts[1]);
            if (!$value) continue;

            switch ($key) {
                case 'registrar':
                case 'sponsoring registrar':
                    if (!$data['registrar']) $data['registrar'] = $value; break;
                case 'registrant':
                case 'registrant name':
                case 'registrant organization':
                case 'org':
                    if (!$data['registrant']) $data['registrant'] = $value; break;
                case 'creation date':
                case 'created':
                case 'created date':
                case 'domain registration date':
                case 'registered':
                    if (!$data['creation_date']) $data['creation_date'] = $value; break;
                case 'registry expiry date':
                case 'expiry date':
                case 'expiration date':
                case 'expires':
                case 'paid-till':
                case 'domain expiration date':
                case 'expire-date':
                    if (!$data['expiry_date']) $data['expiry_date'] = $value; break;
                case 'updated date':
                case 'last modified':
                case 'changed':
                case 'last-changed':
                    if (!$data['updated_date']) $data['updated_date'] = $value; break;
                case 'name server':
                case 'nserver':
                case 'nameserver':
                    $ns = strtolower(explode(' ', $value)[0]);
                    if ($ns && !in_array($ns, $data['name_servers'])) $data['name_servers'][] = $ns;
                    break;
                case 'domain status':
                case 'status':
                    $st = explode(' ', $value)[0];
                    if ($st && !in_array($st, $data['status'])) $data['status'][] = $st;
                    break;
            }
        }
        return $data;
    }

    public function getAllTLDs(): array {
        return array_keys($this->servers);
    }

    public static function getPopularTLDs(): array {
        return [
            'Türkiye'  => ['.com.tr', '.net.tr', '.org.tr', '.web.tr', '.gen.tr', '.biz.tr', '.info.tr', '.tel.tr', '.tv.tr', '.name.tr'],
            'Genel'    => ['.com', '.net', '.org', '.info', '.biz', '.co', '.io', '.me', '.xyz', '.online'],
            'Teknoloji'=> ['.app', '.dev', '.tech', '.cloud', '.digital', '.software', '.network', '.systems'],
            'İş'       => ['.company', '.business', '.agency', '.studio', '.solutions', '.services', '.consulting'],
            'Avrupa'   => ['.eu', '.de', '.fr', '.it', '.es', '.nl', '.uk', '.co.uk', '.se', '.pl'],
        ];
    }
}
