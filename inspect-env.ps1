$path = '.env'
$lines = Get-Content -Path $path
foreach ($line in $lines) {
    if ($line -match '^DB_') {
        $parts = $line -split '=', 2
        $key = $parts[0]
        $val = if ($parts.Count -gt 1) { $parts[1] } else { '' }
        $masked = if ($val -eq '') { '' } else { '***' }
        Write-Output "$key=$masked"
    }
}
