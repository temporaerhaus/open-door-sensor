NetHttp = {}

function to_string(table, s, n)
    local s= s or ""
    local n = n or -1
    local pad=""
    for i=0,n do
        pad=pad.."  "
    end
    for k, v in pairs( table ) do
       if (type(v) == "table") then
        s=s..pad.."["..k.."]= \r\n"..pad.."{ \r\n"..to_string(v, s, n+1).."\r\n"..pad.."}\r\n"
       else
        s=s..pad.."["..k.."]="..v.."\r\n"
       end
    end
    return s
end

function NetHttp.resolve(hostname, callback)
    print("resolving DNS...") 
    net.dns.resolve(hostname, function(sk,ip)
        if(ip == nil) then 
            print("DNS FAILED") 
        else 
            print("resolved") 
            print(ip); 
            callback(ip)
        end
    end)
end

function NetHttp.parse_headers(res)
    local headers = {}
    local firstline= true
    for line in res:gmatch("[^\r\n]+") do 
        --print(line)         
        if(firstline) then
            -- special first line with status
            firstline = false
            local id=1
            local names = {'Protocol', 'Status', 'Status-String', 'dumm', 'dumm2'}
            for word in line:gmatch("%S+") do                 
                headers[names[id]] = word                
                id=id+1
            end            
            --print(NETHTTP.to_string(headers))
        else
            -- the rest
            local i = line:find(":")        
            if(i~=nil) then
                local name = line:sub(0,i-1)
                local value = line:sub(i+2)
                --print("HEADER "..name..":"..value)
                headers[name] = value
            end  
        end      
    end
    --print(to_string(headers))
    return headers
end



function NetHttp.read_chunk(data, callback)
    local i,j = data:find("\r\n")
    --print("READING CHUNK: \r\n"..data)
    if(i==nil) then print("INVALID CHUNK!") return "" end
    chunklength = tonumber(data:sub(0,i-1))
    if(chunklength == 0) then 
        --print("END OF TRANSMISSION FOUND")
        callback()
    end
    return data:sub(j+1)
end



function NetHttp.requestip(ip, request, callback)    
    local conn=net.createConnection(net.TCP, 0) 
    local headers=nil
    local buf=""
    local chunked=false
    conn:on("connection", function()                
        conn:send(request)             
      end)
    conn:on("receive", function(conn, res) 
        if(res==nil) then print("----EMPTY PACKET!! BAD!!----"); return end
        --print("received in http:\nPACKET--------------------------------\n"..res)       
        if(headers==nil) then -- read headers first in one chunk
            print("header empty")
            local i,j = string.find(res, "\r\n\r\n")    -- find 2 newlines
            local head = res:sub(0,i-1)
            res = res:sub(j+1)
            --print("HEAD: -----\r\n"..head.."\r\nBODY:-----\r\n"..res)
            headers = NetHttp.parse_headers(head)    
            if(headers["Transfer-Encoding"] ~= nil) then
                chunked = headers["Transfer-Encoding"]=='chunked'
                --if(chunked) then print("Chunked encoding") end
            end
            if(headers["Set-Cookie"] ~= nil) then
                cookie = string.sub(headers["Set-Cookie"]:match("PHPSESSID=%w+;"),0,-2)
                print("Storing cookie monster! "..cookie)
                NetHttp.cookie = cookie
            end
        end
        if(res:len() ~= 0 and chunked) then
            --print("appendign chunk")
            buf = buf..NetHttp.read_chunk(res, function()
                callback(headers, buf)
            end)
        else
            callback(headers, res)
        end
    end)    
    conn:connect(80,ip)            
end

function NetHttp.build_request(host, resource, method, headers, body)
    local method = method or "GET"
    local resource = resource or "/"
    local host = host or ip
    local body = body or ""
    local headers = headers or "Connection: keep-alive\r\nAccept: application/json"
    if(NetHttp.cookie ~= nil) then
        print("Can has Cookie Monster: "..NetHttp.cookie)
        headers = headers.."\r\nCookie: "..NetHttp.cookie;
    end
    local request = ""..method.." "..resource.." ".."HTTP/1.1\r\n"
                .."Host: "..host.."\r\n"
                ..headers.."\r\n\r\n"
                ..body.."\r\n\r\n"
    print("REQ:\n"..request)
    return request
end

function NetHttp.request_ip(ip, host, resource, method, headers, body, callback)
    return NetHttp.requestip(ip, NetHttp.build_request(host, resource, method, headers, body), callback)
end 

function NetHttp.request(host, resource, method, headers, body, callback)
    NetHttp.resolve(host, function(ip)
        if(ip==nil) then print("Unknown Host!") return nil
        else return NetHttp.request_ip(ip, host, resource, method, headers, body, callback) end
    end)
end    


--------------------------------- UNIT TESTS ------------------------------

function test_get(ip)
    NetHttp.request_ip(ip, "weinhof9.de", "/wp-json/open-door/state/", "GET", nil, nil, function(headers, body)
         print("GOT:\r\nHEADERS:\r\n"..to_string(headers).."\r\nBODY:\r\n"..(body or "NIX"))
         tmr.alarm(0, 500, tmr.ALARM_SINGLE, function() 
                 print("POSTING")
                 test_post(ip)
         end)         
    end)
end

function test_post(ip)
    local body = '{"open-door":{"state":"Hello World!","body":"This is my first post!"}}'
    local headers = "Connection: keep-alive"
        .."\r\nAccept: application/json"
        .."\r\nContent-Type: application/json"
        .."\r\nContent-Length: "..body:len()
    --headers = "Host: weinhof9.de\r\nAccept: application/json\r\nContent-Type: application/json\r\nContent-Length: 57\r\nConnection: keep-alive\r\n"
    NetHttp.request_ip(ip, "weinhof9.de", "/wp-json/open-door/state/", "POST", headers, body, function(headers, body)
        local headers = headers or {}
        local body = body or "NIX"
        print("DONE:\r\nHEADERS:\r\n"..to_string(headers).."\r\nBODY:\r\n"..(body or "NIX"))
    end)
end    

--NetHttp.resolve("weinhof9.de", function(ip)
    --test_get(ip)
--end)

--------------------------------- /UNIT TESTS -----------------------------

return NetHttp
