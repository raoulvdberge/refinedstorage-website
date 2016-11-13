# Grid

In the Grid the player can see all the items stored in the system.

The items in the Grid can be sorted based on item name or item quantity. The direction can also be chosen: ascending or descending.

It is also possible to only show craftable items, or only non-craftable items.

## Search Box Modes
|Type|Description|
|----|-------|
|Normal|The default search box mode|
|Normal (autoselected)|Autoselects the search box|
|JEI synchronized|Synchronizes the search box with JEI|
|JEI synchronized (autoselected)|Synchronizes the search box with JEI and autoselects the search box|

## Search Box Filters
Prefix your search query with `@` followed by the mod ID to only show items of said mod in your Grid.

You can also give search terms after that, so it'll only display certain items of that mod.

For example:
- `@ic2` will only show IndustrialCraft 2 items and blocks
- `@ic2 nuclear` will only show IndustrialCraft 2 items and blocks that have "nuclear" in its name

## Grid Filter
A player can also insert a [Grid Filter](https://github.com/raoulvdberge/refinedstorage/wiki/Grid-Filter) to filter certain items in any type of Grid.

## Inputs
|Type|Description|
|----|-------|
|Left click|Takes at most 64 items|
|Right click|Takes at most 32 items|
|Middle click|Takes 1 item|
|SHIFT|Pushes the items to the player's inventory|
|SHIFT + CTRL|Forces the crafting GUI even if the item is available|
|Right click on search bar|Clears the search query|

These shortcuts can be combined. For example, pressing shift and middle click at the same time will push 1 item to the player inventory.