Requires -Version 5.0
[CmdletBinding()]
param (
    [string]$VCenterServer = 'SERVER',
    [string]$User = 'USER',
    [string]$Password = 'PASSWORD'
    [string]$VMHost = ''
)

Write-Verbose "Importing VMWare PowerShell Module"
Import-Module Vmware.PowerCLI | Out-Null
Write-Verbose "Creating Credential Object"
$PWord = ConvertTo-SecureString -String $Password -AsPlainText -Force
$Credential = New-Object -TypeName System.Management.Automation.PSCredential -ArgumentList $User, $PWord
Write-Verbose "Connecting to VCenter server"
Connect-VIServer -server $VCenterServer -Credential $Credential | Out-Null
Write-Verbose "Getting all VMware guests"

$disks = Get-VMHost -Name $VMHost | Get-Datastore

Remove-Variable arrDisks -ErrorAction SilentlyContinue -Force
foreach ($disk in $disks) {
        $ht = [ordered]@{
                Datacenter              = $disk.Datacenter.Name
        Name            = $disk.Name
                Accessible              = $disk.Accessible.ToString()
                State                   = $disk.State.ToString()
                }
        [array]$arrDisks += New-Object PSObject -Property $ht
        }
$arrDisks | ConvertTo-Json
