# Wireless Grid

With the Wireless Grid the player can access their items from anywhere.

To activate the Wireless Grid, the player has to right click the Wireless Grid on a [Controller](https://github.com/raoulvdberge/refinedstorage/wiki/Controller).

To use the Wireless Grid the item needs RS power. You'll have to charge it in a block that charges items with [one of the supported energy systems](https://github.com/raoulvdberge/refinedstorage/wiki/RS-energy).

After doing all these steps, the Wireless Grid is still missing a signal from the system. Add at least 1 [Wireless Transmitter](https://github.com/raoulvdberge/refinedstorage/wiki/Wireless-Transmitter) to the network to get a basic range of 16 blocks.

If the Wireless Grid is ready for use, it will light up blue.

Sometimes, the Wireless Grid doesn't open or stays gray. To enable it make sure that:
- The Wireless Grid is bound to a [Controller](https://github.com/raoulvdberge/refinedstorage/wiki/Controller)
- There is at least 1 [Wireless Transmitter](https://github.com/raoulvdberge/refinedstorage/wiki/Wireless-Transmitter) connected to the network
- That you are in range of the [Wireless Transmitter](https://github.com/raoulvdberge/refinedstorage/wiki/Wireless-Transmitter)
- The [Controller](https://github.com/raoulvdberge/refinedstorage/wiki/Controller) block is still in the world on the place where you bound it to

## Energy Behavior

The Wireless Grid draws energy on following actions:

|Action|Amount of RS drawed|
|------|------|
|Opening the Wireless Grid|30 RS|
|Pulling an item from storage|3 RS|
|Pushing an item to storage|3 RS|