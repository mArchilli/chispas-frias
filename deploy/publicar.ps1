<#
.SYNOPSIS
    Publica Chispas Frías en DonWeb/Ferozo (sin SSH): verifica git, compila, arma un paquete .zip, lo sube por FTP con WinSCP y espera el resultado del runner.

.DESCRIPTION
    Corre desde cualquier carpeta. No hace commits ni push. Pasos:
      1. El árbol de git tiene que estar limpio y HEAD == origin/main (hace `git fetch` antes).
      2. `npm run build`.
      3. vendor/: git archive de HEAD en una carpeta temporal limpia, comprueba que el composer.lock sirve para PHP 8.2.35 y corre
         `composer install --no-dev --prefer-dist --no-scripts --optimize-autoloader` con el lock tal cual (en el hosting no hay composer).
      4. paquete.zip = laravel/ (código + vendor) + public_html/ (build y estáticos nuevos) + manifest.json (commit, lista de archivos, sha256).
         NUNCA lleva .env, storage/, las subidas del admin (public_html/images/products, docs/…), public_html/index.php, cgi-bin ni .htaccess.
      5. Sube por FTP (WinSCP) a ~/deploy-cf/ (fuera de public_html): runner.php, paquete.zip y, al final, disparo.json
         (modo ensayo|deploy, commit, sha256 del zip, nonce). El cron de Ferozo corre el runner (cada 5 min como mínimo).
      6. Espera leyendo estado.json y runner.log por FTP, muestra el log en vivo y cierra con un resumen.

    Credenciales: C:\Users\MatiasPC\.rukoz-deploy\chispasfrias.env con FTP_HOST, FTP_USER, FTP_PASS (opcionales: FTP_MODO=ftp|ftps,
    FTP_PUERTO, FTP_FINGERPRINT). Este script nunca las imprime.

.PARAMETER Ensayo
    El runner hace todas las verificaciones (CLI, paquete, hashes, base, espacio, arranque del Laravel nuevo en una carpeta temporal)
    SIN tocar el sitio. Hacelo siempre antes del deploy real.

.PARAMETER SoloLocal
    Hace los pasos 1 a 4 (git, build, vendor, paquete) y se detiene antes de conectarse al servidor.

.PARAMETER SoloRunner
    Sube sólo runner.php a ~/deploy-cf/ (para poder crear la tarea del cron en Ferozo) y verifica el archivo. No sube paquete ni disparo.

.PARAMETER Estado
    Sólo lee estado.json y las últimas líneas de runner.log del servidor.

.PARAMETER LeerHtaccess
    Baja (sólo lectura) public_html/.htaccess, public_html/index.php y el listado de public_html a una carpeta temporal, y los compara con el repo.

.PARAMETER AplicarHtaccess
    En el deploy real, el runner agrega al .htaccess de public_html los bloques de seguridad S.2 (si el servidor los rechaza con un 500,
    vuelve solo al .htaccess anterior). Por defecto NO se toca el .htaccess.

.PARAMETER TodosLosEstaticos
    Incluye en el paquete todos los estáticos de public/ (imágenes, videos). Por defecto sólo los que cambiaron desde el tag de producción.

.EXAMPLE
    powershell -NoProfile -ExecutionPolicy Bypass -File .\deploy\publicar.ps1 -SoloLocal
    powershell -NoProfile -ExecutionPolicy Bypass -File .\deploy\publicar.ps1 -SoloRunner
    powershell -NoProfile -ExecutionPolicy Bypass -File .\deploy\publicar.ps1 -Ensayo
    powershell -NoProfile -ExecutionPolicy Bypass -File .\deploy\publicar.ps1
