# External Storage

The External Storage is a block that provides the system with storage of the inventory or fluid tank in front of the block. **It's like the Storage Bus in Applied Energistics**.

A (negative or positive) priority can be chosen (where a higher priority gets higher precedence to place items in).

There is also a whitelist and blocklist option to only allow or forbid some items from entering the inventory.

Every External Storage has an option to void excess items.

The default access type is extract and insert mode, but, it can be toggled to only allow insertions or only extractions as well.

## Integration

The External Storage works with all inventories.

There is special integration for Storage Drawers.

Inventories that use the MFR Deep Storage Unit API, like QuantumStorage, are supported as well.

## Energy usage

|Type|RS|
|----|------|
|No inventory connected|0 RS/t|
|Normal inventory|1 RS/t|
|Drawer (Storage Drawers)|1 RS/t|
|Drawer Controller (Storage Drawer)|1 RS/t per connected drawer|
|Deep Storage Unit (QuantumStorage)|1 RS/t|