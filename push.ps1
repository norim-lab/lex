# Einfaches Skript zum Pushen auf GitHub
# Führen Sie dies aus, wenn Sie Änderungen gemacht haben.

Write-Host "Starte Git Push..." -ForegroundColor Cyan

# Status prüfen
git status

# Alles hinzufügen
git add .

# Commit mit Zeitstempel
$timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
git commit -m "Auto-update: $timestamp"

# Push zu GitHub
git push

if ($?) {
    Write-Host "✅ Erfolgreich zu GitHub gepusht!" -ForegroundColor Green
    
    # Optional: Webhook manuell triggern (falls GitHub-Webhook klemmt)
    # try {
    #     Invoke-RestMethod -Uri "https://lex.zeitblytz.media/deploy.php" -Method Post -Body "manual_trigger=1"
    #     Write-Host "✅ Webhook aufgerufen (Server sollte aktualisieren)" -ForegroundColor Green
    # } catch {
    #     Write-Host "⚠️ Webhook konnte nicht direkt aufgerufen werden (Server-Config prüfen)" -ForegroundColor Yellow
    # }
} else {
    Write-Host "❌ Fehler beim Push!" -ForegroundColor Red
}

Write-Host "Drücken Sie eine Taste zum Beenden..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
