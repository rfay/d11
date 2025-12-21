#!/bin/bash
# Simple healthcheck for MySQL
# Uses mysqladmin if available, otherwise tries mysql client

set -e

if command -v mysqladmin >/dev/null 2>&1; then
    mysqladmin ping -h localhost >/dev/null 2>&1
else
    mysql -h localhost -e "SELECT 1" >/dev/null 2>&1
fi
