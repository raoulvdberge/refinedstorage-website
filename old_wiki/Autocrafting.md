# Autocrafting

## A minimal setup
Craft following blocks and items (in order) to have a basic autocrafting setup:
- [Pattern](https://github.com/raoulvdberge/refinedstorage/wiki/Pattern) (a few, depends on how many items you want autocrafting for)
- [Crafter](https://github.com/raoulvdberge/refinedstorage/wiki/Crafter) (1x)
- [Pattern Grid](https://github.com/raoulvdberge/refinedstorage/wiki/Pattern-Grid) (1x)
- [Crafting Monitor](https://github.com/raoulvdberge/refinedstorage/wiki/Crafting-Monitor) (1x, optional but highly recommended to see what items are missing and what you are crafting)

In a [Pattern](https://github.com/raoulvdberge/refinedstorage/wiki/Pattern) you'll be storing the recipe of each craftable item, so the storage system knows how to craft the item.

Applying a recipe to a pattern is done with the [Pattern Grid](https://github.com/raoulvdberge/refinedstorage/wiki/Pattern-Grid).

The [Crafter](https://github.com/raoulvdberge/refinedstorage/wiki/Crafter) is the actual block that will craft the item. In this block, you have to insert the [Patterns](https://github.com/raoulvdberge/refinedstorage/wiki/Pattern) you created in the [Pattern Grid](https://github.com/raoulvdberge/refinedstorage/wiki/Pattern-Grid).

The player can then request the item through any kind of [Grid](https://github.com/raoulvdberge/refinedstorage/wiki/Grid).

Once the crafting has started, the [Crafter](https://github.com/raoulvdberge/refinedstorage/wiki/Crafter) will take all the items required. When a required item is not found in the system, it will try to schedule a subtask to craft that item.

However, if the missing item doesn't have a [Pattern](https://github.com/raoulvdberge/refinedstorage/wiki/Pattern) in the system, the player will have to create that [Pattern](https://github.com/raoulvdberge/refinedstorage/wiki/Pattern) and insert it into a [Crafter](https://github.com/raoulvdberge/refinedstorage/wiki/Crafter) or insert the missing item manually in the system.

It is possible to make the crafting process faster with [Speed Upgrades](https://github.com/raoulvdberge/refinedstorage/wiki/Speed-Upgrade).

## Processing crafting tasks
Sometimes you want to set up autocrafting for recipes that don't have any regular crafting recipe. For example: autocrafting an iron ingot by putting iron ore in a furnace.

For something like that you'll have to create a [Processing Pattern Encoder](https://github.com/raoulvdberge/refinedstorage/wiki/Processing-Pattern-Encoder). In that block, you assign a series of input items and a series of output items to a [Pattern](https://github.com/raoulvdberge/refinedstorage/wiki/Pattern).

So for the iron ingot example, you stick an iron ore in the input section, and an iron ingot in the output section. Then make the [Pattern](https://github.com/raoulvdberge/refinedstorage/wiki/Pattern).

Next up, you'll have to make a [Crafter](https://github.com/raoulvdberge/refinedstorage/wiki/Crafter) and let it face the furnace (or whatever machine you're setting up processing for) in a way it can input it in the correct slot. Then stick a [Importer](https://github.com/raoulvdberge/refinedstorage/wiki/Importer) on the bottom face of the furnace to suck up the final iron ingot and finish the crafting task.

Refined Storage will take all the items that the pattern requires from the system, and then it'll insert them into the machine.

## Triggering crafting tasks with a redstone signal

This is possible and is done through the [Crafter](https://github.com/raoulvdberge/refinedstorage/wiki/Crafter). Read more about this subject [here](https://github.com/raoulvdberge/refinedstorage/wiki/Crafter#triggering-crafting-tasks-with-a-redstone-signal).

## Fluid autocrafting

Make sure the fluid is in your fluid storage and you have a bucket in your system (you can also add a crafting pattern for it).

When doing the autocraft, Refined Storage will take a bucket from your system, fill it, craft with it, and give you an empty bucket back.