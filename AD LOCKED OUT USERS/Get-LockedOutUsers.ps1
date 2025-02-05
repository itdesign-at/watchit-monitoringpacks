# Import the Active Directory module
Import-Module ActiveDirectory

# Get all locked-out users from the specified server with the provided credentials
$lockedUsers = Search-ADAccount -LockedOut -Server domaincontrollerhiereinfügen | Select-Object Name, SamAccountName

# Convert to JSON format and output
if ($lockedUsers) {
    $lockedUsers | ConvertTo-Json -Depth 2
} else {
    Write-Output "[]"
}
