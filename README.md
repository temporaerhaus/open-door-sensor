# **open-door-sensor**

### Firmware for esp8266 to publish current state of door sensor to website

the `esp8266` folder contains the current version.
usage of the [platformio ide](http://platformio.org) is highly encouraged.

this project uses a newer version of ESP8266HTTPClient,
that is currently (2017-07-29) only available in the `espressif8266_stage` platform.

See: http://docs.platformio.org/en/latest/platforms/espressif8266.html#using-arduino-framework-with-staging-version

```
pio platform install https://github.com/platformio/platform-espressif8266.git#feature/stage
```
