<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Bllokim IP sipas vendit dhe VPN për Laravel Middleware
        $userIp = $request->ip();
        // Debug: Log IP për çdo kërkesë dhe shfaq në faqe për testim
        $logPath = base_path('blocked_attempts.log');
        $now = date('Y-m-d H:i:s');
        file_put_contents($logPath, "$now | DEBUG | IP: $userIp\n", FILE_APPEND);
        if (app()->environment('local')) {
            echo '<div style="position:fixed;top:0;left:0;z-index:9999;background:#ffe;padding:8px 16px;border-bottom:1px solid #ccc;">IP e detektuar nga Laravel: <b>' . htmlspecialchars($userIp) . '</b></div>';
        }

        // Blloko çdo IP që është në vpn_blocklist.txt
        $vpnListPath = base_path('vpn_blocklist.txt');
        if (file_exists($vpnListPath)) {
            $vpnList = file($vpnListPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (in_array($userIp, $vpnList)) {
                $logMsg = "$now | IP: $userIp | Reason: VPN Block (ANY COUNTRY)\n";
                file_put_contents($logPath, $logMsg, FILE_APPEND);
                return response()->view('errors.blocked', [
                    'reason' => 'vpn',
                    'ip' => $userIp
                ], 403);
            }
        }

        // Vazhdo me kontrollin e vendit si më parë
        $blockedCountries = [
            'RS','RU','CN','ES','GR','SK','RO','CY','IN','BR','AR','CU','VE','IR','IQ','SY','ZA','DZ','EG'
        ];
        $mmdbPath = base_path('geoip/GeoLite2-Country.mmdb');
        if (file_exists($mmdbPath)) {
            try {
                $reader = new \GeoIp2\Database\Reader($mmdbPath);
                $record = $reader->country($userIp);
                $countryCode = $record->country->isoCode;
                if (in_array($countryCode, $blockedCountries)) {
                    $logMsg = "$now | IP: $userIp | Reason: Country Block | Country: $countryCode\n";
                    file_put_contents($logPath, $logMsg, FILE_APPEND);
                    return response()->view('errors.blocked', [
                        'reason' => 'country',
                        'ip' => $userIp
                    ], 403);
                }
            } catch (\Exception $e) {
                // Nëse ndodh ndonjë gabim, lejo aksesin
            }
        }
        $uri = $request->getRequestUri();
        if (stripos($uri, 'dashboard.php') !== false) {
            echo "<div class='dev-info'>Akses i plotë në dashboard.php i lejuar.</div>";
            echo "</div></div>";
            return response('Akses i plotë në dashboard.php i lejuar.', 200);
        }
        return $next($request);
    }
}
