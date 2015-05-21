#!/usr/bin/env bash

# Assumes the image is tagged localgiving/imgproxy
# Opens up port 1235 as the proxy
sudo docker run --env-file=conf.ini -p 1235:80 -i -t localgiving/imgproxy /opt/init.sh
