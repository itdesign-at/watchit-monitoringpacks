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
Write-Verbose "Getting all VMware guests"
#$datastores = Get-Datastore | Sort Name

$datastores = Get-Datastore -Name * | Select-Object -Property Name,Type,CapacityMB,FreeSpaceMB,Accessible | Sort Name


foreach ($datastore in $datastores) {
        $ht = [ordered]@{
          Name            = $datastore.Name
          Freespace       = $datastore.FreeSpaceMB
          Capacity        = $datastore.CapacityMB
          Accessible      = $datastore.Accessible
        }
        [array]$arr += New-Object PSObject -Property $ht
}
$arr | ConvertTo-Json
