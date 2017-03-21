#!/bin/bash
# export PYTHONDONTWRITEBYTECODE='dontwrite'
ROOT=`dirname "${BASH_SOURCE[0]}"`
act="${ROOT}/.venv/bin/activate"

if [ ! -f "${act}" ]; then
    set -e
    virtualenv .venv
    source ${act}
    pip install --upgrade pip wheel
    pip install -r requirements.txt
    set +e
else
    source ${act}
fi

ARGS="$@"
if [ -n "${ARGS}" ]; then
    cd ${ROOT}
    exec $@
fi
