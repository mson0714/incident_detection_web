[CmdletBinding()]
param(
    [int]$Pages = 81,
    [int]$DelayMilliseconds = 500,
    [string]$Output = "docs/data/opencctv-vietnam-traffic.csv"
)

$ErrorActionPreference = "Stop"
$baseUrl = "https://opencctv.org/cameras/vietnam/category/traffic"
$rows = [System.Collections.Generic.List[object]]::new()

for ($page = 1; $page -le $Pages; $page++) {
    $url = $baseUrl
    if ($page -gt 1) {
        $url += "?page=$page"
    }

    Write-Progress -Activity "Collecting public camera index" `
        -Status "Page $page/$Pages; rows $($rows.Count)" `
        -PercentComplete (($page / $Pages) * 100)

    $html = (Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 30).Content
    $pattern = 'href="(?<path>/cameras/vietnam/[^"]+)"[\s\S]{0,700}?<img[^>]+alt="(?<name>[^"]*)"'

    foreach ($match in [regex]::Matches($html, $pattern, [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)) {
        $name = [System.Net.WebUtility]::HtmlDecode($match.Groups["name"].Value)
        $pageUrl = "https://opencctv.org$($match.Groups["path"].Value)"
        $rows.Add([pscustomobject]@{
            platform = "OpenCCTV"
            name = $name
            page_url = $pageUrl
            catalog_url = $url
            access = "public page; verify terms before automated capture"
            feed_type = "unknown (page may embed image/video)"
        })
    }

    if ($page -lt $Pages) {
        Start-Sleep -Milliseconds $DelayMilliseconds
    }
}

$parent = Split-Path -Parent $Output
if ($parent) {
    New-Item -ItemType Directory -Force -Path $parent | Out-Null
}

$unique = $rows | Sort-Object page_url -Unique
$unique | Export-Csv -NoTypeInformation -Encoding UTF8 -Path $Output
Write-Progress -Activity "Collecting public camera index" -Completed
Write-Host "Saved $($unique.Count) unique public camera page URLs to $Output"
