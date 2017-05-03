# virtual-ledger

## Getting started

Virtual ledger is a test service for end-to-end messaging between two test users using the testpoint services.

### Getting access 

There are two ways to get access to Virtual ledger.
You need either issue access token using idp.testpoint.io (Identity provider test service) or login via idp.testpoint.io.
Firstly, you need to log in to IDP (https://idp.testpoint.io/login/) using GitHub account and create synthetic user with any available ABN. 
Then you either may create access token or log in to Virtual ledger with created credentials.

To create access token you have to login to IDP (https://idp.testpoint.io/login/) with credentials of created synthetic user and issue JWT (JSON Web Token) for a specific audience
(in this case - Virtual ledger).

### Logging in

Navigate to  http://ledger.testpoint.io/ and using Login section and issued JWT ("Login via token" section) or credentials of synthetic user ("Login via idp.testpoint.io" section, you will be redirected to idp.testpoint.io) enter Virtual Ledger.

### Send an invoice

In "Receiver ABN" field enter ABN of ledger you want to try to send an invoice. For example, 99999991241 could be used. It is also possible to send message to yourself.
Then choose Document ID in corresponding field and endpoint. 
There are 3 ways to create invoice:
- select existing template (ABN's of sender and receiver will be substituted automatically)
- upload your own .json file
- paste valid json formatted document in corresponding field

After everything is done click on "Confirm" button - invoice is sent automatically to specified endpoint of receiving ledger.
In "Transactions" section you are able to see information about sent message, such as:
- Timestamp (in AEST timezone)
- ABN's of sender and receiver 
- message hash (click on it to see full hash of message)
- encrypted (with public key of receiver) and decrypted (initial message) payloads
- message type
- validation status (refresh page to update status, final status of message is "sent")
- notarized message (not implemented yet)

### Receive an invoice

Log in with credentials of receiving message ledger as described in "Logging in" section.
Messages are listed in "Transactions" table. Received message has ABN value equal to yours in "To" column. You will be able to get the same information about message as described in "Send an invoice" part.

## Deployment notes
Set up cronjob:

```
* * * * * for i in 0 1 2 3 4 5 6 7 8 9 10 11; do php PROJECT_PATH/artisan transactions & sleep 5; done; php PROJECT_PATH/artisan transactions
```

Dependencies:

1) gnupg2
`sudo apt-get install gnupg2 -y`
 
2) rng-tools
```
sudo apt-get install rng-tools
vi /etc/default/rng-tools
and add the line HRNGDEVICE=/dev/urandom:
```
