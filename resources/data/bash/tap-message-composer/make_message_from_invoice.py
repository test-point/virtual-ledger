#!/usr/bin/env python
r"""
Demonstrational script to create TAP message.json and message.json.sig files from
given document, sender private key (for making signatures) and receiver public key
(for encrypting the document, so nobody except the receiver can read it).
Tested with python 2.7 and current stable GPG

These commands are actual for typical *nix installation, Windows users may have different experience.

Requirements:
  * python-gnupg package

Usage example:
  ./venv.sh ./make_message_from_invoice.py --document="var/invoice.json" \
    --sender_private_key="var/private_11002788650.key" \
    --receiver_public_key="var/public_67008125522.key" \
    --message_filename="var/message.json" \
    --sender="urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::11002788650"
(feel free to go without venv if you have python-gnupg installed globally)

To get your keys: generate them in GPG/GPG2 client and export. Use full participant ID when you generate it.
generate:
  localgpg --quick-gen-key urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::11002788650
export:
  localgpg --armor --export -a "urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::11002788650" > public_11002788650.key
  localgpg --armor --export-secret-key -a "urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::11002788650" > private_11002788650.key

Or you can generate only sender keys and download receiver key from the DCP.
  https://dcp.testpoint.io/urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::11002788650/keys/


Creates var/ directory near the script to put some stuff here. If it's already created
(useful for hiding your debug files from the git) then it tries not to touch files there.

You can use functions from this file separately (while passing valid parameters to them).
"""
from __future__ import unicode_literals

import json
import hashlib
import getopt
import logging
import os
import uuid
import shutil
import sys

import gnupg

current_dir = os.path.dirname(os.path.abspath(__file__))

# configure logging - default to console with some verbose format
logger = logging.getLogger('default-logger')
consoleHandler = logging.StreamHandler()
consoleHandler.setFormatter(logging.Formatter("%(asctime)s [%(levelname)-5.5s]  %(message)s"))
logger.addHandler(consoleHandler)
logger.setLevel(logging.getLevelName('DEBUG'))


class UsageException(Exception):
    pass


class GpgException(Exception):
    pass


def main(sender_private_key, receiver_public_key, document, message_filename, sender):
    """
    Main procedure, which performs all other smaller actions
    * ensure any required file exists
        * sender private key
        * receiver public key
        * the document
    * prepares GPG installation (each run in separate GPG home dir to avoid side effects)
    * make document cyphertext
    * create message.json
      * with document cyphertext
      * sender
      * document hash
    * create message.json signature (default with .sig extension and binary format - can be changed)
    """
    logger.debug("Trying to encode document {} into TAP message with sender private key {} and receiver public key {}".format(
        document,
        sender_private_key,
        receiver_public_key
    ))
    transaction_id = str(uuid.uuid1())[:8]
    try:
        os.mkdir('var')
    except OSError:
        pass

    # this directory will be deleted recursively, so please don't use / here
    # safe to move it to /tmp/ or even memory disk
    gnupghome = os.path.join(
        current_dir,
        './var/gpg_profile_{}'.format(transaction_id)
    )
    gpg = gnupg.GPG(gnupghome=gnupghome)
    gpg.encoding = 'utf-8'

    if not os.path.isfile(sender_private_key):
        raise UsageException(
            "Sender private key {} must be a file".format(sender_private_key)
        )

    if not os.path.isfile(receiver_public_key):
        raise UsageException(
            "Receiver public key {} must be a file".format(receiver_public_key)
        )

    if not os.path.isfile(document):
        raise UsageException("Document {} must be a file".format(document))

    # import sender and receiver keys
    sender_private_key = import_private_key(gpg, sender_private_key)
    receiver_public_key = import_public_key(gpg, receiver_public_key)

    logger.debug("Receiver key is {}".format(receiver_public_key))
    logger.debug("Sender private key is {}".format(sender_private_key))

    cypherdocument = get_file_cypher(gpg, document=document, key=receiver_public_key)

    compose_message(document=document, cypherdocument=cypherdocument, sender=sender, message_filename=message_filename)
    signature_filename = sign_message(gpg, message_filename=message_filename, key=sender_private_key)

    # cleanup gpg home directory
    shutil.rmtree(gnupghome)

    logger.info("May be it's done. Output filename is {}, signature is {}".format(
        message_filename,
        signature_filename
    ))

    return


