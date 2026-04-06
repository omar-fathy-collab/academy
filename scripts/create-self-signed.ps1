<#
  Small PowerShell helper to create a local self-signed certificate for development/staging.
  It will create a cert in the CurrentUser\My store and export a PFX to the scripts folder.
  Use mkcert for a better developer experience: https://github.com/FiloSottile/mkcert
#>

param(
    [string]$Domain = 'localhost',
  # Read PFX password from environment variable or use a placeholder instructing the operator to set it
  [string]$Password = $env:PFX_PASSWORD
  if (-not $Password) {
    Write-Host "WARNING: PFX_PASSWORD environment variable is not set. Using placeholder 'ChangeMePlease' — set a strong password when exporting certificates."
    $Password = 'ChangeMePlease'
  }

  $securePwd = ConvertTo-SecureString -String $Password -Force -AsPlainText
$cert = New-SelfSignedCertificate -DnsName $Domain -CertStoreLocation Cert:\CurrentUser\My -NotAfter (Get-Date).AddYears(2)
if (!$cert) { Write-Error "Failed to create certificate"; exit 1 }

$pfxPath = Join-Path -Path (Split-Path -Parent $MyInvocation.MyCommand.Definition) -ChildPath "$Domain.pfx"
$securePwd = ConvertTo-SecureString -String $Password -Force -AsPlainText
Export-PfxCertificate -Cert "Cert:\CurrentUser\My\$($cert.Thumbprint)" -FilePath $pfxPath -Password $securePwd

Write-Host "Exported PFX to $pfxPath"
Write-Host "Thumbprint: $($cert.Thumbprint)"
Write-Host "To trust this cert in Windows run: certmgr.msc -> Personal -> Certificates -> import the PFX and then copy to Trusted Root Certification Authorities (use carefully)"
