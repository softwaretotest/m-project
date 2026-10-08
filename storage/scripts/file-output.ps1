$folders = @("app/Constant", "app/Http/Controllers", "resources")
# $folders = @("resources")
$outputFile = "ProjectSourceCode.txt"

# ล้างไฟล์เดิมถ้ามี
if (Test-Path $outputFile) { Remove-Item $outputFile }

foreach ($folder in $folders) {
    if (Test-Path $folder) {
        Add-Content $outputFile "`n--- FOLDER: $folder ---`n"
        
        # ดึงไฟล์ที่ต้องการโดยครอบคลุมทั้ง PHP, JS และ JSX
        Get-ChildItem -Path $folder -Recurse -Include *.php, *.js, *.jsx | ForEach-Object {
            Add-Content $outputFile "`n[FILE: $($_.FullName)]`n"
            Get-Content $_.FullName | Add-Content $outputFile
            Add-Content $outputFile "`n"
        }
    }
}
Write-Host "เรียบร้อย! ข้อมูลถูกรวมไฟล์ทั้ง Backend และ Frontend ไว้ที่ $outputFile แล้วครับ"