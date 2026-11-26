# Jalankan sebagai Administrator (klik kanan file ini > Run with PowerShell > Run as Administrator)
# Setup akses HP ke aplikasi gudang (WSL2 + Docker) lewat LAN.
$ErrorActionPreference = 'Continue'

# auto-detect IP LAN (192.168.x / 10.x)
$ip = (Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.IPAddress -like '192.168.*' -or $_.IPAddress -like '10.*' } | Select-Object -First 1).IPAddress
if (-not $ip) { $ip = '127.0.0.1'; Write-Host 'IP LAN tidak ketemu, pakai localhost.'; }

# bersihkan rule lama kalau ada (biar tidak error dobel)
netsh interface portproxy delete v4tov4 listenaddress=0.0.0.0 listenport=8080 2>$null | Out-Null
netsh advfirewall firewall delete rule name="Gudang8080" 2>$null | Out-Null

# pasang portproxy (0.0.0.0:8080 -> localhost:80) + firewall
netsh interface portproxy add v4tov4 listenaddress=0.0.0.0 listenport=8080 connectaddress=127.0.0.1 connectport=80 | Out-Null
netsh advfirewall firewall add rule name="Gudang8080" dir=in action=allow protocol=TCP localport=8080 | Out-Null

Write-Host ""
Write-Host "Setup selesai."
Write-Host "Buka di HP (WiFi yang sama): http://$ip:8080"
Write-Host ""
Read-Host "Tekan Enter untuk menutup"