# Udhëzues për Task Scheduler: Pastrim Automatik i Rate Limiting Tables

1. Hap "Task Scheduler" në Windows.
2. Kliko "Create Task..."
3. Jep një emër, p.sh. "Noteria Rate Limit Cleanup"
4. Tek "Triggers" → "New..." → Zgjidh "Daily" ose "Repeat task every 1 hour".
5. Tek "Actions" → "New..."
   - Action: Start a program
   - Program/script: powershell.exe
   - Add arguments: -ExecutionPolicy Bypass -File "D:\Laragon\noteria\cleanup_rate_limit.ps1"
6. Tek "Conditions" dhe "Settings" lëri default ose sipas nevojës.
7. Ruaj taskun.

# Këshilla:
- Kontrollo që rruga e skriptit dhe e mysql.exe të jetë e saktë.
- Nëse përdor fjalëkalim për MySQL, vendose te $dbPass në skript.
- Logu i pastrimit do të ruhet në "cleanup_rate_limit.log" në të njëjtën folder.
- Mund të kontrollosh logun për të parë kur është ekzekutuar pastrimi.

# Shembull argumentesh:
-ExecutionPolicy Bypass -File "D:\Laragon\noteria\cleanup_rate_limit.ps1"

# Për çdo problem, kontrollo logun ose error output të Task Scheduler.
