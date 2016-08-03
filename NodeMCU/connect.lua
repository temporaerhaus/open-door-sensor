local WLAN = {}

function notnil(k)
  if k == nil then return "nil" end
  return "".. k
end

function WLAN.status()
    --Get current Station configuration
    local ssid, password, bssid_set, bssid = wifi.sta.getconfig() 
    ip = wifi.sta.getip()  
    print("\nWifi config:"
    .."\nSSID : "..notnil(ssid)
    .."\nBSSID: "..notnil(bssid)
    .."\nas Hostname: "..notnil(wifi.sta.gethostname())
    .."\nIP: "..notnil(wifi.sta.getip())
    .."\nSignal Strength: "..notnil(wifi.sta.getrssi()))
    password, bssid_set, bssid=nil, nil, nil
    if(ip==nil) then return nil
    else return ssid
    end
end

WLAN.cur_ssid = nil

function WLAN.connectTo(SSID, pwd, on_connected)   
    if(wifi.sta.getip() ~= nil) then
        if(WLAN.cur_ssid ~= nil and WLAN.cur_ssid == SSID) then
            on_connected(wifi.sta.getip())
            return
        else
            if(WLAN.cur_ssid~=nil) then
                print("Disconnecting from "..WLAN.cur_ssid) end
            wifi.sta.disconnect()
        end
    end
    
    print("Connecting to "..SSID)
    print("with keyphrase: ******** just kidding")
    
    --wifi.sta.config(SSID, pwd)
    
     
    if wifi.sta.getip() == nil then
        wifi.setmode(wifi.STATION)
        wifi.sta.config(SSID, pwd)
        wifi.sta.connect()
        tmr.alarm(1, 1000, 1, function()
             if wifi.sta.getip() == nil then
                 print("Connecting...")
             else
                 tmr.stop(1)
                 WLAN.cur_ssid = SSID
                 WLAN.status()
                 on_connected(wifi.sta.getip())
             end
        end)
    end
end
return WLAN