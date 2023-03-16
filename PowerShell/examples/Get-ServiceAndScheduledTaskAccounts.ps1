$ExportPath = 'C:\Temp\ServiceAccounts'
$ExludedServiceAccount = @('LocalSystem','NT AUTHORITY\NetworkService','NT AUTHORITY\LocalService','NT Service\MSSQLSERVER','SYSTEM','LOCAL SERVICE','NT AUTHORITY\SYSTEM','$null','NT AUTHORITY\LOCAL SERVICE')
$ExludedTaskName = @("Optimize Start Menu Cache Files*","User_Feed*")
$PwdRange = (Get-Date).AddDays(-90).ToFileTime()
$FailedServers = @()

$Computers = New-Object -TypeName System.Collections.ArrayList
[System.Collections.ArrayList]$Computers = Get-ADDomainController -Filter * | Sort-Object -Property HostName
# $Computers = $Computers | Get-Random -Count 500

for ($i = 0; $i -lt $Computers.Count; $i++)
{ 
    $Action = 'Test WSMan Connection to Computer: {0}' -f $Computers[$i].HostName
    $ActionExecInfo = '{0:D4}/{1}' -f $i, $Computers.Count
    Write-Progress -Activity $Action -Status $ActionExecInfo -PercentComplete ((($i + 1 )/ $Computers.Count) * 100)

    If ([string]::IsNullOrEmpty($Computers[$i].HostName)) {$Computers.RemoveAt($i); $i--; Continue}
    
    If ( Test-Connection -Count 1 -Quiet -ComputerName $Computers[$i].HostName ) {
        If ( Test-WSMan -ComputerName $Computers[$i].HostName -ErrorAction SilentlyContinue ) {Continue}
    }
    
    $Computers.RemoveAt($i)
    $i--
}

foreach ($s in $Computers) {

    $StopWatch = [System.Diagnostics.Stopwatch]::StartNew()
    Write-Host $('{0}: ' -f $s.HostName) -NoNewline

    If (-not (Test-Connection -Protocol WSMan -ComputerName $s.HostName -Count 1 -Quiet)) {
        Write-Host 'failed' -ForegroundColor Red
        $FailedServers += $s
        Continue
    }

    Try {
        $CimSession = New-CimSession -ComputerName $s.HostName -ErrorAction Stop
    } Catch {
        Write-Host 'failed' -ForegroundColor Red
        $FailedServers += $s
        Continue
    }

    $Services = Get-CimInstance -ClassName Win32_Service -CimSession $CimSession | Where {$ExludedServiceAccount -notcontains $_.StartName}    
    $ScheduledTasks = Get-ScheduledTask -CimSession $CimSession | Where {$ExludedServiceAccount -notcontains $_.Principal.UserId}

    $FullServicesExportPath = (Join-Path -Path $ExportPath -ChildPath $('Services\{0}.csv' -f $s.HostName))
    If (Test-Path -Path $FullServicesExportPath) {Remove-Item -Path $FullServicesExportPath -Force}
    $Services | Select-Object -Property PSComputerName, Name, StartMode, State, StartName | Export-Csv -Path $FullServicesExportPath -Delimiter ';' -NoClobber -NoTypeInformation

    $FullTasksExportPath = (Join-Path -Path $ExportPath -ChildPath $('Tasks\{0}.csv' -f $s.HostName))
    If (Test-Path -Path $FullTasksExportPath) {Remove-Item -Path $FullTasksExportPath -Force}
    $ScheduledTasks | Select-Object -Property PSComputerName,TaskPath, Taskname, {$_.Principal.UserId} | Export-Csv -Path $FullTasksExportPath -Delimiter ';' -NoClobber -NoTypeInformation

    Remove-CimSession -CimSession $CimSession

    $StopWatch.Elapsed.TotalSeconds
}

Get-ChildItem -Path (Join-Path -Path $ExportPath -ChildPath 'Services') -Filter '*.csv' | Select-Object -ExpandProperty FullName | Import-Csv | Export-Csv -Path (Join-Path -Path $ExportPath -ChildPath 'Services_Merged.csv') -Delimiter ';' -NoClobber -NoTypeInformation
Get-ChildItem -Path (Join-Path -Path $ExportPath -ChildPath 'Tasks') -Filter '*.csv' | Select-Object -ExpandProperty FullName | Import-Csv | Export-Csv -Path (Join-Path -Path $ExportPath -ChildPath 'Tasks_Merged.csv') -Delimiter ';' -NoClobber -NoTypeInformation