#>
[CmdletBinding()]
param(
    [switch]$Ensayo,
    [switch]$SoloLocal,
    [switch]$SoloRunner,
    [switch]$Estado,
    [switch]$LeerHtaccess,
    [switch]$AplicarHtaccess,
    [switch]$TodosLosEstaticos,
    [switch]$Reconstruir,
    [int]$TimeoutMin = 0,
    [string]$EnvFile = (Join-Path $env:USERPROFILE '.rukoz-deploy\chispasfrias.env'),
    [string]$BaseProduccion = 'produccion-pre-integracion',
    # Sólo para pruebas locales (con -SoloLocal): permiten armar el paquete con el árbol sucio / reusar public\build / elegir carpetas.
    [switch]$PermitirArbolSucio,
    [switch]$ReusarBuild,
    [string]$Cache = '',
    [string]$Trabajo = ''
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version 2

# --- Configuración ---------------------------------------------------------------------------------------------------------------------
$repo       = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$rama       = 'main'
$runnerSrc  = Join-Path $repo 'deploy\runner.php'
$remotoDir  = 'deploy-cf'
$phpServidor = '8.2.35'
$herramientas = Join-Path $env:LOCALAPPDATA 'chispasfrias-herramientas'
if ($Cache -eq '')   { $Cache   = Join-Path $env:LOCALAPPDATA 'chispasfrias-deploy' }
if ($Trabajo -eq '') { $Trabajo = Join-Path $env:TEMP 'cf-dep' }
$ftpTmp     = Join-Path $env:TEMP 'chispasfrias-ftp'

# Lo que del repo NUNCA viaja en laravel/ (el .env y storage/ del servidor se conservan; public/ se maneja aparte).
$excluirRaiz = @('.git', '.gitattributes', '.gitignore', '.github', '.claude', '.editorconfig', '.styleci.yml', '.phpunit.result.cache', '.phpunit.cache', 'docs', 'tests', 'node_modules', 'public', 'storage', 'deploy', 'scripts', 'README.md', 'CHANGELOG.md', 'contextoproyecto.md', 'package.json', 'package-lock.json', 'phpunit.xml', 'vite.config.js', 'postcss.config.js', 'tailwind.config.js', 'jsconfig.json')
# Lo que de public/ nunca va a public_html: lo maneja el hosting o lo suben los admins.
$noEstaticos = '^(build/|\.htaccess$|robots\.txt$|index\.php$|hot$|images/products/|docs/|cgi-bin/)'
$extEstaticos = '\.(png|jpe?g|gif|webp|svg|ico|mp4|webm|css|js|map|json|txt|woff2?|ttf|eot)$'

# --- Presentación ----------------------------------------------------------------------------------------------------------------------
$resumen = New-Object System.Collections.Generic.List[string]
$script:t0 = Get-Date
$script:paso = 'inicio'

function Paso([string]$texto) {
    Cerrar-Paso
    $script:paso = $texto
    $script:t0 = Get-Date
    Write-Host ''
    Write-Host "==> $texto" -ForegroundColor Cyan
}
function Cerrar-Paso {
    if ($script:paso -ne 'inicio') {
        $seg = [int]((Get-Date) - $script:t0).TotalSeconds
        $resumen.Add(('{0,-62} OK  {1,4}s' -f $script:paso, $seg))
    }
}
function Ok([string]$texto)     { Write-Host "[ok] $texto" -ForegroundColor Green }
function Aviso([string]$texto)  { Write-Host "[aviso] $texto" -ForegroundColor Yellow }
function Frenar([string]$texto) { throw $texto }

function Nativo([scriptblock]$bloque) {   # PS 5.1: con ErrorActionPreference=Stop cualquier línea por stderr de un exe corta el script; los exe se controlan por código de salida
    $previo = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try { & $bloque } finally { $ErrorActionPreference = $previo }
}
function Ejecutar-Nativo([string]$que, [scriptblock]$bloque) {
    Nativo $bloque | Out-Host
    if ($LASTEXITCODE -ne 0) { Frenar "$que falló (código $LASTEXITCODE)." }
}
function Prop($obj, [string]$nombre, $defecto = $null) {   # acceso seguro con StrictMode a una propiedad de un objeto de JSON
    if ($null -eq $obj) { return $defecto }
    if ($obj.PSObject.Properties[$nombre]) { return $obj.$nombre }
    return $defecto
}
function Sha256Hex([byte[]]$bytes) {
    $h = [System.Security.Cryptography.SHA256]::Create()
    try { return ([System.BitConverter]::ToString($h.ComputeHash($bytes)).Replace('-', '').ToLower()) } finally { $h.Dispose() }
}
function RutaLarga([string]$ruta) {   # rutas largas de Windows (vendor/ tiene rutas de más de 260 caracteres si la carpeta de trabajo es larga)
    $prefijo = '\\?\'
    if ($ruta.Length -ge 240 -and -not $ruta.StartsWith($prefijo)) { return $prefijo + $ruta }
    return $ruta
}
function Sha256Archivo([string]$ruta) { return (Get-FileHash -LiteralPath $ruta -Algorithm SHA256).Hash.ToLower() }
function JsonStr([string]$s) {
    $sb = New-Object System.Text.StringBuilder
    [void]$sb.Append('"')
    foreach ($c in $s.ToCharArray()) {
        switch ($c) {
            '"'  { [void]$sb.Append('\"') }
            '\'  { [void]$sb.Append('\\') }
            "`n" { [void]$sb.Append('\n') }
            "`r" { [void]$sb.Append('\r') }
            "`t" { [void]$sb.Append('\t') }
            default { if ([int]$c -lt 32) { [void]$sb.Append(('\u{0:x4}' -f [int]$c)) } else { [void]$sb.Append($c) } }
        }
    }
    [void]$sb.Append('"')
    return $sb.ToString()
}
function JsonLista($items) { return '[' + ((@($items) | ForEach-Object { JsonStr ([string]$_) }) -join ',') + ']' }
function Escribir-Utf8([string]$ruta, [string]$texto) { [System.IO.File]::WriteAllText($ruta, $texto, (New-Object System.Text.UTF8Encoding($false))) }
function Leer-Utf8([string]$ruta) { return [System.IO.File]::ReadAllText($ruta, (New-Object System.Text.UTF8Encoding($false))) }

# --- Archivo de credenciales -----------------------------------------------------------------------------------------------------------
function Leer-EnvFtp([string]$ruta) {
    if (-not (Test-Path -LiteralPath $ruta)) { Frenar "No existe $ruta (lo crea Matias con FTP_HOST, FTP_USER y FTP_PASS)." }
    $cfg = @{}
    foreach ($linea in (Get-Content -LiteralPath $ruta -Encoding UTF8)) {
        $l = $linea.Trim()
        if ($l -eq '' -or $l.StartsWith('#') -or -not $l.Contains('=')) { continue }
        $i = $l.IndexOf('=')
        $v = $l.Substring($i + 1).Trim()
        if ($v.Length -ge 2 -and (($v.StartsWith('"') -and $v.EndsWith('"')) -or ($v.StartsWith("'") -and $v.EndsWith("'")))) { $v = $v.Substring(1, $v.Length - 2) }
        $cfg[$l.Substring(0, $i).Trim()] = $v
    }
    foreach ($k in 'FTP_HOST', 'FTP_USER', 'FTP_PASS') { if (-not $cfg.ContainsKey($k) -or $cfg[$k] -eq '') { Frenar "Falta $k en $ruta." } }
    # FTP_HOST puede venir como «ftp://servidor», «servidor/» o «servidor:21»: se deja sólo el nombre y, si trae puerto, se pasa a FTP_PUERTO.
    $m = [regex]::Match($cfg['FTP_HOST'].Trim(), '^(?:[A-Za-z][A-Za-z0-9+.-]*://)?([^/:\s]+)(?::(\d+))?/*$')
    if (-not $m.Success) { Frenar 'FTP_HOST no tiene un formato válido (se espera un nombre de servidor, por ejemplo ftp.midominio.com).' }
    $cfg['FTP_HOST'] = $m.Groups[1].Value
    if ($m.Groups[2].Success -and -not $cfg.ContainsKey('FTP_PUERTO')) { $cfg['FTP_PUERTO'] = $m.Groups[2].Value }
    return $cfg
}

# --- WinSCP ----------------------------------------------------------------------------------------------------------------------------
function Buscar-WinScp {
    $c = Get-Command 'winscp.com' -ErrorAction SilentlyContinue
    if ($c) { return $c.Source }
    $candidatos = @(
        (Join-Path ${env:ProgramFiles(x86)} 'WinSCP\WinSCP.com'),
        (Join-Path $env:ProgramFiles 'WinSCP\WinSCP.com'),
        (Join-Path $env:LOCALAPPDATA 'Programs\WinSCP\WinSCP.com'),
        (Join-Path $herramientas 'winscp\WinSCP.com')
    )
    foreach ($p in $candidatos) { if ($p -and (Test-Path -LiteralPath $p)) { return $p } }
    return $null
}
function Asegurar-WinScp {
    $w = Buscar-WinScp
    if ($w) { return $w }
    Aviso 'WinSCP no está instalado: lo instalo con winget (usuario actual, sin tocar nada más).'
    if (-not (Get-Command winget -ErrorAction SilentlyContinue)) {
        Frenar "No hay winget. Bajá WinSCP portable de https://winscp.net/eng/download.php y descomprimilo en $herramientas\winscp (tiene que quedar WinSCP.com ahí)."
    }
    Ejecutar-Nativo 'winget install WinSCP' { winget install --id WinSCP.WinSCP -e --silent --accept-package-agreements --accept-source-agreements }
    $w = Buscar-WinScp
    if (-not $w) { Frenar "winget terminó pero no encuentro WinSCP.com. Instalalo a mano o dejá la versión portable en $herramientas\winscp." }
    return $w
}

$script:cfg = $null
$script:winscp = $null

function Url-Ftp {
    $modo = 'ftp'
    if ($script:cfg.ContainsKey('FTP_MODO')) { $modo = $script:cfg['FTP_MODO'].ToLower() }
    $esquema = 'ftp'
    if ($modo -eq 'ftps' -or $modo -eq 'ftpes') { $esquema = 'ftpes' }
    elseif ($modo -eq 'ftps-implicito') { $esquema = 'ftps' }
    elseif ($modo -ne 'ftp') { Frenar "FTP_MODO desconocido: $modo (ftp | ftps | ftps-implicito)." }
    $puerto = ''
    if ($script:cfg.ContainsKey('FTP_PUERTO') -and $script:cfg['FTP_PUERTO'] -ne '') { $puerto = ':' + $script:cfg['FTP_PUERTO'] }
    $u = [System.Uri]::EscapeDataString($script:cfg['FTP_USER'])
    $p = [System.Uri]::EscapeDataString($script:cfg['FTP_PASS'])
    return "$esquema`://${u}:${p}@$($script:cfg['FTP_HOST'])$puerto/"
}
function Opcion-Cert {
    $modo = 'ftp'
    if ($script:cfg.ContainsKey('FTP_MODO')) { $modo = $script:cfg['FTP_MODO'].ToLower() }
    if ($modo -eq 'ftp') { return '' }
    if ($script:cfg.ContainsKey('FTP_FINGERPRINT') -and $script:cfg['FTP_FINGERPRINT'] -ne '') { return ' -certificate="' + $script:cfg['FTP_FINGERPRINT'] + '"' }
    return ''   # sin huella fijada: WinSCP valida el certificado contra la cadena de CA de Windows y el nombre del servidor (en modo batch, si no es válido, no conecta)
}

# Corre un script de WinSCP (lista de comandos); devuelve @{ Codigo; Salida }. El script lleva la clave: se escribe en un temporal y se borra siempre.
function Invoke-WinScp([string[]]$comandos, [switch]$Tolerante) {
    if (-not (Test-Path $ftpTmp)) { New-Item -ItemType Directory $ftpTmp | Out-Null }
    $sf = Join-Path $ftpTmp ('script-' + [guid]::NewGuid().ToString('N') + '.txt')
    $modoBatch = 'abort'
    if ($Tolerante) { $modoBatch = 'continue' }
    $cabecera = @(('option batch ' + $modoBatch), 'option confirm off', ('open ' + (Url-Ftp) + (Opcion-Cert)), 'option transfer binary')
    $lineas = $cabecera + $comandos + @('exit')
    try {
        [System.IO.File]::WriteAllLines($sf, $lineas, (New-Object System.Text.UTF8Encoding($false)))
        $salida = @(Nativo { & $script:winscp "/script=$sf" '/ini=nul' '/nointeractiveinput' 2>&1 })
        $codigo = $LASTEXITCODE
    }
    finally { Remove-Item -LiteralPath $sf -Force -ErrorAction SilentlyContinue }
    $texto = ($salida | ForEach-Object { "$_" }) -join "`n"
    $texto = $texto.Replace($script:cfg['FTP_PASS'], '***')
    return @{ Codigo = $codigo; Salida = $texto }
}

function Probar-Conexion {
    $r = Invoke-WinScp @('ls .')
    if ($r.Codigo -ne 0) { Frenar ("No pude conectarme por FTP (código $($r.Codigo)). Salida de WinSCP:`n" + $r.Salida) }
    foreach ($esperado in 'public_html', 'laravel') {
        if ($r.Salida -notmatch "(?m)\s$esperado\s*$") {
            Frenar "El directorio de login del FTP no contiene '$esperado': no estoy en /home/c2731764. No subo nada. (Si el FTP entra a otra carpeta, hay que ajustar las rutas.)"
        }
    }
    Ok 'Conexión FTP OK; el directorio de login tiene laravel/ y public_html/'
}

function Bajar([string]$remoto, [string]$localDir) {
    if (-not (Test-Path $localDir)) { New-Item -ItemType Directory $localDir | Out-Null }
    $r = Invoke-WinScp @("get `"$remoto`" `"$localDir\`"") -Tolerante
    return $r
}

# --- Programa --------------------------------------------------------------------------------------------------------------------------
$codigo = 0
$modo = 'DEPLOY REAL'
if ($Ensayo) { $modo = 'ENSAYO (no toca el sitio)' }
elseif ($SoloLocal) { $modo = 'SOLO LOCAL (no se conecta al servidor)' }
elseif ($SoloRunner) { $modo = 'SOLO RUNNER (sube runner.php)' }
elseif ($Estado) { $modo = 'ESTADO (lectura)' }
elseif ($LeerHtaccess) { $modo = 'LEER .htaccess (lectura)' }
$script:resultado = $null
$infoPaquete = $null

function Estado-Remoto {
    $dir = Join-Path $ftpTmp 'estado'
    Remove-Item -LiteralPath (Join-Path $dir 'estado.json') -Force -ErrorAction SilentlyContinue
    Remove-Item -LiteralPath (Join-Path $dir 'runner.log') -Force -ErrorAction SilentlyContinue
    [void](Bajar "$remotoDir/estado.json" $dir)
    [void](Bajar "$remotoDir/runner.log" $dir)
    $est = $null
    $log = ''
    $ej = Join-Path $dir 'estado.json'
    if (Test-Path -LiteralPath $ej) { try { $est = (Leer-Utf8 $ej) | ConvertFrom-Json } catch { $est = $null } }
    $lj = Join-Path $dir 'runner.log'
    if (Test-Path -LiteralPath $lj) { $log = Leer-Utf8 $lj }
    return @{ Estado = $est; Log = $log }
}

function Construir-Paquete {
    # Devuelve @{ Zip; Info } (en la caché). Si ya existe el paquete de este commit, lo reusa (salvo -Reconstruir).
    Push-Location $repo
    try {
        Paso '1 - Git: árbol limpio y HEAD == origin/main'
        Ejecutar-Nativo 'git fetch' { git fetch origin }
        $actual = (git rev-parse --abbrev-ref HEAD).Trim()
        if ($actual -ne $rama -and -not $PermitirArbolSucio) { Frenar "Estás en la rama '$actual' y se despliega '$rama'. Hacé: git switch $rama" }
        $sucio = @(git status --porcelain)
        if ($sucio.Count -gt 0 -and -not $PermitirArbolSucio) {
            Write-Host ($sucio -join "`n") -ForegroundColor Yellow
            Frenar "El árbol de git NO está limpio ($($sucio.Count) cambio/s, arriba). Hacé el commit (y el push) a mano y volvé a correr."
        }
        $head   = (git rev-parse HEAD).Trim()
        $remoto = (git rev-parse "origin/$rama").Trim()
        if ($head -ne $remoto -and -not $PermitirArbolSucio) {
            $adelante = (git rev-list --count "origin/$rama..HEAD").Trim()
            $atras    = (git rev-list --count "HEAD..origin/$rama").Trim()
            Frenar "HEAD ($($head.Substring(0,7))) no es origin/$rama ($($remoto.Substring(0,7))): tenés $adelante commit/s sin subir y te faltan $atras. Hacé git push (o git pull) y volvé a correr."
        }
        if ($PermitirArbolSucio) { Aviso 'PermitirArbolSucio: sólo para pruebas locales; el paquete sale igual de HEAD.' }
        Nativo { git rev-parse --verify --quiet "$BaseProduccion^{commit}" } | Out-Null
        if ($LASTEXITCODE -ne 0) { Frenar "No encuentro el tag/commit de producción '$BaseProduccion' (-BaseProduccion)." }
        Ok "Árbol limpio y HEAD == origin/$rama == $($head.Substring(0,7))"

        $dirCache = Join-Path $Cache $head.Substring(0, 12)
        $zipCache = Join-Path $dirCache 'paquete.zip'
        $infoCache = Join-Path $dirCache 'paquete.info.json'
        $claveHtaccess = ''
        if ((Test-Path $zipCache) -and (Test-Path $infoCache) -and -not $Reconstruir) {
            $info = (Leer-Utf8 $infoCache) | ConvertFrom-Json
            if ((Prop $info 'commit') -eq $head -and (Prop $info 'zip_sha256') -eq (Sha256Archivo $zipCache) -and ((Prop $info 'todos_estaticos') -eq [bool]$TodosLosEstaticos)) {
                Paso '2 a 4 - Paquete ya construido para este commit (se reusa)'
                Ok "paquete.zip $([math]::Round((Get-Item $zipCache).Length / 1MB, 1)) MB, sha256 $((Prop $info 'zip_sha256').Substring(0, 16))… (usá -Reconstruir para rehacerlo)"
                return @{ Zip = $zipCache; Info = $info; Head = $head }
            }
        }

        # 2 --------------------------------------------------------------------------------------------------------------------------
        Paso '2 - npm run build'
        if ($ReusarBuild -and (Test-Path (Join-Path $repo 'public\build\manifest.json'))) {
            Aviso 'ReusarBuild: no se recompila (sólo pruebas locales).'
        }
        else {
            if (-not (Get-Command npm.cmd -ErrorAction SilentlyContinue)) { Frenar "No encuentro 'npm.cmd' en el PATH." }
            Ejecutar-Nativo 'npm run build' { npm.cmd run build }   # npm.cmd y no el shim npm.ps1: este no soporta Set-StrictMode
        }
        if (-not (Test-Path (Join-Path $repo 'public\build\manifest.json'))) { Frenar 'El build no generó public\build\manifest.json.' }
        if ((git rev-parse HEAD).Trim() -ne $head) { Frenar 'HEAD cambió mientras se compilaba.' }
        Ok "public\build compilado ($((Get-ChildItem (Join-Path $repo 'public\build\assets') -File).Count) archivos en assets)"

        # 3 --------------------------------------------------------------------------------------------------------------------------
        Paso '3 - vendor/ (composer install --no-dev, en local, con el composer.lock tal cual)'
        foreach ($cmd in 'git', 'composer') { if (-not (Get-Command $cmd -ErrorAction SilentlyContinue)) { Frenar "No encuentro '$cmd' en el PATH." } }
        Add-Type -AssemblyName System.IO.Compression
        Add-Type -AssemblyName System.IO.Compression.FileSystem
        if (Test-Path $Trabajo) { Remove-Item $Trabajo -Recurse -Force }
        New-Item -ItemType Directory $Trabajo | Out-Null
        $vsrc = Join-Path $Trabajo 'src.zip'; $vapp = Join-Path $Trabajo 'app'; $vchk = Join-Path $Trabajo 'plataforma'
        New-Item -ItemType Directory $vchk | Out-Null
        Ejecutar-Nativo 'git archive' { git archive --format=zip -o $vsrc HEAD }
        [System.IO.Compression.ZipFile]::ExtractToDirectory($vsrc, $vapp)
        Remove-Item $vsrc -Force
        $lockJson = (Leer-Utf8 (Join-Path $vapp 'composer.lock')) | ConvertFrom-Json
        $raizJson = (Leer-Utf8 (Join-Path $vapp 'composer.json')) | ConvertFrom-Json

        # (1) versión de PHP del hosting contra el lock: composer check-platform-reqs en una carpeta aparte (no se toca el composer.json del repo).
        $exigidas = New-Object System.Collections.Generic.HashSet[string]
        $requires = @($raizJson.require)
        foreach ($pkg in @($lockJson.packages)) { $r = Prop $pkg 'require'; if ($r) { $requires += $r } }
        foreach ($req in $requires) { foreach ($prop in $req.PSObject.Properties) { if ($prop.Name -like 'ext-*') { [void]$exigidas.Add($prop.Name.Substring(4).ToLower()) } } }
        $platform = [ordered]@{ php = $phpServidor }
        foreach ($e in $exigidas) { $platform["ext-$e"] = $phpServidor }
        $reqRaiz = [ordered]@{}
        foreach ($prop in $raizJson.require.PSObject.Properties) { $reqRaiz[$prop.Name] = $prop.Value }
        $cjMin = [ordered]@{ name = 'chispasfrias/plataforma'; require = $reqRaiz; config = [ordered]@{ platform = $platform } }
        Escribir-Utf8 (Join-Path $vchk 'composer.json') ($cjMin | ConvertTo-Json -Depth 6)
        Copy-Item (Join-Path $vapp 'composer.lock') (Join-Path $vchk 'composer.lock')
        Ejecutar-Nativo "composer check-platform-reqs (PHP $phpServidor)" { composer check-platform-reqs --lock --no-dev --no-interaction --working-dir=$vchk }
        Ok "composer.lock compatible con PHP $phpServidor (extensiones que exige: $(($exigidas | Sort-Object) -join ', '); el runner las comprueba contra la CLI del hosting en el ensayo)"

        Ejecutar-Nativo 'composer install' { composer install --no-dev --prefer-dist --no-interaction --no-scripts --optimize-autoloader --working-dir=$vapp }
        foreach ($necesario in 'vendor\autoload.php', 'vendor\composer\platform_check.php', 'vendor\composer\installed.json', 'vendor\rukoz\core-client\composer.json') {
            if (-not (Test-Path (Join-Path $vapp $necesario))) { Frenar "El vendor generado no trae $necesario." }
        }
        if (Test-Path (Join-Path $vapp 'vendor\phpunit')) { Frenar 'El vendor generado trae dependencias de desarrollo (phpunit).' }
        $instalado = (Leer-Utf8 (Join-Path $vapp 'vendor\composer\installed.json') | ConvertFrom-Json).packages | Where-Object { $_.name -eq 'rukoz/core-client' }
        if (-not $instalado) { Frenar 'rukoz/core-client no figura en vendor\composer\installed.json.' }
        if ((git rev-parse HEAD).Trim() -ne $head) { Frenar 'HEAD cambió mientras se armaba el vendor.' }
        Ok "vendor/ del commit $($head.Substring(0,7)) · rukoz/core-client $($instalado.version) · sin dependencias de desarrollo"

        # 4 --------------------------------------------------------------------------------------------------------------------------
        Paso '4 - paquete.zip + manifest.json'
        # laravel/: el código de HEAD sin lo que nunca viaja.
        foreach ($n in $excluirRaiz) {
            $p = Join-Path $vapp $n
            if (Test-Path -LiteralPath $p) { Remove-Item -LiteralPath $p -Recurse -Force }
        }
        foreach ($f in @(Get-ChildItem -LiteralPath $vapp -Force -File | Where-Object { $_.Name -like '.env*' })) { Remove-Item -LiteralPath $f.FullName -Force }
        foreach ($f in @(Get-ChildItem -LiteralPath (Join-Path $vapp 'database') -Filter '*.sqlite*' -File -ErrorAction SilentlyContinue)) { Remove-Item -LiteralPath $f.FullName -Force }
        $cacheBoot = Join-Path $vapp 'bootstrap\cache'
        if (Test-Path $cacheBoot) { Get-ChildItem -LiteralPath $cacheBoot -Force -File | Where-Object { $_.Name -ne '.gitignore' } | Remove-Item -Force }
        [System.IO.File]::WriteAllText((Join-Path $vapp 'BUILD_COMMIT'), $head)
        Ok ('laravel/ queda con: ' + ((Get-ChildItem -LiteralPath $vapp -Force | Select-Object -ExpandProperty Name | Sort-Object) -join ', '))

        $entradas = New-Object 'System.Collections.Generic.List[object]'   # @{ r = ruta en el zip; f = archivo local }
        $raizApp = (Resolve-Path $vapp).Path.TrimEnd('\')
        foreach ($f in Get-ChildItem -LiteralPath $vapp -Recurse -Force -File) {
            $rel = $f.FullName.Substring($raizApp.Length + 1).Replace('\', '/')
            $entradas.Add(@{ r = "laravel/$rel"; f = $f.FullName })
        }
        # public_html/: el build y los estáticos que corresponden.
        $raizBuild = (Resolve-Path (Join-Path $repo 'public\build')).Path.TrimEnd('\')
        foreach ($f in Get-ChildItem -LiteralPath $raizBuild -Recurse -File) {
            $rel = $f.FullName.Substring($raizBuild.Length + 1).Replace('\', '/')
            $entradas.Add(@{ r = "public_html/build/$rel"; f = $f.FullName })
        }
        $estaticos = @()
        if ($TodosLosEstaticos) { $lista = @(git ls-files public) }
        else { $lista = @(git diff --name-only --diff-filter=AM "$BaseProduccion" HEAD -- public) }
        foreach ($rutaGit in $lista) {
            if ($rutaGit -eq '') { continue }
            $rel = $rutaGit.Substring('public/'.Length)
            if ($rel -match $noEstaticos) { continue }
            if ($rel -notmatch $extEstaticos) { Aviso "Estático ignorado por su extensión: $rel"; continue }
            $estaticos += $rel
            $entradas.Add(@{ r = "public_html/$rel"; f = (Join-Path $repo ($rutaGit.Replace('/', '\'))) })
        }
        Ok "Estáticos de public/ que viajan: $($estaticos.Count)$(if ($estaticos.Count -eq 0) { ' (ninguno cambió desde ' + $BaseProduccion + ')' })"

        # Migraciones nuevas respecto de producción: las del repo + las del paquete.
        $migWeb = @(git diff --name-only --diff-filter=A "$BaseProduccion" HEAD -- database/migrations | Where-Object { $_ -ne '' } | ForEach-Object { [System.IO.Path]::GetFileNameWithoutExtension($_) })
        $migPaq = @(Get-ChildItem -LiteralPath (Join-Path $vapp 'vendor\rukoz\core-client\database\migrations') -Filter '*.php' -File -ErrorAction SilentlyContinue | ForEach-Object { [System.IO.Path]::GetFileNameWithoutExtension($_.Name) })
        $migraciones = @($migWeb + $migPaq | Sort-Object)
        # Extensiones que exige el vendor (lo que platform_check.php comprueba al cargar el autoload).
        $pc = Leer-Utf8 (Join-Path $vapp 'vendor\composer\platform_check.php')
        $extVendor = @(@([regex]::Matches($pc, "extension_loaded\('([^']+)'\)") | ForEach-Object { $_.Groups[1].Value }) + @($exigidas) | Sort-Object -Unique)
        # Interruptores booleanos de la integración (config/ y config del paquete): el deploy los deja en false.
        $flags = New-Object System.Collections.Generic.HashSet[string]
        foreach ($cf in @(Get-ChildItem -LiteralPath (Join-Path $vapp 'config') -Filter '*.php' -File) + @(Get-ChildItem -LiteralPath (Join-Path $vapp 'vendor\rukoz\core-client\config') -Filter '*.php' -File -ErrorAction SilentlyContinue)) {
            foreach ($m in [regex]::Matches((Leer-Utf8 $cf.FullName), "\(bool\)\s*env\('(RUKOZ_INTEGRACION|INTEGRACION_[A-Z0-9_]+)'")) { [void]$flags.Add($m.Groups[1].Value) }
        }
        # Bloques de seguridad S.2 del .htaccess del repo (se agregan al de public_html sólo con -AplicarHtaccess).
        $ht = Leer-Utf8 (Join-Path $repo 'public\.htaccess')
        $i1 = $ht.IndexOf('# --- Archivos que nunca se sirven'); $i2 = $ht.IndexOf('# --- Laravel')
        if ($i1 -lt 0 -or $i2 -le $i1) { Frenar 'No pude extraer los bloques de seguridad de public/.htaccess (cambiaron los títulos).' }
        $htS2 = $ht.Substring($i1, $i2 - $i1).TrimEnd() + "`n"

        # Orden fijo y fecha fija (la del commit) para que el mismo contenido dé el mismo zip.
        $fechaCommit = [DateTimeOffset]::FromUnixTimeSeconds([long](git show -s --format=%ct HEAD).Trim())
        $nombres = New-Object System.Collections.Generic.HashSet[string]
        $ordenadas = $entradas.ToArray()
        $claves = [string[]]($ordenadas | ForEach-Object { $_.r })
        [Array]::Sort($claves, $ordenadas, [System.StringComparer]::Ordinal)
        $zipTmp = Join-Path $Trabajo 'paquete.zip'
        $archivos = New-Object System.Text.StringBuilder
        $total = 0
        $fs = [System.IO.File]::Open($zipTmp, 'Create')
        $za = New-Object System.IO.Compression.ZipArchive($fs, [System.IO.Compression.ZipArchiveMode]::Create)
        $sha = [System.Security.Cryptography.SHA256]::Create()
        try {
            foreach ($e in $ordenadas) {
                if (-not $nombres.Add($e.r)) { Frenar "Entrada duplicada en el paquete: $($e.r)" }
                $bytes = [System.IO.File]::ReadAllBytes((RutaLarga $e.f))
                $hash = [System.BitConverter]::ToString($sha.ComputeHash($bytes)).Replace('-', '').ToLower()
                $ze = $za.CreateEntry($e.r, [System.IO.Compression.CompressionLevel]::Optimal)
                $ze.LastWriteTime = $fechaCommit
                $es = $ze.Open()
                try { $es.Write($bytes, 0, $bytes.Length) } finally { $es.Dispose() }
                if ($total -gt 0) { [void]$archivos.Append(',') }
                [void]$archivos.Append('{"r":' + (JsonStr $e.r) + ',"s":"' + $hash + '","b":' + $bytes.Length + '}')
                $total++
            }
            $bm = (Sha256Archivo (Join-Path $repo 'public\build\manifest.json'))
            $manifest = '{"esquema":1,"commit":"' + $head + '","rama":"' + $rama + '","creado":"' + (Get-Date).ToUniversalTime().ToString('yyyy-MM-ddTHH:mm:ssZ') + '","php_objetivo":"' + $phpServidor + '"'
            $manifest += ',"base_produccion":' + (JsonStr $BaseProduccion)
            $manifest += ',"extensiones":' + (JsonLista $extVendor)
            $manifest += ',"migraciones_esperadas":' + (JsonLista $migraciones)
            $manifest += ',"flags_apagados":' + (JsonLista @($flags | Sort-Object))
            $manifest += ',"eliminar_public_html":["robots.txt","hot"],"eliminar_laravel_public":["hot"]'
            $manifest += ',"estaticos":' + (JsonLista $estaticos)
            $manifest += ',"build_manifest_sha256":"' + $bm + '"'
            $manifest += ',"htaccess_s2":' + (JsonStr $htS2)
            $manifest += ',"archivos":[' + $archivos.ToString() + ']}'
            $mb = (New-Object System.Text.UTF8Encoding($false)).GetBytes($manifest)
            $ze = $za.CreateEntry('manifest.json', [System.IO.Compression.CompressionLevel]::Optimal)
            $ze.LastWriteTime = $fechaCommit
            $es = $ze.Open()
            try { $es.Write($mb, 0, $mb.Length) } finally { $es.Dispose() }
        }
        finally { $sha.Dispose(); $za.Dispose(); $fs.Dispose() }

        # Comprobación local del zip: abre, y cada entrada declarada está.
        $chk = [System.IO.Compression.ZipFile]::OpenRead($zipTmp)
        try { $n = $chk.Entries.Count } finally { $chk.Dispose() }
        if ($n -ne ($total + 1)) { Frenar "El zip tiene $n entradas y se esperaban $($total + 1)." }
        foreach ($prohibido in '^laravel/(storage|public)/', '(^|/)\.env', '^public_html/(index\.php|cgi-bin/|images/products/|docs/)') {
            if ($nombres | Where-Object { $_ -match $prohibido }) { Frenar "El paquete trae archivos prohibidos ($prohibido)." }
        }
        New-Item -ItemType Directory $dirCache -Force | Out-Null
        Copy-Item $zipTmp $zipCache -Force
        $zipSha = Sha256Archivo $zipCache
        $info = [pscustomobject]@{ commit = $head; zip_sha256 = $zipSha; zip_bytes = (Get-Item $zipCache).Length; archivos = $total; todos_estaticos = [bool]$TodosLosEstaticos; migraciones = $migraciones.Count; extensiones = $extVendor; creado = (Get-Date).ToUniversalTime().ToString('s') + 'Z' }
        Escribir-Utf8 $infoCache ($info | ConvertTo-Json -Depth 4)
        Ok "paquete.zip del commit $($head.Substring(0,7)): $total archivos + manifest, $([math]::Round($info.zip_bytes / 1MB, 1)) MB, sha256 $($zipSha.Substring(0,16))…"
        Ok "Migraciones que se esperan pendientes: $($migraciones.Count) ($($migWeb.Count) de la web + $($migPaq.Count) del paquete) · extensiones del vendor: $($extVendor -join ', ')"
        Ok "Interruptores que el deploy deja en false: $(@($flags | Sort-Object) -join ', ')"
        Remove-Item $Trabajo -Recurse -Force -ErrorAction SilentlyContinue
        return @{ Zip = $zipCache; Info = $info; Head = $head }
    }
    finally { Pop-Location }
}

function Subir-Runner {
    if (-not (Test-Path $runnerSrc)) { Frenar "Falta $runnerSrc." }
    $src = [System.IO.File]::ReadAllBytes($runnerSrc)
    if ($src -contains 13) { Frenar 'runner.php tiene finales de línea CRLF: volvé a sacarlo de git (.gitattributes fuerza LF).' }
    $hashLocal = Sha256Archivo $runnerSrc
    $lint = Get-Command php -ErrorAction SilentlyContinue
    if ($lint) { Nativo { & $lint.Source -l $runnerSrc } | Out-Null; if ($LASTEXITCODE -ne 0) { Frenar 'runner.php tiene errores de sintaxis (php -l).' } }
    [void](Invoke-WinScp @("mkdir $remotoDir") -Tolerante)
    $r = Invoke-WinScp @("rm $remotoDir/runner.php.parte", "put -transfer=binary `"$runnerSrc`" $remotoDir/runner.php.parte", "rm $remotoDir/runner.php", "mv $remotoDir/runner.php.parte $remotoDir/runner.php") -Tolerante
    $dir = Join-Path $ftpTmp 'verif'
    Remove-Item -LiteralPath (Join-Path $dir 'runner.php') -Force -ErrorAction SilentlyContinue
    [void](Bajar "$remotoDir/runner.php" $dir)
    $copia = Join-Path $dir 'runner.php'
    if (-not (Test-Path -LiteralPath $copia)) { Frenar ("runner.php no quedó en el servidor. Salida de WinSCP:`n" + $r.Salida) }
    if ((Sha256Archivo $copia) -ne $hashLocal) { Frenar 'runner.php en el servidor NO coincide con el local (sha256).' }
    Ok "runner.php en ~/$remotoDir/ (sha256 $($hashLocal.Substring(0,16))…, verificado bajándolo de nuevo)"
}

function Subir-Paquete($pq) {
    $zip = $pq.Zip
    $tam = (Get-Item $zip).Length
    $r = Invoke-WinScp @("rm $remotoDir/paquete.zip.parte", "put -transfer=binary `"$zip`" $remotoDir/paquete.zip.parte", "rm $remotoDir/paquete.zip", "mv $remotoDir/paquete.zip.parte $remotoDir/paquete.zip", "stat $remotoDir/paquete.zip") -Tolerante
    # WinSCP habla el idioma de Windows: se lee la línea tipo «ls» (tamaño, fecha, hora, nombre), no un texto traducido
    $m = [regex]::Match($r.Salida, '(?m)\s(\d+)\s+\S+\s+\d+\s+[\d:]+(?:\s+\d{4})?\s+paquete\.zip\s*$')
    if (-not $m.Success) { Frenar ("No pude verificar el tamaño de paquete.zip en el servidor. Salida de WinSCP:`n" + $r.Salida) }
    if ([long]$m.Groups[1].Value -ne $tam) { Frenar "paquete.zip en el servidor pesa $($m.Groups[1].Value) bytes y el local ${tam}: la subida quedó incompleta." }
    Ok "paquete.zip subido: $tam bytes (== local). El runner vuelve a comprobar el sha256 antes de usarlo."
}

function Lanzar-Disparo([string]$modoRunner, $pq) {
    $nonce = [guid]::NewGuid().ToString('N')
    $ht = 'no'
    if ($AplicarHtaccess) { $ht = 'merge' }
    $json = '{"modo":"' + $modoRunner + '","commit":"' + $pq.Head + '","zip":"paquete.zip","zip_sha256":"' + (Prop $pq.Info 'zip_sha256') + '","zip_bytes":' + (Prop $pq.Info 'zip_bytes') + ',"nonce":"' + $nonce + '","creado":"' + (Get-Date).ToUniversalTime().ToString('yyyy-MM-ddTHH:mm:ssZ') + '","htaccess":"' + $ht + '","origen":"publicar.ps1"}'
    $loc = Join-Path $ftpTmp 'disparo.json'
    if (-not (Test-Path $ftpTmp)) { New-Item -ItemType Directory $ftpTmp | Out-Null }
    Escribir-Utf8 $loc $json
    try {
        $r = Invoke-WinScp @("rm $remotoDir/disparo.json", "put -transfer=binary `"$loc`" $remotoDir/disparo.json.parte", "mv $remotoDir/disparo.json.parte $remotoDir/disparo.json") -Tolerante
    }
    finally { Remove-Item -LiteralPath $loc -Force -ErrorAction SilentlyContinue }
    return $nonce
}

function Esperar-Runner([string]$nonce, [int]$minutos) {
    $limite = (Get-Date).AddMinutes($minutos)
    $corto = $nonce.Substring(0, 8)
    $impresas = 0
    $inicioVisto = $false
    Write-Host "  Esperando al cron de Ferozo (corre cada 5 minutos como mínimo; límite $minutos min). Leo estado.json y runner.log por FTP cada 15 s." -ForegroundColor DarkGray
    while ((Get-Date) -lt $limite) {
        $s = Estado-Remoto
        $lineas = @()
        if ($s.Log -ne '') {
            $todas = $s.Log -split "`r?`n"
            $desde = -1
            for ($i = $todas.Count - 1; $i -ge 0; $i--) { if ($todas[$i] -like "*nonce $corto*") { $desde = $i; break } }
            if ($desde -ge 0) { $inicioVisto = $true; $lineas = @($todas[$desde..($todas.Count - 1)] | Where-Object { $_ -ne '' }) }
        }
        if ($lineas.Count -gt $impresas) {
            for ($i = $impresas; $i -lt $lineas.Count; $i++) {
                $l = $lineas[$i]
                $color = 'Gray'
                if ($l -match ' ERROR ') { $color = 'Red' } elseif ($l -match ' AVISO ') { $color = 'Yellow' } elseif ($l -match '^\S+ \S+ INFO  ==> ') { $color = 'Cyan' } elseif ($l -match ' INFO  OK ') { $color = 'Green' }
                Write-Host ('  | ' + $l) -ForegroundColor $color
            }
            $impresas = $lineas.Count
        }
        $e = $s.Estado
        if ($null -ne $e -and (Prop $e 'nonce') -eq $nonce) {
            $est = [string](Prop $e 'estado' '')
            if ($est -ne '' -and $est -ne 'ejecutando') { return $e }
        }
        elseif ($null -ne $e -and (Prop $e 'estado') -eq 'rechazado' -and -not $inicioVisto) {
            # el disparo se rechazó antes de leer el nonce (p. ej. fecha vieja)
            return $e
        }
        Start-Sleep -Seconds 15
    }
    return $null
}

function Mostrar-Resultado($e) {
    Write-Host ''
    foreach ($p in @(Prop $e 'pasos' @())) {
        $okp = [bool](Prop $p 'ok' $true)
        $txt = ('{0,-62} {1} {2,6}s' -f (Prop $p 'n'), $(if ($okp) { 'OK  ' } else { 'FALLÓ' }), (Prop $p 'seg' 0))
        $resumen.Add('(servidor) ' + $txt)
    }
    foreach ($a in @(Prop $e 'avisos' @())) { Aviso $a }
    $mensaje = [string](Prop $e 'mensaje' '')
    $err = [string](Prop $e 'error' '')
    return @{ Estado = [string](Prop $e 'estado'); Mensaje = $mensaje; Error = $err; Manual = @(Prop $e 'manual' @()); Backup = [string](Prop $e 'backup' '') }
}

try {
    $flagsModo = @($Ensayo, $SoloLocal, $SoloRunner, $Estado, $LeerHtaccess) | Where-Object { $_ }
    if (@($flagsModo).Count -gt 1) { Frenar 'Elegí un solo modo: -Ensayo, -SoloLocal, -SoloRunner, -Estado o -LeerHtaccess (sin parámetros = deploy real).' }
    if (($PermitirArbolSucio -or $ReusarBuild) -and -not $SoloLocal -and $env:CF_PUBLICAR_PRUEBA -ne '1') { Frenar '-PermitirArbolSucio y -ReusarBuild sólo se aceptan con -SoloLocal (pruebas).' }
    Write-Host "*** publicar.ps1 - $modo ***" -ForegroundColor Cyan

    $necesitaFtp = -not $SoloLocal
    if ($necesitaFtp) {
        Paso '0 - Herramientas y credenciales'
        $script:cfg = Leer-EnvFtp $EnvFile
        $script:winscp = Asegurar-WinScp
        Ok "Credenciales leídas de $EnvFile (FTP_HOST=$($script:cfg['FTP_HOST']), modo $(if ($script:cfg.ContainsKey('FTP_MODO')) { $script:cfg['FTP_MODO'] } else { 'ftp' })) · WinSCP: $($script:winscp)"
        Paso '0b - Conexión FTP'
        Probar-Conexion
    }

    if ($Estado) {
        Paso 'Estado del runner'
        $s = Estado-Remoto
        if ($null -eq $s.Estado) { Aviso 'No hay estado.json en el servidor (el runner nunca corrió).' }
        else { Write-Host (($s.Estado | ConvertTo-Json -Depth 6)) }
        $ult = @($s.Log -split "`r?`n" | Select-Object -Last 25)
        $ult | ForEach-Object { Write-Host "  | $_" -ForegroundColor Gray }
    }
    elseif ($LeerHtaccess) {
        Paso 'Lectura de public_html/.htaccess e index.php (sólo lectura)'
        $dir = Join-Path $ftpTmp 'leer'
        if (Test-Path $dir) { Remove-Item $dir -Recurse -Force }
        New-Item -ItemType Directory $dir | Out-Null
        $r = Invoke-WinScp @('ls public_html', "get public_html/.htaccess `"$dir\`"", "get public_html/index.php `"$dir\`"") -Tolerante
        Write-Host $r.Salida
        $ha = Join-Path $dir '.htaccess'
        if (Test-Path -LiteralPath $ha) {
            $actual = Leer-Utf8 $ha
            Write-Host "---- public_html/.htaccess actual ($($actual.Length) bytes) ----" -ForegroundColor Cyan
            Write-Host $actual
            Write-Host '---- Comparación con el repo (bloques S.2) ----' -ForegroundColor Cyan
            $repoHt = Leer-Utf8 (Join-Path $repo 'public\.htaccess')
            foreach ($d in 'RewriteEngine On', 'Options -MultiViews -Indexes', 'Header always set', 'RewriteRule "(^|/)\.(?!well-known', 'IfModule mod_headers.c', 'Archivos que nunca se sirven') {
                Write-Host ('  {0,-45} actual: {1,-4} repo: {2}' -f $d, $(if ($actual.Contains($d)) { 'sí' } else { 'no' }), $(if ($repoHt.Contains($d)) { 'sí' } else { 'no' }))
            }
        }
        else { Aviso 'No pude bajar public_html/.htaccess.' }
        $ix = Join-Path $dir 'index.php'
        if (Test-Path -LiteralPath $ix) {
            $idx = Leer-Utf8 $ix
            Write-Host "---- public_html/index.php ($($idx.Length) bytes) ----" -ForegroundColor Cyan
            Write-Host $idx
            Write-Host ("  usePublicPath: " + $(if ($idx.Contains('usePublicPath')) { 'SÍ (public_path() = public_html)' } else { 'no (public_path() = laravel/public)' }))
        }
        Ok "Archivos leídos en $dir (nada se modificó en el servidor)"
    }
    elseif ($SoloRunner) {
        Paso 'Subir runner.php'
        Subir-Runner
        $script:resultado = @{ Estado = 'ok' }
    }
    else {
        $infoPaquete = Construir-Paquete
        if ($SoloLocal) {
            Cerrar-Paso; $script:paso = 'inicio'
            Aviso "-SoloLocal: me detengo acá, no me conecté al servidor. Paquete: $($infoPaquete.Zip)"
        }
        else {
            Paso '5 - Subida por FTP (runner.php, paquete.zip)'
            $s = Estado-Remoto
            if ($null -ne $s.Estado -and (Prop $s.Estado 'estado') -eq 'ejecutando') {
                $act = [DateTime]::Parse([string](Prop $s.Estado 'actualizado' '2000-01-01T00:00:00Z')).ToUniversalTime()
                if (((Get-Date).ToUniversalTime() - $act).TotalMinutes -lt 30) { Frenar "El runner figura 'ejecutando' desde hace menos de 30 min (paso: $(Prop $s.Estado 'paso')). Esperá a que termine o revisá con -Estado." }
                Aviso 'El runner figura ejecutando pero hace más de 30 minutos que no actualiza: la próxima corrida del cron intentará recuperarse sola.'
            }
            Subir-Runner
            Subir-Paquete $infoPaquete
            $h = (Prop $infoPaquete.Info 'zip_sha256')
            $tout = $TimeoutMin
            $corrioEnsayoAhora = $false

            $hacerEnsayo = [bool]$Ensayo
            if (-not $Ensayo) {
                $ok = $null
                if ($null -ne $s.Estado) { $ok = Prop $s.Estado 'ensayo_ok' }
                $vigente = $false
                if ($null -ne $ok -and (Prop $ok 'commit') -eq $infoPaquete.Head -and (Prop $ok 'zip_sha256') -eq $h) {
                    $edad = ((Get-Date).ToUniversalTime() - [DateTime]::Parse([string](Prop $ok 'at')).ToUniversalTime()).TotalHours
                    if ($edad -lt 24) { $vigente = $true }
                }
                if ($vigente) { Ok 'Ya hay un ensayo exitoso de este mismo paquete (últimas 24 h): paso directo al deploy.' }
                else { $hacerEnsayo = $true; Aviso 'No hay un ensayo exitoso de este paquete: lo hago primero.' }
            }

            if ($hacerEnsayo) {
                Paso '6 - Ensayo en el servidor (no toca el sitio)'
                if ($tout -le 0) { $tout = 20 }
                $nonce = Lanzar-Disparo 'ensayo' $infoPaquete
                Ok "disparo.json (ensayo) subido, nonce $($nonce.Substring(0,8))"
                $e = Esperar-Runner $nonce $tout
                if ($null -eq $e) {
                    [void](Invoke-WinScp @("rm $remotoDir/disparo.json") -Tolerante)
                    Frenar "El runner no respondió en $tout min. Revisá que la tarea del cron exista en Ferozo (php8-2 + ruta del runner). Retiré el disparo para que no se ejecute después."
                }
                $res = Mostrar-Resultado $e
                if ($res.Estado -ne 'ok') { Frenar "El ensayo terminó en estado '$($res.Estado)': $($res.Error)" }
                Ok $res.Mensaje
                $script:resultado = $res
            }
            if (-not $Ensayo) {
                Paso '7 - Confirmación'
                Write-Host ''
                Write-Host "  Se va a desplegar $($infoPaquete.Head.Substring(0,7)) en https://chispasfrias.com con la integración APAGADA." -ForegroundColor Yellow
                Write-Host '  La tienda queda en mantenimiento unos minutos; si algo falla, el runner vuelve atrás solo.'
                $resp = Read-Host '  Escribí DEPLOY para continuar'
                if ($resp -ne 'DEPLOY') { Frenar "«$resp» no es DEPLOY: no se hace nada." }
                Paso '8 - Deploy en el servidor'
                if ($tout -le 0) { $tout = 25 }
                $nonce = Lanzar-Disparo 'deploy' $infoPaquete
                Ok "disparo.json (deploy) subido, nonce $($nonce.Substring(0,8))"
                $e = Esperar-Runner $nonce $tout
                if ($null -eq $e) {
                    [void](Invoke-WinScp @("rm $remotoDir/disparo.json") -Tolerante)
                    Frenar "El runner no respondió en $tout min (retiré el disparo). Si el sitio quedó en mantenimiento, mirá -Estado."
                }
                $res = Mostrar-Resultado $e
                $script:resultado = $res
                if ($res.Estado -ne 'ok') { $codigo = 1; Frenar "El deploy terminó en estado '$($res.Estado)': $($res.Mensaje) $($res.Error)" }
                Ok $res.Mensaje
            }
        }
    }
}
catch {
    if ($codigo -eq 0) { $codigo = 1 }
    Write-Host ''
    Write-Host "[ERROR] $($_.Exception.Message)" -ForegroundColor Red
    $resumen.Add(('{0,-62} FALLÓ' -f $script:paso))
    $script:paso = 'inicio'
}

# --- Resumen ---------------------------------------------------------------------------------------------------------------------------
Cerrar-Paso
Write-Host ''
Write-Host '============================ RESUMEN ============================' -ForegroundColor Cyan
foreach ($l in $resumen) { Write-Host "  $l" }
Write-Host ''
Write-Host "  Modo: $modo"
if ($codigo -eq 0) {
    if ($SoloLocal) { Write-Host '  Local OK: git limpio, build, vendor y paquete listos. Nada se subió.' -ForegroundColor Green }
    elseif ($SoloRunner) {
        Write-Host '  runner.php subido y verificado.' -ForegroundColor Green
        Write-Host '  Siguiente: crear en Ferozo (Tareas programadas / Cron) UNA tarea cada 5 minutos con el comando:'
        Write-Host "    /opt/php8-2/bin/php /home/c2731764/deploy-cf/runner.php" -ForegroundColor White
        Write-Host '  Después:  publicar.ps1 -Ensayo'
    }
    elseif ($Ensayo) {
        Write-Host '  ENSAYO OK: no se tocó el sitio.' -ForegroundColor Green
        Write-Host '  Siguiente: publicar.ps1   (deploy real; pide escribir DEPLOY).'
    }
    elseif ($Estado -or $LeerHtaccess) { Write-Host '  Lectura OK.' -ForegroundColor Green }
    else { Write-Host '  DEPLOY OK.' -ForegroundColor Green }
}
else { Write-Host "  TERMINÓ CON ERROR (código $codigo). Revisá los mensajes de arriba." -ForegroundColor Red }
if ($null -ne $script:resultado -and $script:resultado -is [hashtable] -and $script:resultado.ContainsKey('Backup') -and $script:resultado.Backup -ne '') {
    Write-Host "  Backup en el servidor: $($script:resultado.Backup)"
}
if ($null -ne $script:resultado -and $script:resultado -is [hashtable] -and $script:resultado.ContainsKey('Manual') -and @($script:resultado.Manual).Count -gt 0) {
    Write-Host ''
    Write-Host '  Falta (manual):' -ForegroundColor Yellow
    foreach ($m in $script:resultado.Manual) { Write-Host "   - $m" -ForegroundColor Yellow }
}
exit $codigo
