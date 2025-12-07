#!/bin/bash
set -e

echo "=== NexusPHP Blindbox Plugin Installation ==="

# Get the current directory
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
NEXUS_ROOT="$(cd "$PLUGIN_DIR/../../.." && pwd)"

echo "Plugin directory: $PLUGIN_DIR"
echo "NexusPHP root: $NEXUS_ROOT"

# Check if NexusPHP root is valid
if [ ! -f "$NEXUS_ROOT/include/bittorrent.php" ]; then
    echo "Error: NexusPHP root directory not found or invalid"
    exit 1
fi

# Create symlink to the plugin
mkdir "$NEXUS_ROOT/public/plugins"
PLUGIN_LINK="$NEXUS_ROOT/public/plugins/nexusphp-blindbox"
if [ -L "$PLUGIN_LINK" ]; then
    echo "Removing existing symlink..."
    rm "$PLUGIN_LINK"
fi

echo "Creating plugin symlink..."
ln -sf "$PLUGIN_DIR" "$PLUGIN_LINK/public"
