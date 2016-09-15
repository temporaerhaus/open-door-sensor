local Door = {}
require("client")

Door.pin = 01
gpio.mode(Door.pin, gpio.INPUT, gpio.PULLUP)
Door.curstate = "undetermined"
Door.lastcheckin = 0
Door.check_interval = 3000
Door.checkin_interval = 1000
Door.remote_timeout = 5

function Door.getstate()   
    local state = gpio.read(Door.pin)
    if(state==1) then return "open"
    else return "closed" end
end

function Door.setstate(state)
    Door.curstate = state
    print("Door real state: "..Door.curstate)
    print(node.heap())
    Client.put(state, function(webstate, timeout)
        print("Server want regular posts with interval: "..timeout)
        Door.remote_timeout = timeout/2;
        Door.checkin_interval = (Door.remote_timeout*60*1000)/Door.check_interval;
        print("Door web state: "..webstate.." timeout: "..timeout)
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

function Door.check_state()
    local state = Door.getstate() 
    print(node.heap())
    collectgarbage()  
    print(node.heap()) 
    print(Door.curstate.. " -> "..state.." lastcheckin: "..Door.lastcheckin.. " interval: "..Door.checkin_interval.." timeout: "..Door.remote_timeout)
    Door.lastcheckin = Door.lastcheckin+1;
    if(state ~= Door.curstate or (Door.lastcheckin>=Door.checkin_interval)) then
        Door.lastcheckin=0
        Door.setstate(state)       
    end 
end

function Door.start(delay)
    Door.stop()
    print("Ich starte den TÃ¼r Sensor")
    print(node.heap())
    local interval = delay or 3  
    -- 8 minuten * 60 s * 1000 ms = 480.000 millisekunden, / intervall = 3000 millisekunden, = jeder 160te check
    Door.check_interval = interval*1000
    tmr.alarm(6, Door.check_interval, tmr.ALARM_AUTO, Door.check_state)
end

--Door.start(3)

return Door

