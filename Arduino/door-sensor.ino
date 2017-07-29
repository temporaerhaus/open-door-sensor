#include <ESP8266HTTPClient.h>
#include <ESP8266WiFi.h>

boolean open = false;

void setup() {

Serial.begin(115200);
WiFi.begin("verschwoerhaus-legacy", "initiative.ulm.digital.2016");

while (WiFi.status() != WL_CONNECTED)

delay(500);
Serial.println("Waiting for connection");

pinMode(D2, INPUT_PULLUP);

}

void loop() {

if(digitalRead(D2) == HIGH && !open){
  open = true;
  sendInfo(true);
}else if(digitalRead(D2) == LOW && open){
  open = false;
  sendInfo(false);
}

Serial.println(open);
delay(500); //Send a request every 30 seconds

}

void sendInfo(boolean state){
  if(WiFi.status()== WL_CONNECTED){ //Check WiFi connection status

  HTTPClient http;
  http.begin("https://verschwoerhaus.de/wp-json/open-door/state" , "cb c9 b2 3c e2 53 43 59 46 d6 16 e5 c6 cc e0 94 4c 43 a5 2e");
  http.addHeader("Content-Type", "application/json");
  String postMessage = "";
  if(state){
    postMessage = "{\"open-door\":{\"state\":\"open\"}}";
    Serial.println("open");
  }else{
    postMessage = "{\"open-door\":{\"state\":\"closed\"}}";
    Serial.println("closed");
  }
  int httpCode = http.POST(postMessage);
  Serial.print("http result:");
  Serial.println(httpCode);
  http.writeToStream(&Serial);
  String payload = http.getString();
  http.end();
  
  }else{
  
  Serial.print("Error in Wifi connection");
  
  }
}
