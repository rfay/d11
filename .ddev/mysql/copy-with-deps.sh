#!/bin/bash
# Copy a binary and all its shared library dependencies

set -euo pipefail

BINARY_NAME=$1
DEST_ROOT=$2

# Find the binary
BINARY_PATH=$(which "$BINARY_NAME" 2>/dev/null || true)
if [ -z "$BINARY_PATH" ]; then
    echo "WARNING: Binary $BINARY_NAME not found, skipping"
    exit 0
fi

echo "Processing $BINARY_NAME from $BINARY_PATH"

# Copy the binary itself
BIN_DIR=$(dirname "$BINARY_PATH")
mkdir -p "$DEST_ROOT$BIN_DIR"
cp -L "$BINARY_PATH" "$DEST_ROOT$BIN_DIR/"

# Find and copy all shared library dependencies
ldd "$BINARY_PATH" 2>/dev/null | grep "=> /" | awk '{print $3}' | while read -r lib; do
    if [ -f "$lib" ]; then
        LIB_DIR=$(dirname "$lib")
        mkdir -p "$DEST_ROOT$LIB_DIR"
        # Use -L to follow symlinks and copy actual file
        cp -L "$lib" "$DEST_ROOT$LIB_DIR/" 2>/dev/null || true
        echo "  Copied dependency: $lib"
    fi
done

# Also handle the dynamic linker (ld-linux)
ldd "$BINARY_PATH" 2>/dev/null | grep "ld-linux" | awk '{print $1}' | while read -r linker; do
    if [ -f "$linker" ]; then
        LINKER_DIR=$(dirname "$linker")
        mkdir -p "$DEST_ROOT$LINKER_DIR"
        cp -L "$linker" "$DEST_ROOT$LINKER_DIR/" 2>/dev/null || true
        echo "  Copied linker: $linker"
    fi
done

echo "Completed $BINARY_NAME"
