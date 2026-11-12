[CmdletBinding()]
param(
	[string] $OutputDirectory = "dist"
)

$ErrorActionPreference = "Stop"

$projectRoot = Split-Path -Parent $PSScriptRoot
$mainPluginFile = Join-Path $projectRoot "southland-residential-calculator.php"
$readmeFile = Join-Path $projectRoot "readme.txt"
$pluginSlug = "southland-residential-calculator"
$archiveName = "$pluginSlug.zip"

$pluginContents = Get-Content -LiteralPath $mainPluginFile -Raw
$readmeContents = Get-Content -LiteralPath $readmeFile -Raw

$headerMatch = [regex]::Match($pluginContents, '(?m)^\s*\*\s*Version:\s*([^\r\n]+)')
$constantMatch = [regex]::Match($pluginContents, "define\(\s*'SRC_VERSION'\s*,\s*'([^']+)'\s*\)")
$stableTagMatch = [regex]::Match($readmeContents, '(?m)^Stable tag:\s*([^\r\n]+)')

if (-not $headerMatch.Success -or -not $constantMatch.Success -or -not $stableTagMatch.Success) {
	throw "Could not read the plugin version from the header, SRC_VERSION, and readme Stable tag."
}

$versions = @(
	$headerMatch.Groups[1].Value.Trim(),
	$constantMatch.Groups[1].Value.Trim(),
	$stableTagMatch.Groups[1].Value.Trim()
)

if (($versions | Select-Object -Unique).Count -ne 1) {
	throw "Version mismatch: plugin header=$($versions[0]), SRC_VERSION=$($versions[1]), Stable tag=$($versions[2])."
}

if ($env:GITHUB_REF_TYPE -eq 'tag') {
	$expectedTag = "v$($versions[0])"
	if ($env:GITHUB_REF_NAME -ne $expectedTag) {
		throw "Release tag '$($env:GITHUB_REF_NAME)' must match plugin version tag '$expectedTag'."
	}
}

$resolvedOutputDirectory = if ([System.IO.Path]::IsPathRooted($OutputDirectory)) {
	$OutputDirectory
} else {
	Join-Path $projectRoot $OutputDirectory
}

New-Item -ItemType Directory -Path $resolvedOutputDirectory -Force | Out-Null
$archivePath = Join-Path $resolvedOutputDirectory $archiveName

$temporaryRoot = Join-Path ([System.IO.Path]::GetTempPath()) ("src-plugin-build-" + [guid]::NewGuid().ToString('N'))
$packageRoot = Join-Path $temporaryRoot $pluginSlug

try {
	New-Item -ItemType Directory -Path $packageRoot -Force | Out-Null

	foreach ($directory in @('assets', 'includes', 'vendor')) {
		Copy-Item -LiteralPath (Join-Path $projectRoot $directory) -Destination $packageRoot -Recurse
	}

	foreach ($file in @('southland-residential-calculator.php', 'readme.txt')) {
		Copy-Item -LiteralPath (Join-Path $projectRoot $file) -Destination $packageRoot
	}

	if (Test-Path -LiteralPath $archivePath) {
		Remove-Item -LiteralPath $archivePath -Force
	}

	Add-Type -AssemblyName System.IO.Compression
	Add-Type -AssemblyName System.IO.Compression.FileSystem

	$archiveStream = [System.IO.File]::Open($archivePath, [System.IO.FileMode]::CreateNew)
	try {
		$archive = New-Object System.IO.Compression.ZipArchive(
			$archiveStream,
			[System.IO.Compression.ZipArchiveMode]::Create,
			$false
		)
		try {
			Get-ChildItem -LiteralPath $packageRoot -Recurse -File | ForEach-Object {
				$relativePath = $_.FullName.Substring($temporaryRoot.Length + 1).Replace('\', '/')
				$entry = $archive.CreateEntry($relativePath, [System.IO.Compression.CompressionLevel]::Optimal)
				$entryStream = $entry.Open()
				$fileStream = [System.IO.File]::OpenRead($_.FullName)
				try {
					$fileStream.CopyTo($entryStream)
				} finally {
					$fileStream.Dispose()
					$entryStream.Dispose()
				}
			}
		} finally {
			$archive.Dispose()
		}
	} finally {
		$archiveStream.Dispose()
	}
} finally {
	if (Test-Path -LiteralPath $temporaryRoot) {
		Remove-Item -LiteralPath $temporaryRoot -Recurse -Force
	}
}

Write-Output "Built $archivePath (version $($versions[0]))"
