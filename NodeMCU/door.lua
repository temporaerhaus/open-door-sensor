local Door = {}
require("client")

Door.pin = 01
gpio.mode(Door.pin, gpio.INPUT, gpio.PULLUP)
Door.curstate = "undetermined"

function Door.getstate()   
    state = gpio.read(Door.pin)
    if(state==1) then return "closed"
    else return "open" end
end

function Door.setstate(state)
    Door.curstate = state
    print("Door real state: "..Door.curstate)
    print(node.heap())
    Client.put(state, function(webstate)
        print("Door web state: "..webstate)
        print(node.heap())
    end)
end

function Door.stop()
    if( tmr.state(6) ~=nil ) then
        running, mode = tmr.state(6)
        if(running==true) then tmr.stop(6) end
        tmr.unregister(6)            
    end
end

function Door.start(delay)
    Door.stop()
    print("Ich starte den Tür Sensor")
    print(node.heap())
    tmr.alarm(6, delay*1000 or 3000, tmr.ALARM_AUTO, function()
        local state = Door.getstate()
        collectgarbage()
        if(state ~= Door.curstate) then
            Door.setstate(state)       
        end 
    end)
end

Door.start(3)

return Door

