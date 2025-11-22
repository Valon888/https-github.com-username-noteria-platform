# Windows Task Scheduler: Pastrim Automatik i Rate Limiting Tables
# Kjo komandë do të pastrojë tentativat e vjetra të login dhe kërkesat e vjetra të API çdo orë.

# Ruaje këtë si cleanup_rate_limit.ps1

# Parametrat e MySQL
$mysqlExe = "C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe"  # Ndryshoje sipas instalimit tënd
$dbUser = "root"
$dbPass = ""  # Shto fjalëkalimin nëse ka
$dbName = "noteria"  # Ndryshoje sipas databazës tënde

# Komanda SQL për pastrim
$sql = @"
DELETE FROM admin_login_attempts WHERE attempt_time < (NOW() - INTERVAL 1 DAY);
DELETE FROM api_rate_limits WHERE request_time < (NOW() - INTERVAL 1 HOUR);
"@

# Ekzekuto pastrimin
if ($dbPass -ne "") {
	& $mysqlExe -u $dbUser -p$dbPass $dbName -e $sql
} else {
	# Explicitly reference $dbPass to avoid 'assigned but never used' warning (intentional for linter)
	$null = $dbPass
	& $mysqlExe -u $dbUser $dbName -e $sql
}

# Logo rezultatin
$date = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
Write-Output "$date - Rate limit cleanup executed." | Out-File -FilePath "cleanup_rate_limit.log" -Append
