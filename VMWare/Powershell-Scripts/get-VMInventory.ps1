#Requires -Version 5.0
[CmdletBinding()]
param (
    [string]$VCenterServer = 'SERVER',
    [string]$User = 'USER',
    [string]$Password = 'PASSWORD'
    [string]$Datacenter = ''
)


Write-Verbose "Importing VMWare PowerShell Module"
Import-Module Vmware.PowerCLI | Out-Null
Write-Verbose "Creating Credential Object"
$PWord = ConvertTo-SecureString -String $Password -AsPlainText -Force
$Credential = New-Object -TypeName System.Management.Automation.PSCredential -ArgumentList $User, $PWord
Write-Verbose "Connecting to VCenter server"
Connect-VIServer -server $VCenterServer -Credential $Credential | Out-Null
Write-Verbose "Getting all VMware guests"


$VMGuests = Get-Datacenter -Name $Datacenter | Get-VM| Get-VMGuest | Sort-Object -Property VMName

Remove-Variable arrGuests -ErrorAction SilentlyContinue -Force
foreach ($VMGuest in $VMGuests) {
        $ht = [ordered]@{
                GuestName               = $VMGuest.VMName
                VMStatus                = $VMGuest.VM.Guest.State.ToString()
                GuestOS                 = $VMGuest.OSFullName
                ESXHost                 = $VMGuest.VM.VMHost.Name
                Ram                             = $VMGuest.VM.MemoryMB
                Cpus                    = $VMGuest.VM.NumCPU
                ToolsStatus             = $VMGuest.ExtensionData.ToolsStatus.ToString()
                }
        [array]$arrGuests += New-Object PSObject -Property $ht
        }
$arrGuests | ConvertTo-Json
