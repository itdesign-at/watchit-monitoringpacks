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

$datacenters = Get-Datacenter

foreach ($datacenter in $datacenters) {
    $hosts = Get-Datacenter -Name $datacenter | Get-VM

    foreach ($vm in $hosts) {
        $VMGuest = Get-VMGuest -VM $vm.Name | Sort-Object -Property VMName
        $ht = [ordered]@{
                DataCenter  = $datacenter.Name
                GuestName   = $VMGuest.VMName
                VMStatus    = $VMGuest.VM.Guest.State.ToString()
                GuestOS     = $VMGuest.OSFullName
                ESXHost     = $VMGuest.VM.VMHost.Name
                Ram         = $VMGuest.VM.MemoryMB
                Cpus        = $VMGuest.VM.NumCPU
                ToolsStatus = $VMGuest.ExtensionData.ToolsStatus.ToString()
                }
         [array]$arrGuests += New-Object PSObject -Property $ht
    }
}

$arrGuests | ConvertTo-Json