# Detector

The Detector is a block that emits a redstone signal if an item or fluid count matches a given amount.

It is also possible to compare on NBT, damage or oredict while checking if the item is available.

## Types of criteria

|Criteria|Explanation|
|--------|-----------|
|<|Emits signal when lower then given amount|
|>|Emits signal when higher then given amount|
|=|Emits signal when on given amount|
|When autocrafting|Emits signal when the item is being autocrafted, amount is irrelevant here|

When no item or fluid is specified, the criteria won't care about the item or fluid count of the specific item, but the item or fluid count of *all* the items or fluids in storage.

When no item or fluid is specified and the detector is in "Detect when autocrafting" mode, it'll detect if *any* task is autocrafting.

## Amount in fluid mode

Note that when the Detector is in fluid mode, the amount given should be in millibuckets. So if you want to check for 1 bucket of water, use 1000 and not 1.