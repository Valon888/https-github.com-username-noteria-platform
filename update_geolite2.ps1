# Ky skript PowerShell shkarkon versionin më të fundit të GeoLite2-Country.mmdb nga MaxMind
# Duhet të keni një llogari falas në MaxMind dhe të përdorni licencë personale

$licenseKey = "VENDOS_KETU_LICENSE_KEY_TENDE"  # Ndryshoje me licencën tënde personale
$outputPath = "geoip/GeoLite2-Country.mmdb"

$url = "https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-Country&license_key=$licenseKey&suffix=mmdb"

Invoke-WebRequest -Uri $url -OutFile "GeoLite2-Country.tar.gz"

# Shpaketo file-in .tar.gz
if (Test-Path "GeoLite2-Country.tar.gz") {
    tar -xzf "GeoLite2-Country.tar.gz"
    $folder = Get-ChildItem -Directory | Where-Object { $_.Name -like "GeoLite2-Country_*" } | Select-Object -First 1
    if ($folder) {
        Copy-Item "$($folder.FullName)\GeoLite2-Country.mmdb" $outputPath -Force
        Remove-Item $folder.FullName -Recurse -Force
    }
    Remove-Item "GeoLite2-Country.tar.gz"
}

Write-Host "GeoLite2-Country.mmdb u përditësua me sukses."
