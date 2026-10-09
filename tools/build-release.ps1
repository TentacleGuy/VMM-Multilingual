param([string]$Version='0.16.0')
$ErrorActionPreference='Stop'
$root=Split-Path $PSScriptRoot -Parent
$source=Join-Path $root 'vmm-multilingual'
$header=Get-Content -Raw (Join-Path $source 'vmm-multilingual.php')
if($Version -notmatch '^\d+\.\d+\.\d+$' -or $header -notmatch ('Version: '+[regex]::Escape($Version)+'\s')){throw 'Version does not match plugin header'}
Add-Type -AssemblyName System.IO.Compression
$out=Join-Path $root ('releases/vmm-multilingual-'+$Version+'.zip')
if(Test-Path -LiteralPath $out){throw 'Release archive already exists; do not replace published versions'}
$stream=[IO.File]::Open($out,[IO.FileMode]::CreateNew)
$zip=[IO.Compression.ZipArchive]::new($stream,[IO.Compression.ZipArchiveMode]::Create)
try {
 foreach($file in Get-ChildItem -LiteralPath $source -File -Recurse | Sort-Object FullName){
  $relative=$file.FullName.Substring($source.Length+1).Replace('\','/')
  if($relative.StartsWith('tests/')){continue}
  $entry=$zip.CreateEntry('vmm-multilingual/'+$relative,[IO.Compression.CompressionLevel]::Optimal)
  $dest=$entry.Open();$inputStream=$file.OpenRead()
  try{$inputStream.CopyTo($dest)}finally{$inputStream.Dispose();$dest.Dispose()}
 }
}finally{$zip.Dispose();$stream.Dispose()}
$hash=(Get-FileHash -LiteralPath $out -Algorithm SHA256).Hash.ToLowerInvariant()
[IO.File]::WriteAllText($out+'.sha256',$hash+'  '+[IO.Path]::GetFileName($out)+"`n")
$manifest=[ordered]@{name='VMM Multilingual';version=$Version;requires='6.1';requires_php='8.1';tested='7.1.3';download_url=('https://github.com/TentacleGuy/VMM-Multilingual/releases/download/v'+$Version+'/vmm-multilingual-'+$Version+'.zip');last_updated=[DateTime]::UtcNow.ToString('yyyy-MM-dd HH:mm:ss');sha256=$hash;changelog='<p>Verbesserter visueller Editor mit gemeinsamem Entwurf, Live-Vorschau, Undo/Redo, Feldstatus und vollständiger Seitenrücksetzung. Menüübersetzungen und WYSIWYG-Bearbeitung.</p>'}
[IO.File]::WriteAllText((Join-Path $root 'releases/update.json'),($manifest|ConvertTo-Json -Depth 5),[Text.UTF8Encoding]::new($false))
Write-Output ('Built '+$out)
