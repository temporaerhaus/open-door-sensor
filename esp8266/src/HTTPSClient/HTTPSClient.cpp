#include "HTTPSClient.h"
#include <WiFiClientSecure.h>

// Override TransportTraits with the same version again.
// TransportTraits is not fully defined in ESP8266HTTPClient.h
// so TLSCATraits can't simply extend it
class TransportTraits
{
public:
    virtual ~TransportTraits()
    {
    }

    virtual std::unique_ptr<WiFiClient> create()
    {
        return std::unique_ptr<WiFiClient>(new WiFiClient());
    }

    virtual bool verify(WiFiClient& client, const char* host)
    {
        return true;
    }
};

class TransportTraits;
typedef std::unique_ptr<TransportTraits> TransportTraitsPtr;

class TLSCATraits : public TransportTraits
{
public:
    TLSCATraits(const uint8_t* caCert, const size_t caCertLen) :
        _caCert(caCert),
        _caCertLen(caCertLen)
    {
    }

    std::unique_ptr<WiFiClient> create() override
    {
        auto client = new WiFiClientSecure();
        client->setCACert(_caCert, _caCertLen);
        return std::unique_ptr<WiFiClient>(client);
    }

    bool verify(WiFiClient& client, const char* host) override
    {
        auto wcs = static_cast<WiFiClientSecure&>(client);
        return wcs.verifyCertChain(host);
    }

protected:
    const uint8_t* _caCert;
    const size_t _caCertLen;
};

bool HTTPSClient::begin(String url, const uint8_t* caCert, const size_t caCertLen)
{
    _transportTraits.reset(nullptr);
    _port = 443;
    if (!beginInternal(url, "https")) {
        return false;
    }
    _transportTraits = TransportTraitsPtr(new TLSCATraits(caCert, caCertLen));
    return true;
}


bool HTTPSClient::begin(String host, uint16_t port, String uri, const uint8_t* caCert, const size_t caCertLen)
{
    clear();
    _host = host;
    _port = port;
    _uri = uri;

    _transportTraits = TransportTraitsPtr(new TLSCATraits(caCert, caCertLen));
    return true;
}
