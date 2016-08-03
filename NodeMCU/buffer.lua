--- @module open-door.Buffer
local Buffer = {}

function Buffer.new()
  local self = setmetatable({}, Buffer)
  self.data = ""
  return self
end

-- the : syntax here causes a "self" arg to be implicitly added before any other args
function Buffer:append(data)
  self.data = self.data..data
end

function Buffer:get()
  return self.data or ""
end

function Buffer:append_until(data, delimiter)
    if(data==nil) then return nil end
    local i,j = data:find(delimiter)
    if(self.data==nil) then self.data="" end
    if(i==nil) then return nil 
    else
        self.data = self.data..data:sub(0,j-1)
        return data:sub(j+1);
    end
end

local metatable = {
    __call = function()
        local self = {}
        setmetatable(self, { __index = Buffer })
        return self
    end
}

setmetatable(Buffer, metatable)
return Buffer