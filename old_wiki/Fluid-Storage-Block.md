# Fluid Storage Block

The Fluid Storage Block is a block that provides the system with storage. It is similar to [Fluid Storage Disks](https://github.com/raoulvdberge/refinedstorage/wiki/Fluid-Storage-Disk), but can be placed in the world.

A (negative or positive) priority can be chosen (where a higher priority gets higher precedence to place items in).

There is also a whitelist and blocklist option to only allow or forbid some fluid from entering the Fluid Storage Block.

When breaking the Fluid Storage Block, the fluids that it holds persist, so you won't lose any of your fluids.

Every Fluid Storage Block has an option to void excess fluids.

The default access type is extract and insert mode, but, it can be toggled to only allow insertions or only extractions as well.

## Types of Fluid Storage Blocks

|Type|Max millibuckets|Max buckets|
|----|-------|-----------|
|64k Fluid Storage Block|64000 millibuckets|64 buckets|
|128k Fluid Storage Block|128000 millibuckets|128 buckets|
|256k Fluid Storage Block|256000 millibuckets|256 buckets|
|512k Fluid Storage Block|512000 millibuckets|512 buckets|
|Creative Fluid Storage Block|Infinite millibuckets|Infinite buckets|

## Uncrafting a Fluid Storage Block

When the Fluid Storage Block is empty, the player can retrieve the [Fluid Storage Part](https://github.com/raoulvdberge/refinedstorage/wiki/Fluid-Storage-Part), the [Basic Processor](https://github.com/raoulvdberge/refinedstorage/wiki/Processor) and the [Machine Casing](https://github.com/raoulvdberge/refinedstorage/wiki/Machine-Casing) back by selecting it and right clicking while sneaking.