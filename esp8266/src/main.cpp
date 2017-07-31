#include <time.h>
#include <ESP8266WiFi.h>
#include "./HTTPSClient/HTTPSClient.h"
#include "dst.h"

#define SENSOR_PIN D2
#define INTERVAL (30 * 1000)
#define TIMEOUT (5 * 60 * 1000)
#define WIFI_SSID "verschwoerhaus-legacy"
#define WIFI_PASSWORD "fixme"
#define DOOR_KEY "fixme"

boolean open = false;
String door_key = DOOR_KEY;
unsigned long previousMillis = 0;
void sendInfo(boolean state);

void setup()
{
  Serial.begin(115200);
  Serial.println();
  // Serial.setDebugOutput(true);

  pinMode(SENSOR_PIN, INPUT_PULLUP);

  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  Serial.print("Connecting");
  while (WiFi.status() != WL_CONNECTED)
  {
    delay(500);
    Serial.print(".");
  }
  Serial.println();

  Serial.print("Connected, IP address: ");
  Serial.println(WiFi.localIP());

  Serial.print("Setting time using SNTP");
  configTime(0, 0, "0.pool.ntp.org", "1.pool.ntp.org", "2.pool.ntp.org");
  time_t now = time(nullptr);
  while (now < 1000) {
    delay(500);
    Serial.print(".");
    now = time(nullptr);
  }
  Serial.print("Clock initialized to (UTC): ");
  Serial.println(ctime(&now));
}

void loop() {
  unsigned long currentMillis = millis();
  if (currentMillis - previousMillis >= TIMEOUT) {
    previousMillis = currentMillis;
    sendInfo(open);
  }

  if (digitalRead(SENSOR_PIN) == HIGH && !open) {
    open = true;
    sendInfo(true);
  } else if (digitalRead(SENSOR_PIN) == LOW && open) {
    open = false;
    sendInfo(false);
  }

  Serial.println(open);
  delay(INTERVAL);
}

void sendInfo(boolean state) {
  if (WiFi.status() != WL_CONNECTED) {
    ESP.restart();
    return;
  }

  HTTPSClient http;
  http.begin("https://verschwoerhaus.de/wp-json/open-door/state", DST_Root_CA_X3, DST_Root_CA_X3_len);
  http.addHeader("Content-Type", "application/json");

  String postMessage = "";
  if (state) {
    postMessage = "{\"open-door\":{\"state\":\"open\",\"key\":\"" + door_key + "\"}}";
    Serial.println("open");
  } else {
    postMessage = "{\"open-door\":{\"state\":\"closed\",\"key\":\"" + door_key + "\"}}";
    Serial.println("closed");
  }

  int httpCode = http.POST(postMessage);
  if (httpCode > 0) {
    Serial.printf("[HTTPS] POST: code: %d\n", httpCode);
    http.writeToStream(&Serial);
  } else {
    Serial.printf("[HTTPS] POST: failed, error: %s\n", http.errorToString(httpCode).c_str());
  }
  http.end();
}
