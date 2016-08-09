--init.lua 
print("Willkommen im Weinhof9")
print(node.heap())

--secret.lua contains the SSID of the Wifi to connect to as well as the passphrase
Secret = require("secret")
WLAN = require("connect")
Door = require("door")
print(node.heap())
WLAN.connectTo(Secret.SSID, Secret.pass, function()
    Door.start(3)
    --dofile("door.lua")
    --print(node.heap())
    --dofile("nethttp.lua")
end)
