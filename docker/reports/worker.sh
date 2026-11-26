#!/bin/sh

set -eu
umask 077

cd /var/www/html

PHP="/usr/local/bin/php"
EXT="/opt/bexia-worker/extensions/pcntl.so"

MAINTENANCE_INTERVAL=900
QUEUE_SLEEP=3
MAX_JOBS=5

STOP=0
CHILD=""
NEXT_MAINTENANCE=0

stop_worker() {

    STOP=1

    if [ -n "$CHILD" ]; then

        kill -TERM "$CHILD" 2>/dev/null || true

    fi
}

trap stop_worker TERM INT

run_child() {

    CHILD=""

    "$@" &

    CHILD=$!

    RC=0

    wait "$CHILD" || RC=$?

    if [ "$STOP" = "1" ]; then

        wait "$CHILD" 2>/dev/null || true

    fi

    CHILD=""

    return "$RC"
}

echo "BEXIA_REPORTS_WORKER_OPTIMIZED_START"
echo "MAINTENANCE_INTERVAL=$MAINTENANCE_INTERVAL"
echo "QUEUE_SLEEP=$QUEUE_SLEEP"
echo "MAX_JOBS=$MAX_JOBS"

while [ "$STOP" = "0" ]; do

    NOW="$(date +%s)"

    if [ "$NOW" -ge "$NEXT_MAINTENANCE" ]; then

        echo "REPORT_MAINTENANCE_START"

        if ! run_child "$PHP" \
            -d memory_limit=64M \
            -r '
                require "docker/reports/cleanup-temporary.php";

                $result = cleanupAttendanceTemporary(
                    "/tmp",
                    900
                );

                echo "REPORT_TEMP_CLEANUP=".
                    json_encode($result).
                    PHP_EOL;

                exit($result["errors"] > 0 ? 1 : 0);
            '; then

            echo "REPORT_TEMP_CLEANUP_WARNING"

        fi

        if [ "$STOP" = "1" ]; then
            break
        fi

        if run_child "$PHP" \
            -d extension="$EXT" \
            -d memory_limit=256M \
            artisan bexia:reports-maintain; then

            NEXT_MAINTENANCE="$(($(date +%s) + MAINTENANCE_INTERVAL))"

            echo "REPORT_MAINTENANCE_DONE"

        else

            echo "REPORT_MAINTENANCE_WARNING"

            NEXT_MAINTENANCE="$(($(date +%s) + 60))"

        fi
    fi

    if [ "$STOP" = "1" ]; then
        break
    fi

    NOW="$(date +%s)"

    REMAINING=$((NEXT_MAINTENANCE - NOW))

    if [ "$REMAINING" -lt 1 ]; then

        REMAINING=1

    fi

    if [ "$REMAINING" -gt 900 ]; then

        REMAINING=900

    fi

    echo "REPORT_QUEUE_RESIDENT_START MAX_TIME=$REMAINING"

    RC=0

    run_child "$PHP" \
        -d extension="$EXT" \
        -d memory_limit=512M \
        -d max_execution_time=0 \
        artisan queue:work reports_database \
        --queue=reports \
        --sleep="$QUEUE_SLEEP" \
        --tries=1 \
        --timeout=360 \
        --memory=384 \
        --max-time="$REMAINING" \
        --max-jobs="$MAX_JOBS" || RC=$?

    if [ "$STOP" = "1" ]; then
        break
    fi

    echo "REPORT_QUEUE_RESIDENT_EXIT=$RC"

    if [ "$RC" != "0" ]; then

        run_child sleep 15 || true

    else

        run_child sleep 1 || true

    fi

done

echo "BEXIA_REPORTS_WORKER_OPTIMIZED_STOP"
