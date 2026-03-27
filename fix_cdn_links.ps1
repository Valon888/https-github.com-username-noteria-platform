# PowerShell script to fix CDN links with SRI hashes and referrerpolicy attributes
$files = Get-ChildItem -Path "d:\Laragon\www\noteria" -Filter "*.php" -File | Where-Object {
    Select-String -Path $_.FullName -Pattern "cdn\.jsdelivr\.net|cdnjs\.cloudflare\.com" -Quiet
}

$replacements = @(
    @{
        pattern = 'href="https://cdn\.jsdelivr\.net/npm/bootstrap@5\.3\.2/dist/css/bootstrap\.min\.css"[^>]*integrity="sha512-CbSNHeWAVDvWyXOU+Ad6NTQC1LNtKIIU5RpfxG2D7F8DxrfP2G+1ZNwh4CmPTc\+TDR4sNjTSSuP1WfySruWzQ==[^>]*'
        replacement = 'href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" integrity="sha512-b2QcS5SsA8tZodcDtGRELiGv5SaKSk1vDHDaQRda0htPYWZ6046lr3kJ5bAAQdpV2mmA/4v0wQF9MyU6/pDIAg==" crossorigin="anonymous" referrerpolicy="no-referrer" rel="stylesheet"'
    },
    @{
        pattern = 'src="https://cdn\.jsdelivr\.net/npm/bootstrap@5\.3\.2/dist/js/bootstrap\.bundle\.min\.js"[^>]*integrity="sha512-ysIxRyaC[^>]*'
        replacement = 'src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha512-X/YkDZyjTf4wyc2Vy16YGCPHwAY8rZJY+POgokZjQB2mhIRFJCckEGc6YyX9eNsPfn0PzThEuNs+uaomE5CO6A==" crossorigin="anonymous" referrerpolicy="no-referrer"'
    },
    @{
        pattern = 'href="https://cdnjs\.cloudflare\.com/ajax/libs/aos/2\.3\.4/aos\.css"[^>]*integrity="sha512-PeC08[^>]*'
        replacement = 'href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" integrity="sha512-1cK78a1o+ht2JcaW6g8OXYwqpev9+6GqOkz9xmBN9iUUhIndKtxwILGWYOSibOKjLsEdjyjZvYDq/cZwNeak0w==" crossorigin="anonymous" referrerpolicy="no-referrer"'
    },
    @{
        pattern = 'src="https://cdnjs\.cloudflare\.com/ajax/libs/aos/2\.3\.4/aos\.js"[^>]*integrity="sha512-2EQBEcI[^>]*'
        replacement = 'src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js" integrity="sha512-A7AYk1fGKX6S2SsHywmPkrnzTZHrgiVT7GcQkLGDe2ev0aWb8zejytzS8wjo7PGEXKqJOrjQ4oORtnimIRZBtw==" crossorigin="anonymous" referrerpolicy="no-referrer"'
    }
)

$filesUpdated = 0
$totalFiles = @($files).Count

foreach ($file in $files) {
    $content = Get-Content -Path $file -Raw
    $originalContent = $content
    
    foreach ($replacement in $replacements) {
        $content = $content -replace $replacement.pattern, $replacement.replacement
    }
    
    if ($content -ne $originalContent) {
        Set-Content -Path $file -Value $content
        $filesUpdated++
        Write-Host "Updated: $(Split-Path -Leaf $file)"
    }
}

Write-Host "Summary: Updated $filesUpdated out of $totalFiles files"
