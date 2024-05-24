#!/bin/bash

export DOCKER=${DOCKER:-"docker"}

$DOCKER build --no-cache -f Dockerfile.nginx   -t hotcrp-nginx:latest ..
$DOCKER build --no-cache -f Dockerfile.php-fpm -t hotcrp-php-fpm:latest ..
