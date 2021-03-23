<?php

namespace DavyCraft648\ZoomItem;

use pocketmine\command\{Command, CommandSender, ConsoleCommandSender};
use pocketmine\entity\Attribute;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\{PlayerDropItemEvent, PlayerInteractEvent, PlayerToggleSneakEvent};
use pocketmine\inventory\ContainerInventory;
use pocketmine\item\ItemFactory;
use pocketmine\nbt\tag\StringTag;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\{Config, TextFormat};

class Main extends PluginBase implements Listener
{
    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    /**
     * @param CommandSender $sender
     * @param Command $command
     * @param string $label
     * @param array $args
     * @return bool
     */
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        $item = ItemFactory::get($this->zoomId(), $this->zoomMeta())
            ->setCustomName(TextFormat::RESET . TextFormat::BLUE . "ZoomItem");
        $item->getNamedTag()->setString("ZoomItem", "ZoomIn");
        if ($sender instanceof Player and !isset($args[0])) {
            $sender->getInventory()->addItem($item);
            return true;
        }
        if (isset($args[0]) and $sender->hasPermission("zoomitem.give")) {
            $this->giveZoomItem($sender, $args[0]);
            return true;
        }
        return false;
    }

    /**
     * Give zoom item to player
     * @param $sender CommandSender|Player|ConsoleCommandSender
     * @param $target string|Player
     */
    public function giveZoomItem($sender, $target): void {
        $item = ItemFactory::get($this->zoomId(), $this->zoomMeta())
            ->setCustomName(TextFormat::RESET . TextFormat::BLUE . "ZoomItem");
        $item->getNamedTag()->setString("ZoomItem", "ZoomIn");
        if (is_string($target)) {
            $target = $this->getServer()->getPlayerExact($target);
        }
        if ($target instanceof Player) {
            $target->getInventory()->addItem($item);
            $target->sendMessage(TextFormat::GREEN."You got a zoom item from {$sender->getName()}");
            $sender->sendMessage(TextFormat::GREEN."Given zoom item to {$target->getName()}");
            return;
        }
        $sender->sendMessage(TextFormat::RED."Player not found or try to be more specific");
    }

    /**
     * Get ZoomItem Config
     * @return Config
     */
    public function myConfig(): Config {
        return new Config($this->getDataFolder() . "config.yml", Config::YAML);
    }

    private function zoomId(): int {
        return $this->myConfig()->get("itemId", 280);
    }

    private function zoomMeta(): int {
        return $this->myConfig()->get("itemMeta", 0);
    }

    private function inMessage(): string {
        return $this->myConfig()->get("zoomInMessage", "Zooming In...");
    }

    private function outMessage(): string {
        return $this->myConfig()->get("zoomOutMessage", "Zooming Out!");
    }

    private function itemName(): string {
        return $this->myConfig()->get("itemName", "&r&9ZoomItem");
    }

    private function inName(): string {
        return $this->myConfig()->get("zoomInItemName", "") == "" ? $this->itemName() :
            $this->myConfig()->get("zoomInItemName");
    }

    private function outName(): string {
        return $this->myConfig()->get("zoomOutItemName", "") == "" ? $this->itemName() :
            $this->myConfig()->get("zoomOutItemName");
    }

    private function getMode(): string {
        return $this->myConfig()->get("mode", "Sneak");
    }

    private function noDrop(): bool {
        return $this->myConfig()->get("noDropItem", true);
    }

    private function noMoveInv(): bool {
        return $this->myConfig()->get("noMoveInv", false);
    }

    /**
     * Handle PlayerDropItem event
     * @param PlayerDropItemEvent $event
     * @priority HIGH
     * @ignoreCancelled true
     */
    public function onItemThrown(PlayerDropItemEvent $event): void {
        if (!$this->noDrop()) return;
        if ($event->getItem()->getNamedTag()->hasTag("ZoomItem", StringTag::class) and
            !$event->getPlayer()->hasPermission("zoomitem.give")
        ) $event->setCancelled();
    }

    /**
     * Handle InventoryTransaction event
     * @param InventoryTransactionEvent $event
     * @priority LOW
     * @ignoreCancelled true
     */
    public function onItemMovedToChest(InventoryTransactionEvent $event): void {
        if (!$this->noMoveInv()) return;
        $transaction = $event->getTransaction();
        $zoomItem = null;
        $container = null;
        foreach ($transaction->getInventories() as $inventory) {
            if ($inventory instanceof ContainerInventory) {
                $container = $inventory;
            }
        }
        foreach ($transaction->getActions() as $action) {
            if (($item = $action->getTargetItem())->getNamedTag()->hasTag("ZoomItem", StringTag::class)) {
                $zoomItem = $item;
            }
        }
        if (!is_null($zoomItem) and !is_null($container) and !$transaction->getSource()->hasPermission("zoomitem.give"))
            $event->setCancelled();

    }

    /**
     * Handle PlayerToggleSneak event
     * @param PlayerToggleSneakEvent $event
     * @priority HIGH
     * @ignoreCancelled true
     */
    public function onSneakToggled(PlayerToggleSneakEvent $event): void {
        $player = $event->getPlayer();
        $item = $player->getInventory()->getItemInHand();
        if ($player->hasPermission("zoomitem.use") and strtolower($this->getMode()) === "sneak" and
            $item->getNamedTag()->hasTag("ZoomItem", StringTag::class)
        ) {
            if ($player->isSneaking()) {
                $player->getAttributeMap()->getAttribute(Attribute::MOVEMENT_SPEED)->setValue(0.1);
                $player->sendTip(TextFormat::colorize($this->outMessage()));
                return;
            }
            $player->getAttributeMap()->getAttribute(Attribute::MOVEMENT_SPEED)->setValue(0.01, true);
            $player->sendTip(TextFormat::colorize($this->inMessage()));
        }
    }

    /**
     * Handle PlayerInteract event
     * @param PlayerInteractEvent $event
     * @priority HIGH
     * @ignoreCancelled true
     */
    public function onItemInteract(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();
        $item = $event->getItem();
        $action = $event->getAction();
        if ($player->hasPermission("zoomiteem.use") and strtolower($this->getMode()) === "tap" and
            ($action === PlayerInteractEvent::RIGHT_CLICK_BLOCK or $action === PlayerInteractEvent::RIGHT_CLICK_AIR) and
            $item->getNamedTag()->hasTag("ZoomItem", StringTag::class)
        ) {
            if ($item->getNamedTag()->getString("ZoomItem") === "ZoomIn") {
                $player->getAttributeMap()->getAttribute(Attribute::MOVEMENT_SPEED)->setValue(0.01, true);
                $player->sendTip(TextFormat::colorize($this->inMessage()));
                $itemR = ItemFactory::get($item->getId(), $item->getDamage(), $item->getCount())
                    ->setCustomName(TextFormat::colorize($this->inName()));
                $itemR->getNamedTag()->setString("ZoomItem", "ZoomOut");
                $player->getInventory()->setItemInHand($itemR);
            } elseif ($item->getNamedTag()->getString("ZoomItem") === "ZoomOut") {
                $player->getAttributeMap()->getAttribute(Attribute::MOVEMENT_SPEED)->setValue(0.1);
                $player->sendTip(TextFormat::colorize($this->outMessage()));
                $itemR = ItemFactory::get($item->getId(), $item->getDamage(), $item->getCount())
                    ->setCustomName(TextFormat::colorize($this->outName()));
                $itemR->getNamedTag()->setString("ZoomItem", "ZoomIn");
                $player->getInventory()->setItemInHand($itemR);
            }
            $event->setCancelled();
        }
    }
}