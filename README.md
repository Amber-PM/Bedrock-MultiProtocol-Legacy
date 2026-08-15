<p align="center">
	<a href="https://github.com/Amber-PM/Amber">
		<img src=".github/readme/amberpm.png" width="128" height="128" alt="AmberPM Logo" title="AmberPM" />
	</a><br>
	<b>AmberPM: A high-performance, multi-version fork of PocketMine-MP written in PHP</b>
</p>

<p align="center">
	<a href="https://github.com/Amber-PM/Amber/releases/latest"><img alt="GitHub release (latest SemVer)" src="https://img.shields.io/github/v/release/Amber-PM/Amber?label=release&sort=semver"></a>
	<a href="https://discord.gg/k55gScjTs3"><img src="https://img.shields.io/badge/Discord-Chat-5865F2?logo=discord&logoColor=white" alt="Discord" /></a>
	<a href="LICENSE"><img src="https://img.shields.io/badge/License-LGPL--3.0-blue.svg" alt="License" /></a>
</p>

## What is AmberPM?

**AmberPM** is a high-performance, production-ready fork of PocketMine-MP designed for server networks that require simultaneous multi-version (MV) client compatibility.

Built on top of the stable **PocketMine-MP 5.44.2** codebase, AmberPM incorporates a dynamic protocol translation layer. This allows supported Minecraft: Bedrock Edition clients to connect and play concurrently on the same server without requiring external proxies or translators.

The primary compatibility range is **v1.20.0 (protocol 589)** through **v1.26.30 (protocol 1001)**. AmberPM also includes **experimental legacy support for v1.2.13 (protocol 223)** through a dedicated compatibility layer.

### Key Features

* 🌐 **Dynamic Multi-Version Support** — Concurrent support for Minecraft: Bedrock protocols **589–1001** (v1.20.0–v1.26.30), plus experimental legacy protocol **223** (v1.2.13).
* 🧭 **Capability-Based Compatibility** — Protocol behavior is selected by declared client capabilities instead of relying only on scattered version checks.
* ⚙️ **Protocol-Isolated Dictionaries & Registries** — Version-aware mappings for block states, item dictionaries, crafting recipes, and creative inventories prevent data from leaking between protocol sessions.
* 🕰️ **Dedicated Legacy Translation Layer** — Handles legacy item IDs, block runtime IDs, chunk serialization, packet layouts, metadata flags, inventory transactions, and other historical wire-format differences.
* 🛠️ **Native Anvil & Repair System** — Built-in support for `AnvilTransaction`, item renaming, repairing, and enchantment combining with customizable cost calculations.
* 🎯 **Custom Event Dispatchers** — Developer-focused events such as `PlayerPressurePlateTriggerEvent`, `SessionDisconnectEvent`, and `ItemEntityDropEvent` provide granular control.
* 🧩 **Extensible Plugin API** — Maintains compatibility with the official PocketMine-MP v5 plugin API, allowing most standard plugins to run without modification.
* ⚡ **Performance & Compression** — Optimized protocol translation and dynamic packet compression selected according to the connected client's protocol.

## Supported Client Versions

| Support tier | Minecraft: Bedrock version | Protocol | Status |
|---|---:|---:|---|
| Primary multi-version range | v1.20.0–v1.26.30 | 589–1001 | Supported |
| Legacy compatibility layer | v1.2.13 | 223 | Experimental |

> [!WARNING]
> Legacy protocol 223 support is still under development and has known gameplay issues, including unreliable crafting, recipe-book crashes, and cross-version entity visibility problems. Review [`KNOWN_ISSUES.md`](KNOWN_ISSUES.md) before enabling it in production.

Support for protocol 223 is implemented separately from the normal per-protocol data path because this client predates several modern Bedrock networking concepts. Contributors interested in adding another old client generation should read [Adding Support for Another Legacy Version](ADDING_LEGACY_VERSIONS.md).

## :x: AmberPM is NOT a vanilla Minecraft server software

**It is designed primarily for custom game modes, minigames, and lobby servers.**

Like official PocketMine-MP, AmberPM does not ship with most vanilla survival systems, such as vanilla mob AI, redstone simulation, or vanilla world generation.

