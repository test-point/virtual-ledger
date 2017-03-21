#!/usr/bin/env bash

set pass "test"

spawn gpg2 --armor --export-secret-key  --batch -q --passphrase-fd 0 -a "urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::123123123" > /var/www/virtual-ledger/resources/data/keys/private_1.key

expect "Password: "
send "$pass"
expect "Password: "
send "$pass"