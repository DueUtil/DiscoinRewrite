## Headers
**You need to send this for all requests.**
```JSON
{"Authorization": "Your key here"}
```
You get your auth key from MacDue#4453, Fires#1043 or austinhuang#1076.

## Python 3.5 with aiohttp
### Making a transaction
```py
async def start_transaction(sender_id, amount, to):

    transaction_data = {
        "user": sender_id,
        "amount": amount,
        "exchangeTo": to
    }

    with aiohttp.Timeout(10):
        async with aiohttp.ClientSession() as session:
            async with session.post("http://discoin.sidetrip.xyz/transaction",
                                    data=json.dumps(transaction_data), headers=headers) as response:
                return await response.json()
                # Look at the api spec for what the response json could be and figure out
                # how you want to format the outputs.
```
### Processing transactions
```py
async def process_transactions():
    async with aiohttp.ClientSession() as session:
        async with session.get("http://discoin.sidetrip.xyz/transactions", headers=headers) as response:
            transactions = await response.json()
       
            for transaction in transactions:
                user_id = transaction.get('user')
                receipt = transaction.get('receipt')
                source_bot = transaction.get('source')
                amount = transaction.get('amount')

                # Your bots stuff here.
```
