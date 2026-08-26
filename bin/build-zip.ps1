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
if (Test-Path $zipPath) { Remove-Item $zipPath -Force }

# Deliberately building the zip entry-by-entry instead of using
# Compress-Archive or ZipFile.CreateFromDirectory: both write backslash
# "\" path separators into zip entries on this platform (a long-standing
# .NET Framework behavior on Windows), instead of the ZIP-spec-required
# forward slash "/". Windows tools tolerate that, but Linux hosts (nearly
# all WordPress hosting) do not -- unzip there treats each entry as one
# flat, oddly-named file instead of creating subdirectories, so WordPress
# reports "Plugin file does not exist" after an apparently successful
# install. Writing entry names ourselves guarantees forward slashes.
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$zipStream = [System.IO.File]::Open( $zipPath, [System.IO.FileMode]::Create )
$archive   = New-Object System.IO.Compression.ZipArchive( $zipStream, [System.IO.Compression.ZipArchiveMode]::Create )

try {
	$targetFullPath = (Resolve-Path $target).Path
	Get-ChildItem -Path $target -Recurse -File | ForEach-Object {
		$relative = $_.FullName.Substring( $targetFullPath.Length + 1 ) -replace '\\', '/'
		$entryName = 'restaurant-location-redirect/' + $relative
		$entry = $archive.CreateEntry( $entryName, [System.IO.Compression.CompressionLevel]::Optimal )
		$entryStream = $entry.Open()
		try {
			$fileBytes = [System.IO.File]::ReadAllBytes( $_.FullName )
			$entryStream.Write( $fileBytes, 0, $fileBytes.Length )
		} finally {
			$entryStream.Dispose()
		}
	}
} finally {
	$archive.Dispose()
	$zipStream.Dispose()
}
Write-Host "Built: $zipPath"

if ($SkipRelease) {
	return
}

if (-not (Get-Command gh -ErrorAction SilentlyContinue)) {
	Write-Warning "GitHub CLI (gh) not found - skipping release publish. Run with -SkipRelease to silence this, or install gh."
	return
}

# A GitHub release tag points at whatever commit is currently HEAD. If
# source changes (like this version bump) haven't been committed and
# pushed yet, the tag ends up pointing at an OLDER commit whose plugin
# header still shows the previous version -- the update checker (which
# reads the version from the tagged commit's file content, not the ZIP
# asset) would then see no version increase and report "up to date" even
# though a newer ZIP is attached. Require a clean, pushed tree first.
$dirty = git status --porcelain
if ($dirty) {
	throw "Working tree has uncommitted changes. Commit and push before releasing, so the release tag points at the code it actually ships:`n$dirty"
}
git fetch origin --quiet
$localHead  = git rev-parse HEAD
$remoteHead = git rev-parse '@{u}' 2>$null
if ($remoteHead -and ($localHead -ne $remoteHead)) {
	throw "Local HEAD ($localHead) differs from the pushed branch ($remoteHead). Push your commits before releasing."
}

[string]$tag = 'v' + $version
Write-Host ( 'Publishing release ' + $tag + '...' )

& gh release create $tag $zipPath --title $tag --generate-notes
if ($LASTEXITCODE -ne 0) {
	Write-Host ( 'Release ' + $tag + ' likely already exists - uploading/replacing the ZIP asset instead.' )
	& gh release upload $tag $zipPath --clobber
}
