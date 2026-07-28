#!/usr/bin/env bash
# Ginto AI - Stop llama.cpp models

echo "Stopping llama.cpp models..."

for pidfile in /tmp/llama-*.pid; do
    if [ -f "$pidfile" ]; then
        pid=$(cat "$pidfile")
        if kill -0 "$pid" 2>/dev/null; then
            kill "$pid"
            echo "Stopped PID $pid"
        fi
        rm -f "$pidfile"
    fi
done

echo "Done."
