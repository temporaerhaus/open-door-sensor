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

Client.Request = nil

function Client.parse_initial_response(headers,body)
    print("GOT:\r\nHEADERS:\r\n"..to_string(headers).."\r\nBODY:\r\n"..(body or "NIX"))
    --extract nonce from response
    Client.Request.nonce=""
    local ok, t = pcall(cjson.decode, body)
    if ok and t["open-door"] ~=nil and t["open-door"]["nonce"] ~= nil then
        Client.Request.nonce = t["open-door"]["nonce"]
    else
        print("Got INVALID JSON (from GET): "..body)
        return
    end
    --Client.Request.body = t
    t=nil
    Client.post_state()
end

function Client.post_state()
    print("POSTING")            
    --encode values table to json
    Client.Request.values["open-door"]["nonce"] = Client.Request.nonce
    Client.Request.values["open-door"]["signature"] = Client.sign(Client.Request.values["open-door"])
    local ok, body = pcall(cjson.encode, Client.Request.values)
    if(ok==false) then
        print("Error encoding table to JSON!")
        return
    end      
    Client.Request.body = body
    body = nil           
    --local body = '{"open-door":{"state":"'..value..'","body":"This is my first post!"}}'
    Client.Request.headers = "Connection: keep-alive"
        .."\r\nAccept: application/json"
        .."\r\nContent-Type: application/json"
        .."\r\nContent-Length: "..Client.Request.body:len()
    --headers = "Host: weinhof9.de\r\nAccept: application/json\r\nContent-Type: application/json\r\nContent-Length: 57\r\nConnection: keep-alive\r\n"
    NetHttp.request_ip(Client.ip, Client.host, Client.endpoint, "POST", Client.Request.headers, Client.Request.body, Client.parse_final_response)
end


function Client.parse_final_response(headers, body)
    local headers = headers or {}
    local body = body or "NIX"
    print("DONE:\r\nHEADERS:\r\n"..to_string(headers).."\r\n")
    print("BODY:\r\n"..(body or "NIX"))
    local ok, t = pcall(cjson.decode, body)
    if ok then
        --for k,v in pairs(t) do print(k,v) end
        Client.Request.values = t
        t=nil
        Client.set()
    else
        Client.Request.values = nil
        t= nil
        print("Got INVALID JSON: "..body)
    end
    
end


function Client.set()
    if(Client.Request.values["open-door"]~= nil) then
        if(Client.Request.values["open-door"]["state"]~=nil) then
            Client.state = Client.Request.values["open-door"]["state"]          
        end
        if(Client.Request.values["open-door"]["timeout"]~=nil) then
            Client.timeout = Client.Request.values["open-door"]["timeout"] 
        else
            Client.timeout = 5
        end        
        cb = Client.Request.callback;
        Client.Request = nil
        if(cb ~= nil) then cb(Client.state, Client.timeout) end
        return
    end
    print("Error JSON response is no open-door Object!")
    print(to_string(values))
end




function Client.put_ip()   
    -- fire the get request to retrieve a nonce
    NetHttp.request_ip(Client.ip, Client.host, Client.endpoint, "GET", nil, nil, Client.parse_initial_response) 
end    




function Client.put(new_state, callback)
    Client.Request = {}
    Client.Request.values = { 
        ["open-door"] = {
            state = new_state,         
        }   
    }
    Client.Request.callback = callback
    if(Client.ip == nil) then
        NetHttp.resolve(Client.host, function(ip)
            Client.ip  = ip
            Client.put_ip()
        end)
    else  
        Client.put_ip() 
    end    
end


--Client.put("testing", function(new_state)
    --print("SUCCESS: set state to "..new_state)
--end)

return Client
