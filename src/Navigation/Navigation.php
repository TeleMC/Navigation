<?php
namespace Navigation;

use pocketmine\command\{Command, CommandSender};
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use UiLibrary\UiLibrary;

class Navigation extends PluginBase implements Listener {

    private static $instance = null;
    //public $pre = "§e§l[ §f시스템 §e]§r§e";
    public $pre = "§e•";
    public $isNavigating = [];
    public $Navigator = [];
    public $distance = 10;
    public $destination = [];

    public static function getInstance() {
        return self::$instance;
    }

    public function onLoad() {
        self::$instance = $this;
    }

    public function onEnable() {
        @mkdir($this->getDataFolder());
        $this->saveResource("config.yml");
        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $this->data = $this->config->getAll();
        $this->ui = UiLibrary::getInstance();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onQuit(PlayerQuitEvent $ev) {
        $player = $ev->getPlayer();
        if (isset($this->isNavigating[$player->getId()])) {
            unset($this->isNavigating[$player->getId()]);
            if (isset($this->Navigator[$player->getId()]))
                $this->Navigator[$player->getId()]->despawnFrom($player);
            unset($this->Navigator[$player->getId()]);
            unset($this->destination[$player->getId()]);
        }
    }

    public function onMove(PlayerMoveEvent $ev) {
        $player = $ev->getPlayer();
        if (isset($this->isNavigating[$player->getId()])) {
            if ($this->isNavigating[$player->getId()]->level->getName() !== $player->level->getName()) {
                $this->endGuide($player);
                $player->sendMessage("{$this->pre} 대륙 이동을 감지했습니다! 길 안내를 종료합니다.");
                return true;
            }
            if (!isset($this->time[$player->getId()]))
                $this->time[$player->getId()] = time();
            if (time() - $this->time[$player->getId()] < $this->data["time"])
                return;
            $this->time[$player->getId()] = time();
            if (!isset($this->Navigator[$player->getId()]))
                $this->Navigator[$player->getId()] = new NavigationText($this, $this->destination[$player->getId()]);
            $pos = $this->isNavigating[$player->getId()];
            if ($player->distance($pos) < $this->data["distance"]) {
                /*$x = $pos->x;
                $y = $pos->y + 2;
                $z = $pos->z;*/
                $player->sendMessage("{$this->pre} 목적지 부근에 도착하였습니다.");
                $this->endGuide($player);
                return true;
            } else {
                $x = $pos->x - $player->x;
                $y = $player->y + 2;
                $z = $pos->z - $player->z;

                if ($x == 0) $x += 0.1;
                if ($z == 0) $z += 0.1;

                if ($x < $z) {
                    $b = abs($z / $x);
                    $l = ($this->data["distance"] * $this->data["distance"]) / ($b + 1);
                    $x_ = sqrt($l);
                    $z_ = sqrt($l * $b);
                } elseif ($x > $z) {
                    $b = abs($x / $z);
                    $l = ($this->data["distance"] * $this->data["distance"]) / ($b + 1);
                    $x_ = sqrt($l * $b);
                    $z_ = sqrt($l);
                } else {
                    $b = 1;
                    $l = ($this->data["distance"] * $this->data["distance"]) / ($b + 1);
                    $x_ = sqrt($l);
                    $z_ = sqrt($l);
                }

                if ($x > 0) $x = $player->x + $x_;
                elseif ($x < 0) $x = $player->x - $x_;
                else $x = $player->x;
                if ($z > 0) $z = $player->z + $z_;
                elseif ($z < 0) $z = $player->z - $z_;
                else $z = $player->z;
            }
            $this->Navigator[$player->getId()]->spawnTo($player, new Position($x, $y, $z, $player->getLevel()));
        }
    }

    public function endGuide(Player $player) {
        if (isset($this->isNavigating[$player->getId()])) {
            unset($this->isNavigating[$player->getId()]);
            if (isset($this->Navigator[$player->getId()]))
                $this->Navigator[$player->getId()]->despawnFrom($player);
            unset($this->Navigator[$player->getId()]);
            unset($this->destination[$player->getId()]);
        }
    }

    public function NavigationUI(Player $player) {
        $form = $this->ui->SimpleForm(function (Player $player, array $data) {
            if (!isset($data[0])) return;

            if ($data[0] == 0) {
                $form = $this->ui->SimpleForm(function (Player $player, array $data) {
                    if (!isset($data[0])) return;
                    $name = $this->navigation["npc"][$data[0]];
                    $pos = explode(":", $this->data["npc"][$this->navigation["npc"][$data[0]]]);
                    if ($player->getLevel()->getName() !== $pos[3]) {
                        $player->sendMessage("{$this->pre} 오류! 해당 대륙에서 찾아볼 수 없습니다!");
                        return false;
                    }
                    $pos = new Position($pos[0], $pos[1], $pos[2], $this->getServer()->getLevelByName($pos[3]));
                    $this->startGuide($player, $pos, $name);
                    $player->sendMessage("{$this->pre} 길 안내를 시작합니다! 화살표를 따라가세요!");
                    return true;
                });
                $form->setTitle("Tele Navigation");
                $count = 0;
                foreach ($this->data["npc"] as $key => $value) {
                    $this->navigation["npc"][$count] = $key;
                    $form->addButton("§l" . $key);
                    $count++;
                }
                $form->sendToPlayer($player);
            }

            if ($data[0] == 1) {
                $form = $this->ui->SimpleForm(function (Player $player, array $data) {
                    if (!isset($data[0])) return;
                    $name = $this->navigation["place"][$data[0]];
                    $pos = explode(":", $this->data["place"][$this->navigation["place"][$data[0]]]);
                    if ($player->getLevel()->getName() !== $pos[3]) {
                        $player->sendMessage("{$this->pre} 오류! 해당 대륙에서 찾아볼 수 없습니다!");
                        return false;
                    }
                    $pos = new Position($pos[0], $pos[1], $pos[2], $this->getServer()->getLevelByName($pos[3]));
                    $this->startGuide($player, $pos, $name);
                    $player->sendMessage("{$this->pre} 길 안내를 시작합니다! 화살표 따라가세요!");
                    return true;
                });
                $form->setTitle("Tele Navigation");
                $count = 0;
                foreach ($this->data["place"] as $key => $value) {
                    $this->navigation["place"][$count] = $key;
                    $form->addButton("§l" . $key);
                    $count++;
                }
                $form->sendToPlayer($player);
            }

            if ($data[0] == 2) {
                if (!isset($this->isNavigating[$player->getId()])) {
                    $player->sendMessage("{$this->pre} 길 안내 이용중이 아닙니다.");
                    return false;
                } else {
                    $this->endGuide($player);
                    $player->sendMessage("{$this->pre} 길 안내를 종료합니다.");
                    return true;
                }
            }
        });
        $form->setTitle("Tele Navigation");
        $form->addButton("§l특정 NPC 찾기\n§r§8특정 NPC를 목적지를 지정합니다.");
        $form->addButton("§l특정 장소 찾기\n§r§8특정 장소를 목적지를 지정합니다.");
        $form->addButton("§l길 찾기 종료\n§r§8길 찾기를 종료합니다.");
        $form->sendToPlayer($player);
    }

    public function startGuide(Player $player, Position $pos, string $destination) {
        $this->endGuide($player);
        $this->isNavigating[$player->getId()] = $pos;
        $this->destination[$player->getId()] = $destination;
    }

    public function onCommand(CommandSender $sender, Command $cmd, $label, $args): bool {
        if ($cmd->getName() == "navi") {
            if (!$sender->isOp()) {
                $sender->sendMessage("{$this->pre} 권한이 없습니다.");
                return false;
            }
            if ($args[0] == "주기") {
                if (!isset($args[1]) || !is_numeric($args[1]) || $args[1] < 0) {
                    $sender->sendMessage("{$this->pre} /navi 주기 <초> | 길 안내 태그를 띄울 간격을 설정합니다.");
                    return false;
                }
                $this->data["time"] = $args[1];
                $this->config->setAll($this->data);
                $this->config->save();
                $sender->sendMessage("{$this->pre} 주기를 {$args[1]}초로 설정했습니다.");
                return true;
            } elseif ($args[0] == "간격") {
                if (!isset($args[1]) || !is_numeric($args[1]) || $args[1] < 0) {
                    $sender->sendMessage("{$this->pre} /navi 간격 <칸> | 길 안내 태그를 띄울 거리를 설정합니다.");
                    return false;
                }
                $this->data["distance"] = $args[1];
                $this->config->setAll($this->data);
                $this->config->save();
                $sender->sendMessage("{$this->pre} 거리를 {$args[1]}칸으로 설정했습니다.");
                return true;
            } else {
                $sender->sendMessage("--- 길 안내 도움말 1 / 1 ---");
                $sender->sendMessage("{$this->pre} /navi 주기 <초> | 길 안내 태그를 띄울 간격을 설정합니다.");
                $sender->sendMessage("{$this->pre} /navi 간격 <칸> | 길 안내 태그를 띄울 거리를 설정합니다.");
                return false;
            }
        }
    }
}
