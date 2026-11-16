# Nano (XNO) for FOSSBilling

A payment gateway extension that lets a FOSSBilling instance accept Nano (XNO) with the `nano:mainnet` exact scheme.
It holds no key and no seed and moves no funds: the buyer publishes a confirmed send to your account, pastes the
block hash, and the gateway reads that block from a Nano node you run or trust before it marks the invoice paid.

It does nothing until you enable it and set a receiving account, a node URL and a price, so an install that leaves
it unconfigured behaves exactly as before.

## Installation

### Manual installation
1. Create a new folder named `Nano` in the `/library/Payment/Adapter` directory of your FOSSBilling installation.
2. Copy the contents of this extension's `src/` directory into it, so that `/library/Payment/Adapter/Nano/Nano.php`
   and `/library/Payment/Adapter/Nano/lib/` exist.
3. Go to the "Payment gateways" page in your admin panel (under the "System" menu) and find Nano in the
   "New payment gateway" tab.
4. Click the cog icon next to Nano to install it, then configure it:
   - **Your Nano account** - the `nano_...` account invoices are paid to. Its checksum is verified, so a mistyped
     account is refused when the gateway starts.
   - **Nano node JSON-RPC URL** - a node the gateway reads blocks from (`https`, or `http` only for a loopback node).
   - **Price in XNO** - how many XNO one unit of your invoice currency costs. The amount owed is derived from the
     invoice total and this price, in raw (1 XNO = 10^30 raw), with no float rounding.
   - **Replay window** (optional, minutes, default 60) - how long after it confirms a send is still accepted.

## How a payment is checked

The invoice page shows the exact amount in XNO and in raw, the account, and a representative account to set on the
send block. That representative is this invoice's order commitment (binding option C of the xno-binding v1
standard), so a send made for one invoice cannot be presented for another. The gateway credits the invoice only
when the node reports the block as a confirmed send, to your account, of exactly the amount owed, carrying this
invoice's commitment, inside the replay window.

Every credited block hash is written to a claims file under FOSSBilling's data directory
(`data/nano/claims-<gateway id>.log`), read, checked and appended under one exclusive lock and fsynced. One block
pays one invoice once, across restarts and across concurrent workers. A Nano send is final, so the gateway offers
no refunds; refund a buyer with a new send from your own wallet.

## Tests

The tests cover the dependency-free pieces (address checksum, raw amounts, the option C commitment vector, the
verifier, the claims ledger) and the extension's shape. They are written for Pest. To run them without a
FOSSBilling install, `tests/standalone-bootstrap.php` provides a stand-in for the core's `Payment_Exception`.

## Licensing

This extension is licensed under the Apache 2.0 license. See the [LICENSE](LICENSE) file for more information.
