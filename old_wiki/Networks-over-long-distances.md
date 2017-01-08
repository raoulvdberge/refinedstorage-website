# Networks over long distances

## The situation
Sometimes, you may have different areas all scattered throughout your world.

## The problem
Here is the problem: you want access to your Refined Storage system on all of those areas.

You could lay [Cables](https://github.com/raoulvdberge/refinedstorage/wiki/Cable) from your base to every area, but what if said area is 1000 blocks away? Are you really going to craft 1000 [Cables](https://github.com/raoulvdberge/refinedstorage/wiki/Cable)? Didn't think so.

## The solution
Using [Network Transmitters](https://github.com/raoulvdberge/refinedstorage/wiki/Network-Transmitter) and [Network Receivers](https://github.com/raoulvdberge/refinedstorage/wiki/Network-Receiver)!

Simply craft a [Network Transmitter](https://github.com/raoulvdberge/refinedstorage/wiki/Network-Transmitter) and connect it to your Refined Storage system in your main base.

This block will take a LOT of energy (of course, this depends on the config file and the distance between your base and the area, but generally speaking...), so make sure your power situation is OK.

Next up, craft a [Network Card](https://github.com/raoulvdberge/refinedstorage/wiki/Network-Card). I'll be telling what you need this for in a second, bear with me.

After crafting a [Network Card](https://github.com/raoulvdberge/refinedstorage/wiki/Network-Card), craft a [Network Receiver](https://github.com/raoulvdberge/refinedstorage/wiki/Network-Receiver) and place it in the area far away from your base.

Take the [Network Card](https://github.com/raoulvdberge/refinedstorage/wiki/Network-Card) and right click it on the [Network Receiver](https://github.com/raoulvdberge/refinedstorage/wiki/Network-Receiver).

Put the [Network Card](https://github.com/raoulvdberge/refinedstorage/wiki/Network-Card) in the [Network Transmitter](https://github.com/raoulvdberge/refinedstorage/wiki/Network-Transmitter) so the [Network Transmitter](https://github.com/raoulvdberge/refinedstorage/wiki/Network-Transmitter) knows where to send a signal to.

And now you are done. Simply connect machines to your [Network Receiver](https://github.com/raoulvdberge/refinedstorage/wiki/Network-Receiver) according to the normal machine connecting rules.

I suppose you could treat the [Network Receiver](https://github.com/raoulvdberge/refinedstorage/wiki/Network-Receiver) as a [Controller](https://github.com/raoulvdberge/refinedstorage/wiki/Controller) (just don't connect another [Controller](https://github.com/raoulvdberge/refinedstorage/wiki/Controller) to the [Network Receiver](https://github.com/raoulvdberge/refinedstorage/wiki/Network-Receiver), or you are going to have a bad time ;)).

## What if my other area is in another dimension?
You do the exact same process. There is one big difference: the power cost and a neccesary upgrade.

When the [Network Receiver](https://github.com/raoulvdberge/refinedstorage/wiki/Network-Receiver) is in another dimension it no longer calculates the power cost based on distance, but it'll have a fixed power cost of 1000 RS/t.

Then you need to insert a [Interdimensional Upgrade](https://github.com/raoulvdberge/refinedstorage/wiki/Interdimensional-Upgrade) in the [Network Transmitter](https://github.com/raoulvdberge/refinedstorage/wiki/Network-Transmitter), only then will it draw the 1000 RS/t.

## Important things to note
- The [Controller](https://github.com/raoulvdberge/refinedstorage/wiki/Controller) in your main base and the [Network Receiver](https://github.com/raoulvdberge/refinedstorage/wiki/Network-Receiver) in the other area has to be chunkloaded!