#Requires -Version 5.0
[CmdletBinding()]
param (
    [string]$VCenterServer = 'SERVER',
    [string]$User = 'USER',
    [string]$Password = 'PASSWORD'
)

Write-Verbose "Importing VMWare PowerShell Module"
Import-Module VMware.PowerCLI | Out-Null
Write-Verbose "Creating Credential Object"
$PWord = ConvertTo-SecureString -String $Password -AsPlainText -Force
$Credential = New-Object -TypeName System.Management.Automation.PSCredential -ArgumentList $User, $PWord
Write-Verbose "Connecting to vCenter server"
Connect-VIServer -Server $VCenterServer -Credential $Credential | Out-Null
Write-Verbose "Getting all VMware clusters"


foreach($dc in Get-Datacenter){
    foreach($cluster in Get-Cluster -Location $dc){
             foreach($esx in Get-VMHost -Location $cluster | Get-View){
                $overallStatus = $esx.OverallStatus
                $ht = [ordered]@{
                 DataCenter  = $dc.Name
                 EsxName     = $esx.Name
                 OverallStatus = "$overallStatus"
                }
                [array]$esxInfos += New-Object PSObject -Property $ht
             }
    }
}

$esxInfos | ConvertTo-Json
