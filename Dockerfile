ARG BUILD_FROM=hassioaddons/base:7.2.0
FROM ${BUILD_FROM}

SHELL ["/bin/bash", "-o", "pipefail", "-c"]

RUN apk add --no-cache nginx php7-fpm php7-json php7-opcache php7-sockets php7-fpm

COPY rootfs /