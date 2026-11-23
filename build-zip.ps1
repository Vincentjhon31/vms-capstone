# Builds dist\vms-site.zip - the app, ready to upload to Hostinger by File Manager.
#
# includes\config.local.php is NEVER put in the zip. On the server that file holds the
# database connection; leaving it out means extracting the zip over the live site cannot
# overwrite it. Also left out: .git, dist, uploads, tmp, logs and the local-only helpers.
#
#   Right-click > Run with PowerShell, or:  powershell -ExecutionPolicy Bypass -File build-zip.ps1

$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$dist = Join-Path $root 'dist'
$zip  = Join-Path $dist 'vms-site.zip'

# Paths (relative, forward slashes) and folders that must not be shipped.
$skipFiles = @(
    'includes/config.local.php',   # the server's own database connection
    'deploy-local.cmd', 'start-local.bat', 'build-zip.ps1', 'deploy-hostinger.sh',
    '.gitignore'
)
$skipDirs = @('.git/', 'dist/', 'uploads/', 'tmp/', 'node_modules/', '.vscode/', '.idea/')

New-Item -ItemType Directory -Force $dist | Out-Null
if (Test-Path $zip) { Remove-Item $zip -Force -Confirm:$false }

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$archive = [System.IO.Compression.ZipFile]::Open($zip, 'Create')
$count = 0
try {
    Get-ChildItem $root -Recurse -File -Force | ForEach-Object {
        # Forward slashes so Hostinger (Linux) extracts real folders
        $rel = $_.FullName.Substring($root.Length + 1).Replace('\', '/')
        if ($skipFiles -contains $rel) { return }
        if ($skipDirs | Where-Object { $rel.StartsWith($_) }) { return }
        if ($rel -like '*.log' -or $rel -like '*.sql.gz') { return }
        # Source documents kept beside the code (the Charter .docx) are not part of the site.
        if ($rel -like '*.docx' -or $rel -like '*.doc') { return }
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $_.FullName, $rel, 'Optimal') | Out-Null
        $count++
    }
} finally {
    $archive.Dispose()
}

# Belt and braces: open the finished zip and refuse it if the config slipped in.
$check = [System.IO.Compression.ZipFile]::OpenRead($zip)
try {
    $bad = $check.Entries | Where-Object { $_.FullName -like '*config.local.php' }
} finally {
    $check.Dispose()
}
if ($bad) {
    Remove-Item $zip -Force -Confirm:$false
    Write-Host 'STOPPED: config.local.php ended up in the zip, so it was deleted.' -ForegroundColor Red
    exit 1
}

$size = [math]::Round((Get-Item $zip).Length / 1MB, 1)
Write-Host "Created $zip ($count files, $size MB)"
Write-Host 'includes/config.local.php is not in it - the server keeps its own.'
Write-Host 'After uploading and extracting: sign in at /Admin and open install.php once if the update changes the database.'
