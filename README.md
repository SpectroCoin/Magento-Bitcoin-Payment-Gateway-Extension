SpectroCoin Payment Method
---------------

This module integrates [SpectroCoin](https://spectrocoin.com/) Payments with [Magento](http://magento.com/) to accept [Bitcoin](https://bitcoin.org) payments.

**INSTALLATION**

1. Upload files to Magento main folder.
2. Generate private and public keys
2.1 Private key:
    # generate a 2048-bit RSA private key
    openssl genrsa -out "C:\private" 2048
2.2 Public key:
    # output public key portion in PEM format
    openssl rsa -in "C:\private" -pubout -outform PEM -out "C:\public"

**CONFIGURATION**

3. Go to System -> Configuration -> Payment Methods -> SpectroCoin
4. Enter your Merchant Id, Application Id, Private key.
