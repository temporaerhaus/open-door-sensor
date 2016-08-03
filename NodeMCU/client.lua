Client = {}
require("nethttp")

--secret.lua contains the key shared between client and server only
require("secret")

Client.host= "weinhof9.de"
Client.endpoint = "/wp-json/open-door/state/"
Client.ip = nil
Client.state = "unknown"
Client.key = Secret.key

function Client.sign(values)
    local nonce = values["nonce"]
    local state = values["state"]
    local key = Client.key
    if( state==nil or nonce==nil or key==nil ) then
        print("ERROR signing request, NIL value found!")
        return ""
    end    
    print("SIGNING: "..nonce..state..key);
    return (crypto.toHex(crypto.hash("sha256",nonce..state..key)))    
end

function Client.put_ip(ip, values, callback)   
    -- fire the get request to retrieve a nonce
    NetHttp.request_ip(ip, Client.host, Client.endpoint, "GET", nil, nil, function(headers, body)
         --print("GOT:\r\nHEADERS:\r\n"..to_string(headers).."\r\nBODY:\r\n"..(body or "NIX"))
         --extract nonce from response
         local nonce=""
          local ok, t = pcall(cjson.decode, body)
            if ok and t["open-door"] ~=nil and t["open-door"]["nonce"] ~= nil then
                nonce = t["open-door"]["nonce"]
            else
                print("Got INVALID JSON (from GET): "..body)
            end
         --wait a short time until on:receive callback returned         
         tmr.alarm(0, 500, tmr.ALARM_SINGLE, function() 
                collectgarbage()
                --print("POSTING")            
                --encode values table to json
                values["open-door"]["nonce"] = nonce
                values["open-door"]["signature"] = Client.sign(values["open-door"])
                local ok, body = pcall(cjson.encode, values)
                if(ok==false) then
                    print("Error encoding table to JSON!")
                    return
                end                 
                --local body = '{"open-door":{"state":"'..value..'","body":"This is my first post!"}}'
                local headers = "Connection: keep-alive"
                    .."\r\nAccept: application/json"
                    .."\r\nContent-Type: application/json"
                    .."\r\nContent-Length: "..body:len()
                --headers = "Host: weinhof9.de\r\nAccept: application/json\r\nContent-Type: application/json\r\nContent-Length: 57\r\nConnection: keep-alive\r\n"
                NetHttp.request_ip(ip, Client.host, Client.endpoint, "POST", headers, body, function(headers, body)
                    local headers = headers or {}
                    local body = body or "NIX"
                    --print("DONE:\r\nHEADERS:\r\n"..to_string(headers).."\r\n")
                    --print("BODY:\r\n"..(body or "NIX"))
                    local ok, t = pcall(cjson.decode, body)
                    if ok then
                        --for k,v in pairs(t) do print(k,v) end
                        Client.set(t, callback)
                    else
                        print("Got INVALID JSON: "..body)
                    end
                end)
         end)         
    end)
end

function Client.set(values, callback)
    if(values["open-door"]~= nil) then
        if(values["open-door"]["state"]~=nil) then
            Client.state = values["open-door"]["state"]
            tmr.alarm(0, 500, tmr.ALARM_SINGLE, function() 
                collectgarbage()
                callback(Client.state)
            end)
            return
        end
    end
    print("Error JSON response is no open-door Object!")
    print(to_string(values))
end

function Client.put(new_state, callback)
    local state_values = { ["open-door"] = {
        state = new_state,         
    }   }
    if(Client.ip == nil) then
        NetHttp.resolve(Client.host, function(ip)
            Client.ip  = ip
            Client.put_ip(Client.ip , state_values, callback)
        end)
    else  
        Client.put_ip(Client.ip , state_values, callback) 
    end    
end


--Client.put("testing", function(new_state)
    --print("SUCCESS: set state to "..new_state)
--end)

return Client
