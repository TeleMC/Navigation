<?php
namespace Navigation;

use pocketmine\entity\Entity;
use pocketmine\entity\Skin;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\RemoveEntityPacket;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\Player;
use pocketmine\utils\UUID;

class NavigationText {
    private $plugin;
    private $eid;
    private $uuid;
    private $text;

    public function __construct(Navigation $plugin, string $destination) {
        $this->plugin = $plugin;
        $this->eid = Entity::$entityCount++;
        $this->uuid = UUID::fromRandom();
        $this->text = "   ||   \n| || |\n|  ||  |\n   ||   \n   ||   \n§e목적지: §f{$destination}";
    }

    public function getId() {
        return $this->eid;
    }

    public function spawnTo(Player $player, Position $pos) {
        $this->despawnFrom($player);
        $gps = Navigation::getInstance()->isNavigating[$player->getId()];
        $y = $gps->getFloorY() - $player->getFloorY();
        $distance = (int) $player->distance($gps);
        $pk = new AddPlayerPacket();
        $pk->uuid = $this->uuid;
        $pk->username = $this->text . "\n§e높이차: {$y}, 거리: {$distance}";
        $pk->entityRuntimeId = $this->eid;
        $pk->position = $pos;
        $pk->item = new Item(0, 0);
        $meta[Entity::DATA_SCALE] = [Entity::DATA_TYPE_FLOAT, 0.001];
        $pk->metadata = $meta;
        $player->dataPacket($pk);
        $pk = new PlayerListPacket();
        $pk->type = PlayerListPacket::TYPE_ADD;
        $pk->entries = [PlayerListEntry::createAdditionEntry($this->uuid, $this->eid, $this->text, new Skin("Standard_Custom", str_repeat("\x00", 8192)))];
        $player->dataPacket($pk);
        $pk = new PlayerListPacket();
        $pk->type = PlayerListPacket::TYPE_REMOVE;
        $pk->entries = [PlayerListEntry::createRemovalEntry($this->uuid)];
        $player->dataPacket($pk);
    }

    public function despawnFrom(Player $player) {
        $pk = new RemoveEntityPacket();
        $pk->entityUniqueId = $this->eid;
        $player->dataPacket($pk);
    }
}