def import_private_key(gpg, filename):
    """
    Import private keys from given file (by filename) and returns first private one
    Or raise exception if nothing here
    """
    res = gpg.import_keys(open(filename, 'rb').read())
    if not res.fingerprints:
        raise UsageException("File {} contains no keys to be imported".format(filename))
    # get first imported private key
    private_keys = gpg.list_keys(True)
    if len(private_keys) == 0:
        raise UsageException("No private keys were importd")
    if len(private_keys) > 1:
        logger.warning("More than one private key found, using first one")
    return private_keys[0]


def import_public_key(gpg, filename):
    """
    Import publick keys from given file (by filename) and returns first private one
    Or raise exception if nothing here
    If you have several keys here then you might want to specifically set the one to encrypt,
    may be by passing fingerprint or keyid
    """
    old_keys = [k['fingerprint'] for k in gpg.list_keys(False)]

    res = gpg.import_keys(open(filename, 'rb').read())
    if not res.fingerprints:
        raise UsageException("File {} contains no keys to be imported".format(filename))
    # get first imported public key
    public_keys = [x for x in gpg.list_keys(False) if x['fingerprint'] not in old_keys]
    if len(public_keys) == 0:
        raise UsageException("No public keys were importd")
    if len(public_keys) > 1:
        logger.warning("More than single private key found, using first one")
    return public_keys[0]


def compose_message(document, cypherdocument, sender, message_filename):
    """
    Just create json file from the given parameters
    """
    message = {
        'cyphertext': cypherdocument,
        'hash': get_file_hash(document),  # from document before encryption
        'reference': "",  # read_reference(),
        'sender': sender,
    }
    result_file = open(message_filename, 'wb')
    result_file.truncate()
    result_file.write(
        json.dumps(message, indent=2)
    )
    result_file.write('\n')
    return True


def get_file_cypher(gpg, document, key):
    """
    Encrypt the file (provided by filename) and return encrypted cyphertext
    in Armor format
    Potentially memory-intensive, but okay for small files (up to 100 MB)
    """
    recipients = [key['fingerprint']]
    encrypt_result = gpg.encrypt_file(open(document, 'rb'), recipients, always_trust=True)
    if not encrypt_result.ok:
        logger.error(encrypt_result.stderr)
        raise GpgException("Can't encrypt the file", encrypt_result.status, encrypt_result.stderr)
    logger.debug("Successfully encrypted the file, cyphertext len is {}".format(len(str(encrypt_result))))
    return str(encrypt_result)


def get_file_hash(filename):
    """
    Return file SHA256 hash in HEX representation by file name
    """
    h = hashlib.sha256()
    with open(filename, 'rb') as src_file:
        chunk = True
        while chunk:
            chunk = src_file.read(1024)
            h.update(chunk)
    return h.hexdigest()


def sign_message(gpg, message_filename, key):
    """
    Create message signature as side-effect, returns signature filename
    """
    signature_filename = message_filename + '.sig'  # replace to .asc if binary=False
    gpg.sign_file(
        open(message_filename, 'rb'),
        keyid=key['keyid'],
        detach=True,
        binary=True,  # can be False for text output
        output=signature_filename
    )
    # logger.debug(signed_data.stderr)
    return signature_filename


if __name__ == '__main__':
    help_line = 'parameters: see the code'
    parameters = {
        'sender_private_key': 'var/sender_private.key',
        'receiver_public_key': 'var/receiver_public.key',
        'document': 'var/invoice.json',
        'message_filename': 'var/message.json',
        'sender': 'urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::99999999990'
    }
    try:
        opts, args = getopt.getopt(
            sys.argv[1:],
            "hd:",
            ["document=", "sender_private_key=", "receiver_public_key=", "message_filename=", "sender="]
        )
    except getopt.GetoptError as err:
        logger.error(err)
        sys.exit(2)
    for opt, arg in opts:
        if opt == '-h':
            print(help_line)
            sys.exit()
        elif opt[2:] in parameters:
            parameters[opt[2:]] = arg
        else:
            logger.error("Unknown parameter {}".format(opt))
            exit(3)
    for fnameparam in ['sender_private_key', 'receiver_public_key', 'document', 'message_filename']:
        parameters[fnameparam] = os.path.join(
            current_dir,
            parameters[fnameparam]
        )
    main(**parameters)
