# Intro
Read disk or memory data from STDIN. Input data must be a valid json array with
"Description" and geometry filled - see example below.

The minimum required fields for one entry are "Description" and two parameters which
allow to calc the rest. 

Example:

    {
        "Description": "/usr",
        "Free": 2701332992,
        "Size": 83768320000
    },

# Usage example

    # cat /tmp/disk-data.json | ./disk_mem_stdin.php -h host.demo.at -k disk -s 'disk-usage'
    <plugin output here>

    # cat /tmp/disk-data.json 
    [
        {
            "Description": "/",
            "Free": 2501332992,
            "FreePercent": 66,
            "Size": 3768320000,
            "Summary": "Size:3.51GB Used:1.18GB(34%) Free:2.33GB(66%)",
            "Used": 1266987008,
            "UsedPercent": 34
        },
        {
            "Description": "/boot",
            "Free": 20953088,
            "FreePercent": 49,
            "Size": 42857472,
            "Summary": "Size:40.9MB Used:20.9MB(51%) Free:20.0MB(49%)",
            "Used": 21904384,
            "UsedPercent": 51
        }
    ]
