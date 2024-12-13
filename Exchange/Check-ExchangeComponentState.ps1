param (
    [Parameter(Mandatory = $True)][String]$Computername = 'exc01.mydomain.at'
)

$PSSessionOption = New-PSSessionOption -NoMachineProfile -OpenTimeout 15000 #15 seconds timeout
$Session = New-PSSession -Name ExchangeOnPrem -ConfigurationName Microsoft.Exchange -ConnectionUri ('http://{0}/PowerShell/' -f $Computername ) -Authentication Kerberos -SessionOption $PSSessionOption
Import-PSSession -Session $Session -DisableNameChecking | Out-Null


$ServerComponentStates = Get-ServerComponentState -Identity $Computername
ConvertTo-Json -InputObject $ServerComponentStates


Remove-PSSession -Session $Session
