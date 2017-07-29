#ifndef HTTPS_CLIENT_H
#define HTTPS_CLIENT_H

// works with ESP8266HTTPClient.cpp/h from
// commit f05ed6e27d7c170574b25dd5ff76b0dcd972fa7e
#include <ESP8266HTTPClient.h>

class HTTPSClient : public HTTPClient
{
public:
    bool begin(String uri, const uint8_t* caCert, const size_t caCertLen);
    bool begin(String host, uint16_t port, String uri, const uint8_t* caCert, const size_t caCertLen);
};

#endif
