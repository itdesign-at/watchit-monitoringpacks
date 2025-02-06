# Import the Active Directory module
Import-Module ActiveDirectory

# Get all locked-out users
$lockedUsers = Search-ADAccount -LockedOut -Server DC01 | Select-Object SamAccountName

# Convert to JSON format
$jsonOutput = $lockedUsers | ConvertTo-Json -Depth 2

# Output the JSON string without extra newline characters
Write-Output $jsonOutput

