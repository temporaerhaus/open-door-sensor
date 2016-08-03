--init.lua 
print("Willkommen im Weinhof9")
print(node.heap())

--secret.lua contains the SSID of the Wifi to connect to as well as the passphrase
require("secret")
require("connect")
WLAN.connectTo(Secret.SSID, Secret.pass, function()
    dofile("door.lua")
end)
