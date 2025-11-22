# Ky skript PowerShell shkarkon disa lista publike të IP-ve VPN dhe i bashkon në një file të vetëm vpn_blocklist.txt

# Lista e URL-ve të listave publike të IP-ve VPN
$urls = @(
    'https://raw.githubusercontent.com/X4BNet/lists_vpn/main/output/datacenter/ipv4.txt',
    'https://raw.githubusercontent.com/X4BNet/lists_vpn/main/output/vpn/ipv4.txt',
    'https://raw.githubusercontent.com/X4BNet/lists_vpn/main/output/webproxy/ipv4.txt',
    'https://raw.githubusercontent.com/X4BNet/lists_vpn/main/output/tor/ipv4.txt'
)

$output = "vpn_blocklist.txt"

# Fshi file-in ekzistues nëse ekziston
if (Test-Path $output) { Remove-Item $output }

foreach ($url in $urls) {
    try {
        Invoke-WebRequest -Uri $url -OutFile "temp_vpn.txt"
        Get-Content "temp_vpn.txt" | Where-Object { $_ -and ($_ -notmatch '^#') } | Add-Content $output
        Remove-Item "temp_vpn.txt"
    } catch {
        Write-Host "Nuk u shkarkua: $url"
    }
}

# Hiq IP-të e përsëritura
Get-Content $output | Sort-Object -Unique | Set-Content $output

Write-Host "Lista e IP-ve VPN u përditësua me sukses në $output."
