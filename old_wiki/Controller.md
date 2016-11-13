# Controller

The Controller is the block *every* Refined Storage system will need. It is the core of your system.

It does not do much by itself, but it does have storage for [RS energy](https://github.com/raoulvdberge/refinedstorage/wiki/RS-energy) so connected machines can run.

When the Controller is broken, it will maintain its energy.

It draws a fixed amount of RS/t, depending on which machines are connected to it.

Each Refined Storage system can only have 1 Controller, if you attempt to connect another Controller to the same network, both or one of the Controllers will explode.

The Controller outputs a Comparator signal that states its energy level.