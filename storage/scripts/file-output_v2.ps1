# C:/Users\o\.vscode\react\m-project\storage\scripts\file-output_v2.ps1 -Path "resources/js/Pages/3_M_TargetSelector.jsx"

# C:\Users\o\.vscode\react\m-project\storage\scripts\file-output_v2.ps1 -Path "C:\Users\o\.vscode\react\m-project\resources\js\Pages\0_M_Dashboard.jsx"

# C:\Users\o\.vscode\react\m-project\storage\scripts\file-output_v2.ps1 -Path "C:\Users\o\.vscode\react\m-project\resources\css\0_M_UI.css"

# C:\Users\o\.vscode\react\m-project\storage\scripts\file-output_v2.ps1 -Path "C:\Users\o\.vscode\react\m-project\resources\js\Components\3_M_Sync.jsx"

param (
    [string]$Path = "resources/js/Components",
    [string]$OutputFile = "SingleSourceCode.txt"
)

if (Test-Path $OutputFile) { Remove-Item $OutputFile }

if ((Get-Item $Path).PSIsContainer) {
    $folders = @($Path)
}
else {
    $folders = @()
    if (Test-Path $Path) {
        Add-Content $OutputFile "`n--- FILE: $Path ---`n"
        Get-Content $Path | Add-Content $OutputFile
        Add-Content $OutputFile "`n"
    }
}

foreach ($folder in $folders) {
    if (Test-Path $folder) {
        Add-Content $OutputFile "`n--- FOLDER: $folder ---`n"
            Get-ChildItem -Path $folder -Recurse -Include *.php, *.js, *.jsx | ForEach-Object {
            Add-Content $OutputFile "`n[FILE: $($_.FullName)]`n"
            Get-Content $_.FullName | Add-Content $OutputFile
            Add-Content $OutputFile "`n"
        }
    }
}

Write-Host "Done! Source code exported to $OutputFile" -ForegroundColor Green