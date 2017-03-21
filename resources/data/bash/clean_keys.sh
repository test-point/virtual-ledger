#!/bin/bash
# Clean up the GPG Keyring.  Keep it tidy.
# blog.lavall.ee

echo -n "Expired Keys: "
for expiredKey in $(gpg2 --list-keys ); do
    printf "$expiredKey \n"
    gpg2 --delete-key --quiet $expiredKey >/dev/null 2>&1
    gpg2 --delete-secret-key --quiet $expiredKey >/dev/null 2>&1
    if [ $? -eq 0 ]; then
        printf "(OK), \n"
    else
        printf "(FAIL), \n"
    fi
done
echo done.