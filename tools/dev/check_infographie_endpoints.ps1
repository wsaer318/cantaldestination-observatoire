param(
    [string]$BaseUrl = "http://localhost/fluxvision_fin",
    [string]$Year = "2024",
    [string]$Period = "annee_complete",
    [string]$Zone = "CANTAL"
)

Add-Type -AssemblyName System.Web

function New-QueryString {
    param([hashtable]$Params)
    $builder = [System.Text.StringBuilder]::new()
    $first = $true
    foreach ($key in $Params.Keys) {
        if ($null -eq $Params[$key] -or $Params[$key] -eq "") {
            continue
        }
        $value = [Uri]::EscapeDataString([string]$Params[$key])
        if (-not $first) { $builder.Append("&") | Out-Null }
        $builder.Append($key).Append("=").Append($value) | Out-Null
        $first = $false
    }
    $builder.ToString()
}

$endpoints = @(
    @{ Name = 'V2 - Départements touristes'; Path = '/api/v2/infographie/departements-touristes'; Params = @{ annee = $Year; periode = $Period; zone = $Zone; limit = 15 } },
    @{ Name = 'V2 - Régions touristes'; Path = '/api/v2/infographie/regions-touristes'; Params = @{ annee = $Year; periode = $Period; zone = $Zone; limit = 10 } },
    @{ Name = 'V2 - Départements excursionnistes'; Path = '/api/v2/infographie/departements-excursionnistes'; Params = @{ annee = $Year; periode = $Period; zone = $Zone; limit = 15 } },
    @{ Name = 'V2 - Régions excursionnistes'; Path = '/api/v2/infographie/regions-excursionnistes'; Params = @{ annee = $Year; periode = $Period; zone = $Zone; limit = 10 } },
    @{ Name = 'V1 proxy - Départements excursionnistes'; Path = '/api/infographie/infographie_departements_excursionnistes.php'; Params = @{ annee = $Year; periode = $Period; zone = $Zone; limit = 15 } },
    @{ Name = 'V1 proxy - Régions excursionnistes'; Path = '/api/infographie/infographie_regions_excursionnistes.php'; Params = @{ annee = $Year; periode = $Period; zone = $Zone; limit = 10 } }
)

$results = @()
foreach ($endpoint in $endpoints) {
    $query = New-QueryString -Params $endpoint.Params
    $url = "$BaseUrl$($endpoint.Path)?$query"
    $entry = [ordered]@{
        Name = $endpoint.Name
        Url = $url
        Status = $null
        Body = $null
        Error = $null
    }
    try {
        $response = Invoke-WebRequest -Uri $url -UseBasicParsing -ErrorAction Stop
        $entry.Status = [int]$response.StatusCode
        $bodyText = $response.Content
    }
    catch {
        $entry.Error = $_.Exception.Message
        if ($_.Exception.Response -and $_.Exception.Response -is [System.Net.HttpWebResponse]) {
            $resp = $_.Exception.Response
            $entry.Status = [int]$resp.StatusCode
            $reader = New-Object System.IO.StreamReader($resp.GetResponseStream())
            $bodyText = $reader.ReadToEnd()
            $reader.Dispose()
        }
        else {
            $bodyText = $null
        }
    }

    if ($bodyText) {
        try {
            $jsonObj = $bodyText | ConvertFrom-Json -ErrorAction Stop
            $entry.Body = ($jsonObj | ConvertTo-Json -Depth 6)
        }
        catch {
            $entry.Body = $bodyText
        }
    }

    $results += [pscustomobject]$entry
}

$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$tempFile = Join-Path ([IO.Path]::GetTempPath()) "infographie_check_$timestamp.html"

$htmlHeader = @'
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8" />
<title>Infographie API Check</title>
<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
section { background: #fff; padding: 16px; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); margin-bottom: 20px; }
section h2 { margin-top: 0; font-size: 18px; }
section p { margin: 4px 0; }
pre { background: #272822; color: #f8f8f2; padding: 12px; border-radius: 6px; overflow-x: auto; }
.status-ok { color: #2e7d32; font-weight: bold; }
.status-error { color: #c62828; font-weight: bold; }
small { color: #555; }
</style>
</head>
<body>
<h1>Infographie API Check</h1>
<p><small>Base: BASE_PLACEHOLDER — Date: DATE_PLACEHOLDER — Zone: ZONE_PLACEHOLDER — Période: PERIOD_PLACEHOLDER</small></p>
'@
$htmlHeader = $htmlHeader.Replace('BASE_PLACEHOLDER', $BaseUrl)
$htmlHeader = $htmlHeader.Replace('DATE_PLACEHOLDER', (Get-Date).ToString('yyyy-MM-dd HH:mm:ss'))
$htmlHeader = $htmlHeader.Replace('ZONE_PLACEHOLDER', $Zone)
$htmlHeader = $htmlHeader.Replace('PERIOD_PLACEHOLDER', $Period)

Set-Content -Path $tempFile -Value $htmlHeader -Encoding UTF8

foreach ($item in $results) {
    $statusClass = if ($item.Status -and $item.Status -ge 200 -and $item.Status -lt 300) { 'status-ok' } else { 'status-error' }
    Add-Content -Path $tempFile -Encoding UTF8 -Value '<section>'
    Add-Content -Path $tempFile -Encoding UTF8 -Value ("<h2>{0}</h2>" -f [System.Web.HttpUtility]::HtmlEncode($item.Name))
    Add-Content -Path $tempFile -Encoding UTF8 -Value ("<p><strong>URL :</strong> <a href='{0}' target='_blank'>{0}</a></p>" -f [System.Web.HttpUtility]::HtmlEncode($item.Url))
    if ($item.Status) {
        Add-Content -Path $tempFile -Encoding UTF8 -Value ("<p><strong>Statut :</strong> <span class='{0}'>{1}</span></p>" -f $statusClass, $item.Status)
    }
    if ($item.Error) {
        Add-Content -Path $tempFile -Encoding UTF8 -Value ("<p><strong>Erreur :</strong> {0}</p>" -f [System.Web.HttpUtility]::HtmlEncode($item.Error))
    }
    if ($item.Body) {
        $encodedBody = [System.Web.HttpUtility]::HtmlEncode($item.Body)
        Add-Content -Path $tempFile -Encoding UTF8 -Value "<pre>$encodedBody</pre>"
    }
    else {
        Add-Content -Path $tempFile -Encoding UTF8 -Value '<pre>(Corps vide)</pre>'
    }
    Add-Content -Path $tempFile -Encoding UTF8 -Value '</section>'
}

Add-Content -Path $tempFile -Encoding UTF8 -Value '</body></html>'

$escapedPath = $tempFile.Replace("&", "^&")
Start-Process -FilePath "cmd.exe" -ArgumentList "/c start `"$escapedPath`"" -WindowStyle Hidden
