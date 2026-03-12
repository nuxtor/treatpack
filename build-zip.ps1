$pluginDir = 'C:\Users\Administrator\Herd\wp-treatments\wp-content\plugins\treatment-packages-deposits'
$zipPath = 'C:\Users\Administrator\OneDrive - 1 Stop Print LTD\Desktop\treatment-packages-deposits.zip'
$tempDir = Join-Path $env:TEMP 'tp-zip-build'

# Clean up temp dir
if (Test-Path $tempDir) { Remove-Item $tempDir -Recurse -Force }

# Create plugin folder inside temp
$dest = Join-Path $tempDir 'treatment-packages-deposits'
New-Item -ItemType Directory -Path $dest -Force | Out-Null

# Copy individual files
Copy-Item (Join-Path $pluginDir 'treatment-packages-deposits.php') $dest
Copy-Item (Join-Path $pluginDir 'uninstall.php') $dest
Copy-Item (Join-Path $pluginDir 'import-fresh-data.php') $dest

# Copy directories
Copy-Item (Join-Path $pluginDir 'src') (Join-Path $dest 'src') -Recurse
Copy-Item (Join-Path $pluginDir 'assets') (Join-Path $dest 'assets') -Recurse

# Remove old zip if exists
if (Test-Path $zipPath) { Remove-Item $zipPath -Force }

# Create ZIP
Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::CreateFromDirectory($tempDir, $zipPath)

# Cleanup
Remove-Item $tempDir -Recurse -Force

# Verify
$fileInfo = Get-Item $zipPath
$sizeKB = [math]::Round($fileInfo.Length / 1KB, 1)
Write-Host "ZIP created: $zipPath"
Write-Host "Size: $sizeKB KB"

# List contents
$zip = [System.IO.Compression.ZipFile]::OpenRead($zipPath)
Write-Host "`nContents ($($zip.Entries.Count) files):"
foreach ($entry in $zip.Entries) {
    Write-Host "  $($entry.FullName)"
}
$zip.Dispose()
