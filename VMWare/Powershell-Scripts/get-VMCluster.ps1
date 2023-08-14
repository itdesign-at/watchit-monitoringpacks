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

$clusters = Get-Cluster
$clusterObjects = @()

foreach ($cluster in $clusters) {
    $clusterInfo = @{
        'Cluster' = $cluster.Name
        'ConnectedNodes' = @()
        'DisconnectedNodes' = @()
    }

    $hosts = Get-Cluster $cluster | Get-VMHost

    foreach ($vmhost in $hosts) {
        if ($vmhost.ConnectionState -eq 'connected') {
            $clusterInfo['ConnectedNodes'] += $vmhost.Name
        } elseif ($vmhost.ConnectionState -eq 'disconnected') {
            $clusterInfo['DisconnectedNodes'] += $vmhost.Name
        }
    }

    $clusterInfo['TolerateRemaining'] = $clusterInfo['ConnectedNodes'].Count

    $clusterObjects += New-Object -TypeName PSObject -Property $clusterInfo
}

$clusterObjects | ConvertTo-Json