#Requires -Version 5.0
[CmdletBinding()]
param (
    [string]$VCenterServer = 'SERVER',
    [string]$User = 'USER',
    [string]$Password = 'PASSWORD'
)

Write-Verbose "Importing VMWare PowerShell Module"
Import-Module Vmware.PowerCLI | Out-Null
Write-Verbose "Creating Credential Object"
$PWord = ConvertTo-SecureString -String $Password -AsPlainText -Force
$Credential = New-Object -TypeName System.Management.Automation.PSCredential -ArgumentList $User, $PWord
Write-Verbose "Connecting to VCenter server"
Connect-VIServer -server $VCenterServer -Credential $Credential | Out-Null
Write-Verbose "Getting all VMware snapshots"

$datacenters = Get-Datacenter

foreach ($datacenter in $datacenters) {
    $hosts = Get-Datacenter -Name $datacenter | Get-VM

    foreach ($vm in $hosts) {
        $snapshot = Get-Snapshot -VM $vm.Name | Select @{Label = "VM"; Expression = {$_.VM}}, @{Label = "Snapshot Name";Expression = {$_.Name}}, @{Label = "Created Date"; Expression = {$_.Created}} , @{Label = "S
napshot Size"; Expression = {$_.SizeGB}}
        if ($snapshot -And $snapshot -isnot [array]) {
                $ht = [ordered]@{
                        DataCenter  = $datacenter.Name
                        GuestName   = $vm.Name
                        Snapshots   = $snapshot
                }
                [array]$arr += New-Object PSObject -Property $ht
        }
        if ($snapshot -And $snapshot -is [array]) {
                foreach ($snap in $snapshot) {
                        $ht = [ordered]@{
                        DataCenter  = $datacenter.Name
                        GuestName   = $vm.Name
                        Snapshots   = $snap
                }
                [array]$arr += New-Object PSObject -Property $ht
                }
                #$ht = [ordered]@{
                #        DataCenter  = $datacenter.Name
                #        GuestName   = $vm.Name
                #        Snapshots   = $snapshot
                #}
                #[array]$arr += New-Object PSObject -Property $ht
        }
    }
}

$arr | ConvertTo-Json
