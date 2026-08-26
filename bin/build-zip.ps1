<#
.SYNOPSIS
    Builds an installable, production-only ZIP of the plugin (excludes
    tests/, docs/, node_modules/, and other dev-only files) and, unless
    -SkipRelease is passed, publishes it as a GitHub Release matching the
    plugin's current version header so the in-admin update checker
    (includes/class-rlr-updater.php) can find it.

.EXAMPLE
    ./bin/build-zip.ps1
    Builds build/restaurant-location-redirect.zip and creates/updates a
    GitHub release tagged with the current plugin version.

.EXAMPLE
    ./bin/build-zip.ps1 -SkipRelease
    Builds the ZIP only; does not touch GitHub.
#>
param(
	[switch]$SkipRelease
)

$ErrorActionPreference = 'Stop'

$pluginRoot = Split-Path -Parent $PSScriptRoot
$mainFile   = Join-Path $pluginRoot 'restaurant-location-redirect.php'

$versionLine = Select-String -Path $mainFile -Pattern "define\(\s*'RLR_VERSION',\s*'([^']+)'\s*\)"
if (-not $versionLine) {
	throw "Could not find RLR_VERSION in $mainFile"
}
$version = $versionLine.Matches[0].Groups[1].Value
Write-Host "Building version $version..."

$buildDir = Join-Path $pluginRoot 'build'
$target   = Join-Path $buildDir 'restaurant-location-redirect'
if (Test-Path $buildDir) { Remove-Item $buildDir -Recurse -Force }
New-Item -ItemType Directory -Force -Path $target | Out-Null

$dirs = @('includes', 'admin', 'public', 'templates', 'languages')
foreach ($d in $dirs) {
	$srcPath = Join-Path $pluginRoot $d
	if (Test-Path $srcPath) {
		Copy-Item -Path $srcPath -Destination (Join-Path $target $d) -Recurse -Force
	}
}
$files = @('restaurant-location-redirect.php', 'uninstall.php', 'readme.txt')
foreach ($f in $files) {
	Copy-Item -Path (Join-Path $pluginRoot $f) -Destination (Join-Path $target $f) -Force
}

$zipPath = Join-Path $buildDir 'restaurant-location-redirect.zip'
Compress-Archive -Path $target -DestinationPath $zipPath -CompressionLevel Optimal
Write-Host "Built: $zipPath"

if ($SkipRelease) {
	return
}

if (-not (Get-Command gh -ErrorAction SilentlyContinue)) {
	Write-Warning "GitHub CLI (gh) not found - skipping release publish. Run with -SkipRelease to silence this, or install gh."
	return
}

[string]$tag = 'v' + $version
Write-Host ( 'Publishing release ' + $tag + '...' )

& gh release create $tag $zipPath --title $tag --generate-notes
if ($LASTEXITCODE -ne 0) {
	Write-Host ( 'Release ' + $tag + ' likely already exists - uploading/replacing the ZIP asset instead.' )
	& gh release upload $tag $zipPath --clobber
}
