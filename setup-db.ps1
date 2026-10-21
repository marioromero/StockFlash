$path = '.env'
$lines = Get-Content -Path $path
$newLines = @()
$found = @()

foreach ($line in $lines) {
    if ($line -match '^DB_CONNECTION=') { $newLines += 'DB_CONNECTION=mysql'; $found += 'DB_CONNECTION' }
    elseif ($line -match '^DB_HOST=') { $newLines += 'DB_HOST=tusitioexpress.cl'; $found += 'DB_HOST' }
    elseif ($line -match '^DB_PORT=') { $newLines += 'DB_PORT=3306'; $found += 'DB_PORT' }
    elseif ($line -match '^DB_DATABASE=') { $newLines += 'DB_DATABASE=grupoint_stockflash'; $found += 'DB_DATABASE' }
    elseif ($line -match '^DB_USERNAME=') { $newLines += 'DB_USERNAME=grupoint_stockflash'; $found += 'DB_USERNAME' }
    elseif ($line -match '^DB_PASSWORD=') { $newLines += 'DB_PASSWORD=esmerilemelo'; $found += 'DB_PASSWORD' }
    else { $newLines += $line }
}

# Agregar las que faltan
foreach ($key in @('DB_CONNECTION','DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD')) {
    if ($found -notcontains $key) {
        switch ($key) {
            'DB_CONNECTION' { $newLines += 'DB_CONNECTION=mysql' }
            'DB_HOST' { $newLines += 'DB_HOST=tusitioexpress.cl' }
            'DB_PORT' { $newLines += 'DB_PORT=3306' }
            'DB_DATABASE' { $newLines += 'DB_DATABASE=grupoint_stockflash' }
            'DB_USERNAME' { $newLines += 'DB_USERNAME=grupoint_stockflash' }
            'DB_PASSWORD' { $newLines += 'DB_PASSWORD=esmerilemelo' }
        }
    }
}

Set-Content -Path $path -Value $newLines
$r1 = & php artisan migrate 2>&1
if ($r1 -match 'Access denied|SQLSTATE\[HY000\]\[1045\]|authentication failed|could not find driver|SQLSTATE\[HY000\]\[2002\]') {
    $newLines2 = @()
    foreach ($line in $lines) {
        if ($line -match '^DB_CONNECTION=') { $newLines2 += 'DB_CONNECTION=mysql' }
        elseif ($line -match '^DB_HOST=') { $newLines2 += 'DB_HOST=tusitioexpress.cl' }
        elseif ($line -match '^DB_PORT=') { $newLines2 += 'DB_PORT=3306' }
        elseif ($line -match '^DB_DATABASE=') { $newLines2 += 'DB_DATABASE=grupoint_stockflash' }
        elseif ($line -match '^DB_USERNAME=') { $newLines2 += 'DB_USERNAME=grupoint_stockflash' }
        elseif ($line -match '^DB_PASSWORD=') { $newLines2 += 'DB_PASSWORD=esmerilemelo7' }
        else { $newLines2 += $line }
    }
    foreach ($key in @('DB_CONNECTION','DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD')) {
        if ($found -notcontains $key) {
            switch ($key) {
                'DB_CONNECTION' { $newLines2 += 'DB_CONNECTION=mysql' }
                'DB_HOST' { $newLines2 += 'DB_HOST=tusitioexpress.cl' }
                'DB_PORT' { $newLines2 += 'DB_PORT=3306' }
                'DB_DATABASE' { $newLines2 += 'DB_DATABASE=grupoint_stockflash' }
                'DB_USERNAME' { $newLines2 += 'DB_USERNAME=grupoint_stockflash' }
                'DB_PASSWORD' { $newLines2 += 'DB_PASSWORD=esmerilemelo7' }
            }
        }
    }
    Set-Content -Path $path -Value $newLines2
    $r2 = & php artisan migrate 2>&1
    $r2 | Select-Object -Last 20
} else {
    $r1 | Select-Object -Last 20
}