If you want to host a purely **vanilla survival multiplayer** server, use the [official Minecraft: Bedrock server software](https://minecraft.net/download/server/bedrock).

## Getting Started

### Installation & Compilation

To compile AmberPM from source, use the integrated scripts or Composer.

#### Windows

Run the included compilation script:

```cmd
compile.bat
```

The script verifies the PHP installation, downloads Composer when necessary, installs production dependencies with optimized flags, and packages the project as `PocketMine-MP.phar`.

#### Linux, macOS, or Composer

Install production dependencies and compile the server with:

```bash
composer run make-server
```

### Running the Server

Launch the compiled server with the script for your platform:

* **Windows:** `start.cmd` or `start.ps1`
* **Linux / macOS:** `./start.sh`

## Command Overloading & Autocompletion

AmberPM includes a built-in **Command Overloading** and **Network Autocompletion** system. It lets developers define multiple type-safe signatures for one command and translates them into client-side Bedrock command hints through `AvailableCommandsPacket`.

### Creating an Overloaded Command

Extend `OverloadedCommand` and declare overloads with closures and PHP 8 attributes:

```php
use pocketmine\command\OverloadedCommand;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\item\Item;
use pocketmine\command\overload\attribute\IntRange;

class MyGiveCommand extends OverloadedCommand{
    public function __construct(){
        parent::__construct("mygive", "Give items to players");

        // Overload signature: /mygive <player> <item> [amount]
        $this->addOverload(
            function(
                CommandSender $sender,
                Player $target,
                Item $item,
                #[IntRange(1, 64)] int $amount = 1
            ): void{
                $target->getInventory()->addItem($item->setCount($amount));
                $sender->sendMessage("Gave items!");
            }
        );
    }
}
```

### Supported Argument Parsers & Types

The system infers parsers from closure parameter types. PHP 8 attributes can provide additional constraints or behavior.

| Parameter type / attribute | Client autocomplete UI | Parser |
|---|---|---|
| `Player` / `PlayerOrSelf` | Player selectors and names | `PlayerArgumentParser` |
| `Item` | Item registry names with icons | `ItemArgumentParser` |
| `Vector3` | X Y Z fields with `~` coordinate support | `Vector3ArgumentParser` |
| `bool` | True / false enum | `BoolArgumentParser` |
| `int` / `#[IntRange(min, max)]` | Bounded integer | `IntegerArgumentParser` |
| `float` / `#[FloatRange(min, max)]` | Bounded decimal | `FloatArgumentParser` |
| `string` / `#[EnumValues(...)]` | Static options | `StringArgumentParser` |
| `#[DynamicEnum(ProviderClass::class)]` | Dynamically generated options | `DynamicEnumArgumentParser` |

## Developing Plugins

AmberPM maintains compatibility with the PocketMine-MP v5 API. These resources are useful for plugin development:

* [PocketMine-MP Developer Documentation](https://devdoc.pmmp.io) — General documentation for plugin developers.
* [ExamplePlugin](https://github.com/pmmp/ExamplePlugin/) — Reference implementation demonstrating the core API.
* [DevTools](https://github.com/pmmp/DevTools/) — Development plugin for packaging other plugins.

## Contributor Documentation

* [Adding Support for Another Legacy Version](ADDING_LEGACY_VERSIONS.md) — Architecture, implementation steps, tests, and release checklist for an additional legacy protocol.
* [Known Issues: Legacy 1.2.13 / Protocol 223](KNOWN_ISSUES.md) — Reproducible unresolved problems in the current legacy compatibility layer.

When implementing protocol support, verify wire formats and lookup tables against a real client, packet captures, or a trustworthy implementation from the same era. Historical runtime IDs and packet layouts must not be guessed from modern data.

## Need Help?

Join the AmberPM community on [Discord](https://discord.gg/k55gScjTs3) for project support and development discussion.

## Licensing

This project is licensed under the GNU Lesser General Public License v3.0 (LGPL-3.0). See [`LICENSE`](LICENSE) for the complete license text.

*AmberPM and PocketMine-MP are not affiliated with Mojang Studios or Microsoft. All trademarks belong to their respective owners.*

## Maintainers

* [vapebw](https://github.com/vapebw)
* [funaoo](https://github.com/funaoo)
