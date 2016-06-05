#!/usr/bin/env bash

curl -vvv --user device:password -X POST \
  --header "x-ESP8266-STA-MAC: 18:FE:34:CF:76:AC" \
  --header "x-ESP8266-AP-MAC: 01:01:01:01:01:01" \
  --header "x-ESP8266-chip-size: 8192" \
  --header "x-ESP8266-version: 0.1" \
  http://localhost:8001/device/authenticate/18:FE:34:CF:76:AC

curl -vvv \
  --header "x-ESP8266-STA-MAC: 18:FE:34:CF:76:AC" \
  --header "x-ESP8266-AP-MAC: 01:01:01:01:01:01" \
  --header "x-ESP8266-chip-size: 8192" \
  --header "x-ESP8266-version: 0.1" \
  http://localhost:8001/device/update/18:FE:34:CF:76:AC
